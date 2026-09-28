<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Roles;
use App\Config;
use App\Database;
use App\Logger;
use App\RateLimit;
use App\Response;
use App\Util;
use App\Validate;

final class TestController
{
    private static function isActive(): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'test_active' LIMIT 1");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? true : $value === 'true';
    }

    /**
     * Qayta topshirishga berilgan ruxsatlar: har bir yozuv — o'ta olmagan
     * xodimga admin tomonidan qo'lda berilgan bitta qo'shimcha urinish.
     */
    public const RETAKES_DDL = 'CREATE TABLE IF NOT EXISTS test_retakes (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        granted_by  INT NULL,
        granted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB';

    /** Har bir xodimga beriladigan boshlang'ich urinishlar soni (standart — 1). */
    public static function baseAttempts(): int
    {
        return max(1, Config::int('TEST_MAX_ATTEMPTS', 1));
    }

    /**
     * Xodimga ruxsat etilgan jami urinishlar: boshlang'ich + qo'lda berilgan
     * qayta topshirish ruxsatlari. Tranzaksiya ICHIDA chaqirilmasin — jadvalni
     * yaratuvchi DDL MySQL'da tranzaksiyani yashirincha yakunlab qo'yadi
     * (tranzaksiya ichida retakeGrants() ishlatiladi).
     */
    public static function maxAttempts(\PDO $db, int $userId): int
    {
        Util::ensureSchema($db, self::RETAKES_DDL);
        return self::baseAttempts() + self::retakeGrants($db, $userId);
    }

    private static function retakeGrants(\PDO $db, int $userId): int
    {
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM test_retakes WHERE user_id = :uid');
            $stmt->execute(['uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0; // jadval hali yaratilmagan
        }
    }

    /** Barcha xodimlar bo'yicha qayta topshirish ruxsatlari soni — [user_id => n] (statistika uchun). */
    public static function retakeGrantCounts(\PDO $db): array
    {
        Util::ensureSchema($db, self::RETAKES_DDL);
        $counts = [];
        foreach ($db->query('SELECT user_id, COUNT(*) AS n FROM test_retakes GROUP BY user_id') as $r) {
            $counts[(int) $r['user_id']] = (int) $r['n'];
        }
        return $counts;
    }

    private static function hasPassedAttempt(\PDO $db, int $userId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM test_attempts WHERE user_id = :uid AND passed = 1 LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private static function attemptsUsed(\PDO $db, int $userId): int
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM test_attempts WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    private static function bestPercent(\PDO $db, int $userId): ?int
    {
        $stmt = $db->prepare('SELECT MAX(percent) FROM test_attempts WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $v = $stmt->fetchColumn();
        return $v === null || $v === false ? null : (int) $v;
    }

    public static function getQuestions(array $input): void
    {
        $viewer = Auth::optionalUser($input);
        $isAdmin = $viewer && in_array($viewer['rol'], Roles::ANTICOR_VIEW, true);

        $db = Database::connection();
        $rows = $db->query('SELECT * FROM test_questions ORDER BY id ASC')->fetchAll();

        $questions = array_map(static function (array $r) use ($isAdmin) {
            $uz = [
                'savol' => $r['savol'], 'a' => $r['variant_a'], 'b' => $r['variant_b'],
                'c' => $r['variant_c'], 'd' => $r['variant_d'],
            ];
            $ru = [
                'savol' => $r['savol_ru'], 'a' => $r['variant_a_ru'], 'b' => $r['variant_b_ru'],
                'c' => $r['variant_c_ru'], 'd' => $r['variant_d_ru'],
            ];
            if ($isAdmin) {
                $uz['correct'] = $r['togri_javob'];
                $ru['correct'] = $r['togri_javob_ru'];
            }
            return ['id' => (int) $r['id'], 'uz' => $uz, 'ru' => $ru];
        }, $rows);

        $result = ['active' => self::isActive(), 'questions' => $questions];
        if ($viewer) {
            $userId = (int) $viewer['id'];
            $result['attemptsUsed'] = self::attemptsUsed($db, $userId);
            $result['maxAttempts'] = self::maxAttempts($db, $userId);
            $result['passed'] = self::hasPassedAttempt($db, $userId);
            $result['bestPercent'] = self::bestPercent($db, $userId);
            $result['certificate'] = CertificateController::hasCertificate($db, $userId);
        }

        Response::success($result);
    }

    public static function submit(array $input): void
    {
        $user = Auth::requireUser($input);
        // Javobda qaysi savolga noto'g'ri javob berilgani (wrongIds) qaytariladi —
        // reyt-limitsiz bu ketma-ket avtomatlashtirilgan urinish orqali to'g'ri
        // javoblarni asta-sekin "sinab topish" imkonini berardi.
        RateLimit::enforce('submitTest', 20, 3600);

        if (!self::isActive()) {
            Response::error('Test hozircha faol emas', 'TEST_INACTIVE');
        }

        $answers = Validate::array($input, 'answers');
        $db = Database::connection();

        $rows = $db->query('SELECT * FROM test_questions')->fetchAll();
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        $totalQuestions = count($rows);

        $letterCol = ['A' => 'variant_a', 'B' => 'variant_b', 'C' => 'variant_c', 'D' => 'variant_d'];
        $letterColRu = ['A' => 'variant_a_ru', 'B' => 'variant_b_ru', 'C' => 'variant_c_ru', 'D' => 'variant_d_ru'];

        $points = 0;
        $wrongIds = [];

        foreach ($answers as $answer) {
            $qid = (int) ($answer['id'] ?? 0);
            $letter = strtoupper((string) ($answer['letter'] ?? ''));
            if (!isset($byId[$qid]) || !isset($letterCol[$letter])) {
                continue;
            }
            $q = $byId[$qid];
            $uzMatch = $q[$letterCol[$letter]] !== null && $q[$letterCol[$letter]] === $q['togri_javob'];
            $ruMatch = $q[$letterColRu[$letter]] !== null && $q[$letterColRu[$letter]] === $q['togri_javob_ru'];
            if ($uzMatch || $ruMatch) {
                $points++;
            } else {
                $wrongIds[] = $qid;
            }
        }

        $maxPoints = max($totalQuestions, 1);
        $percent = (int) round($points / $maxPoints * 100);
        $threshold = Config::int('TEST_PASS_THRESHOLD', 80);
        $passed = $percent >= $threshold;

        // Urinishlar sonini tekshirish va yozish bitta tranzaksiyada, xodim
        // qatori qulflangan holda — bir vaqtda yuborilgan ikki so'rov cheklovni
        // aylanib o'tib, ortiqcha urinish yozib qo'ya olmasligi uchun.
        // Testdan o'tgan xodim qayta topshirmaydi; o'ta olmagan xodim esa
        // faqat admin qo'lda bergan ruxsat (test_retakes) bilan qayta topshiradi.
        $maxAttempts = self::maxAttempts($db, (int) $user['id']);
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $user['id']]);
            if (self::hasPassedAttempt($db, (int) $user['id'])) {
                $db->rollBack();
                Response::error("Siz testdan muvaffaqiyatli o'tgansiz", 'ALREADY_PASSED', 403);
            }
            $used = self::attemptsUsed($db, (int) $user['id']);
            if ($used >= $maxAttempts) {
                $db->rollBack();
                Response::error(
                    "Siz testni topshirib bo'lgansiz — qayta topshirish uchun Korrupsiyaga qarshi kurashish bo'limining ruxsati kerak",
                    'ATTEMPTS_EXHAUSTED',
                    403
                );
            }

            $ins = $db->prepare(
                'INSERT INTO test_attempts (user_id, points, max_points, percent, passed)
                 VALUES (:user_id, :points, :max_points, :percent, :passed)'
            );
            $ins->execute([
                'user_id' => $user['id'],
                'points' => $points,
                'max_points' => $totalQuestions,
                'percent' => $percent,
                'passed' => $passed ? 1 : 0,
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($passed) {
            // Sertifikat yaratilmasa ham test natijasi saqlanadi — yuklab olishda
            // (certificate-download.php) qayta urinib ko'riladi.
            try {
                CertificateController::ensureForUser($db, $user);
            } catch (\Throwable $e) {
                Logger::error('certificate', $e->getMessage());
            }
        }

        Response::success([
            'points' => $points,
            'maxPoints' => $totalQuestions,
            'percent' => $percent,
            'passed' => $passed,
            'certificate' => CertificateController::hasCertificate($db, (int) $user['id']),
            'attemptsUsed' => $used + 1,
            'maxAttempts' => $maxAttempts,
            'wrongIds' => $wrongIds,
        ]);
    }

    /**
     * O'ta olmagan xodimga testni qayta topshirishga ruxsat beradi (+1
     * urinish) va unga xabarnoma yuboradi. Faqat barcha urinishlarini
     * ishlatib bo'lgan va hali o'tmagan xodimga beriladi.
     */
    public static function grantRetake(array $input): void
    {
        $me = Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $userId = (int) Validate::int($input, 'userId');

        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, login FROM users WHERE id = :id AND rol != :superAdmin LIMIT 1');
        $stmt->execute(['id' => $userId, 'superAdmin' => Roles::SUPER_ADMIN]);
        $target = $stmt->fetch();
        if (!$target) {
            Response::error('Xodim topilmadi', 'NOT_FOUND', 404);
        }

        Util::ensureSchema($db, self::RETAKES_DDL); // tranzaksiyadan oldin (DDL uni yopib qo'yadi)
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $userId]);
            $used = self::attemptsUsed($db, $userId);
            if ($used === 0) {
                $db->rollBack();
                Response::error('Xodim hali testni topshirmagan', 'NOT_TAKEN', 409);
            }
            if (self::hasPassedAttempt($db, $userId)) {
                $db->rollBack();
                Response::error("Xodim testdan o'tgan — qayta topshirish kerak emas", 'ALREADY_PASSED', 409);
            }
            if ($used < self::baseAttempts() + self::retakeGrants($db, $userId)) {
                $db->rollBack();
                Response::error('Xodimda ishlatilmagan urinish bor — ruxsat allaqachon berilgan', 'RETAKE_PENDING', 409);
            }
            $ins = $db->prepare('INSERT INTO test_retakes (user_id, granted_by) VALUES (:uid, :by)');
            $ins->execute(['uid' => $userId, 'by' => $me['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Xabarnoma yuborilmasa ham ruxsat saqlanadi.
        try {
            $notify = $db->prepare(
                "INSERT INTO notifications (sender_id, matn, target_type, target_value)
                 VALUES (:sender, :matn, 'users', :login)"
            );
            $notify->execute([
                'sender' => $me['id'],
                'matn' => "Sizga «Korrupsiyaga qarshi kurashish» testini qayta topshirishga ruxsat berildi.\n"
                    . "Вам разрешено повторно пройти тест «Противодействие коррупции».",
                'login' => $target['login'],
            ]);
        } catch (\Throwable $e) {
            Logger::error('grantRetake', $e->getMessage());
        }

        Response::success(['maxAttempts' => self::maxAttempts($db, $userId)]);
    }

    public static function setActive(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $active = Validate::bool($input, 'active');

        $db = Database::connection();
        $stmt = $db->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('test_active', :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2"
        );
        $stmt->execute(['v' => $active ? 'true' : 'false', 'v2' => $active ? 'true' : 'false']);

        Response::success();
    }

    private static function questionFields(array $input): array
    {
        return [
            'savol' => Validate::requiredStr($input, 'savol', 2000),
            'variant_a' => Validate::str($input, 'a', 500),
            'variant_b' => Validate::str($input, 'b', 500),
            'variant_c' => Validate::str($input, 'c', 500),
            'variant_d' => Validate::str($input, 'd', 500),
            'togri_javob' => Validate::str($input, 'correct', 500),
            'savol_ru' => Validate::str($input, 'savolRu', 2000),
            'variant_a_ru' => Validate::str($input, 'aRu', 500),
            'variant_b_ru' => Validate::str($input, 'bRu', 500),
            'variant_c_ru' => Validate::str($input, 'cRu', 500),
            'variant_d_ru' => Validate::str($input, 'dRu', 500),
            'togri_javob_ru' => Validate::str($input, 'correctRu', 500),
        ];
    }

    public static function add(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $f = self::questionFields($input);

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO test_questions
             (savol, variant_a, variant_b, variant_c, variant_d, togri_javob,
              savol_ru, variant_a_ru, variant_b_ru, variant_c_ru, variant_d_ru, togri_javob_ru)
             VALUES
             (:savol, :variant_a, :variant_b, :variant_c, :variant_d, :togri_javob,
              :savol_ru, :variant_a_ru, :variant_b_ru, :variant_c_ru, :variant_d_ru, :togri_javob_ru)'
        );
        $stmt->execute($f);

        Response::success(['id' => (int) $db->lastInsertId()]);
    }

    public static function edit(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }
        $f = self::questionFields($input);
        $f['id'] = $id;

        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE test_questions SET
                savol = :savol, variant_a = :variant_a, variant_b = :variant_b,
                variant_c = :variant_c, variant_d = :variant_d, togri_javob = :togri_javob,
                savol_ru = :savol_ru, variant_a_ru = :variant_a_ru, variant_b_ru = :variant_b_ru,
                variant_c_ru = :variant_c_ru, variant_d_ru = :variant_d_ru, togri_javob_ru = :togri_javob_ru
             WHERE id = :id'
        );
        $stmt->execute($f);

        Response::success();
    }

    public static function delete(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM test_questions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success();
    }
}
