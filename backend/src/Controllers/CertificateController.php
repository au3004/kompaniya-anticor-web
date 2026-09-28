<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\CertificatePdf;
use App\Config;
use App\Database;
use App\Filials;
use App\Response;
use App\Roles;
use App\Util;
use App\Validate;

/**
 * Testdan o'tish balini qo'lga kiritgan xodimga beriladigan sertifikat (PDF).
 * Xodimda doim faqat bitta amaldagi sertifikat bo'ladi — eng yuqori ball
 * olingan muvaffaqiyatli urinishga tegishli (teng bo'lsa — keyingisi).
 * Sertifikatga chiqadigan har qanday ma'lumot (F.I.Sh, filial, bo'linma,
 * imzo qo'yuvchilar, dizayn versiyasi, tekshiruv havolasi) o'zgarsa, u keyingi
 * ochilishda qayta yaratiladi (sana doim o'sha urinish kuni). Dizaynning
 * o'zi App\CertificatePdf'da. Fayllar backend/certificates/ ichida (public/
 * dan tashqarida) saqlanadi va faqat certificate-download.php orqali beriladi.
 *
 * QR-kodlar certificate-verify.php?t=<token> sahifasiga olib boradi — u yerda
 * sertifikat haqiqiyligi (raqami, F.I.Sh, filiali, sanasi) tekshiriladi.
 *
 * Admin sertifikatni bekor qila oladi (Hisobotlar → Sertifikatlar):
 * revoked_attempt_id o'sha test urinishiga tenglashtiriladi va shu natija
 * uchun sertifikat boshqa berilmaydi. Xodim testni qaytadan muvaffaqiyatli
 * topshirsa (yangi urinish — admin test natijalarini o'chirib, imkoniyat
 * bergandan keyin), sertifikat yana avtomatik beriladi.
 */
final class CertificateController
{
    private const DDL = 'CREATE TABLE IF NOT EXISTS certificates (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        user_id          INT NOT NULL UNIQUE,
        test_attempt_id  INT NOT NULL,
        fish             VARCHAR(500) NOT NULL,
        filial           VARCHAR(50) NULL,
        file_name        VARCHAR(64) NOT NULL,
        issued_at        DATETIME NOT NULL,
        render_sig       CHAR(40) NULL,
        verify_token     CHAR(24) NULL UNIQUE,
        revoked_attempt_id INT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB';

    // Jadval shu ustunlarsiz yaratilgan bazalar uchun.
    private const ALTER_DDL = 'ALTER TABLE certificates
        ADD COLUMN IF NOT EXISTS render_sig CHAR(40) NULL AFTER issued_at,
        ADD COLUMN IF NOT EXISTS verify_token CHAR(24) NULL UNIQUE AFTER render_sig,
        ADD COLUMN IF NOT EXISTS revoked_attempt_id INT NULL AFTER verify_token';

    public const DEFAULT_FILIAL_TITLE = 'Filial direktori';
    public const DEFAULT_KOMPLAENS_TITLE = 'Komplaens departamenti direktori';

    /** Namuna (preview) sertifikatining QR tokeni — tekshiruv sahifasi uni "namuna" deb ko'rsatadi. */
    public const SAMPLE_TOKEN = 'namuna';

    public static function certificatesDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/certificates';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /** Xodimda amaldagi sertifikat bormi: testdan o'tgan va shu natija uchun sertifikat bekor qilinmagan. */
    public static function hasCertificate(\PDO $db, int $userId): bool
    {
        $attempt = self::bestPassingAttempt($db, $userId);
        return $attempt !== null && !isset(self::revokedUserIds($db)[$userId]);
    }

    /**
     * Sertifikati eng yaxshi (amaldagi) test natijasi uchun bekor qilingan
     * xodimlar — [user_id => true]. Jadval/ustun hali bo'lmasa bo'sh.
     */
    public static function revokedUserIds(\PDO $db): array
    {
        try {
            $rows = $db->query(
                'SELECT c.user_id FROM certificates c
                 WHERE c.revoked_attempt_id IS NOT NULL
                   AND c.revoked_attempt_id = (
                       SELECT t.id FROM test_attempts t
                       WHERE t.user_id = c.user_id AND t.passed = 1
                       ORDER BY t.percent DESC, t.attempted_at DESC, t.id DESC LIMIT 1
                   )'
            )->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
        return array_fill_keys(array_map('intval', $rows), true);
    }

    private static function bestPassingAttempt(\PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare(
            'SELECT id, points, max_points, attempted_at FROM test_attempts
             WHERE user_id = :uid AND passed = 1
             ORDER BY percent DESC, attempted_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Xodimning amaldagi sertifikati (kerak bo'lsa shu yerda yaratib/yangilab).
     * Testdan hali muvaffaqiyatli o'tmagan bo'lsa null. $user — users jadvalidagi
     * to'liq qator (familiya, ism, otasining_ismi, filial, bolinma).
     */
    public static function ensureForUser(\PDO $db, array $user): ?array
    {
        $userId = (int) $user['id'];
        $attempt = self::bestPassingAttempt($db, $userId);
        if (!$attempt) {
            return null;
        }

        Util::ensureSchema($db, self::DDL);
        Util::ensureSchema($db, self::ALTER_DDL);
        $fish = Util::fullName($user);
        $filial = ($user['filial'] ?? null) ?: null;
        $dir = self::certificatesDir();

        $stmt = $db->prepare('SELECT * FROM certificates WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $existing = $stmt->fetch() ?: null;

        // Admin shu natija uchun sertifikatni bekor qilgan.
        if ($existing && (int) ($existing['revoked_attempt_id'] ?? 0) === (int) $attempt['id']) {
            return null;
        }

        // Tekshiruv tokeni xodimga bir marta beriladi va qayta yaratishda saqlanadi.
        $token = ($existing['verify_token'] ?? null) ?: bin2hex(random_bytes(12));
        $issuedAt = (string) $attempt['attempted_at'];
        $data = self::pdfData($db, [
            'number' => self::number($issuedAt, (int) $attempt['id']),
            'fish' => $fish,
            'filial' => $filial,
            'bolinma' => trim((string) ($user['bolinma'] ?? '')),
            'ball' => (int) $attempt['points'] . ' / ' . (int) $attempt['max_points'],
            'date' => date('d.m.Y', strtotime($issuedAt)),
        ], $token);
        $sig = sha1((string) json_encode([CertificatePdf::VERSION, $data]));

        if (
            $existing
            && (int) $existing['test_attempt_id'] === (int) $attempt['id']
            && $existing['render_sig'] === $sig
            && $existing['revoked_attempt_id'] === null
            && is_file($dir . '/' . $existing['file_name'])
        ) {
            return $existing;
        }

        $fileName = bin2hex(random_bytes(16)) . '.pdf';
        if (file_put_contents($dir . '/' . $fileName, CertificatePdf::render($data)) === false) {
            throw new \RuntimeException("Sertifikat faylini saqlab bo'lmadi");
        }

        try {
            $upsert = $db->prepare(
                'INSERT INTO certificates (user_id, test_attempt_id, fish, filial, file_name, issued_at, render_sig, verify_token)
                 VALUES (:uid, :aid, :fish, :filial, :file, :issued, :sig, :token)
                 ON DUPLICATE KEY UPDATE test_attempt_id = :aid2, fish = :fish2, filial = :filial2,
                                         file_name = :file2, issued_at = :issued2, render_sig = :sig2,
                                         verify_token = :token2, revoked_attempt_id = NULL'
            );
            $upsert->execute([
                'uid' => $userId,
                'aid' => $attempt['id'], 'aid2' => $attempt['id'],
                'fish' => $fish, 'fish2' => $fish,
                'filial' => $filial, 'filial2' => $filial,
                'file' => $fileName, 'file2' => $fileName,
                'issued' => $issuedAt, 'issued2' => $issuedAt,
                'sig' => $sig, 'sig2' => $sig,
                'token' => $token, 'token2' => $token,
            ]);
        } catch (\Throwable $e) {
            @unlink($dir . '/' . $fileName);
            throw $e;
        }

        if ($existing && $existing['file_name'] !== '' && $existing['file_name'] !== $fileName) {
            @unlink($dir . '/' . $existing['file_name']);
        }

        return [
            'user_id' => $userId,
            'test_attempt_id' => (int) $attempt['id'],
            'fish' => $fish,
            'filial' => $filial,
            'file_name' => $fileName,
            'issued_at' => $issuedAt,
            'render_sig' => $sig,
            'verify_token' => $token,
        ];
    }

    /** Admin uchun namuna — imzo qo'yuvchilar va dizayn qanday chiqishini ko'rish. */
    public static function previewPdf(\PDO $db, ?string $filial): string
    {
        $filial = Filials::isValid((string) $filial) ? $filial : Filials::MARKAZIY;
        return CertificatePdf::render(self::pdfData($db, [
            'number' => self::number(date('Y-m-d'), 0),
            'fish' => 'Karimova Dilnoza Rustamovna',
            'filial' => $filial,
            'bolinma' => "Mijozlarga xizmat ko‘rsatish bo‘limi",
            'ball' => '92 / 100',
            'date' => date('d.m.Y'),
        ], self::SAMPLE_TOKEN));
    }

    /** Sertifikat raqami: AK-<yil>-<urinish raqami, 6 xona>. */
    public static function number(string $issuedAt, int $attemptId): string
    {
        return 'AK-' . date('Y', strtotime($issuedAt)) . '-' . str_pad((string) $attemptId, 6, '0', STR_PAD_LEFT);
    }

    /** $base'dagi filial kalitini nomiga almashtirib, imzo qo'yuvchilar va QR havolalarini qo'shadi. */
    private static function pdfData(\PDO $db, array $base, string $token): array
    {
        $settings = self::loadSettings($db);
        $filialKey = $base['filial'];
        $signers = [];
        if ($filialKey !== null && isset($settings['filials'][$filialKey])) {
            $signers[] = $settings['filials'][$filialKey] + ['qr' => self::verifyUrl($token, 1)];
        }
        $signers[] = $settings['komplaens'] + ['qr' => self::verifyUrl($token, 2)];

        $base['filial'] = Filials::labelUz($filialKey);
        $base['signers'] = $signers;
        return $base;
    }

    public static function verifyUrl(string $token, int $signer): string
    {
        $base = rtrim((string) Config::get('PUBLIC_BASE_URL', ''), '/');
        if ($base === '' && !empty($_SERVER['HTTP_HOST'])) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            $base = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $dir;
        }
        return $base . '/certificate-verify.php?t=' . rawurlencode($token) . '&s=' . $signer;
    }

    // ------------------------------------------------------------------
    // Imzo qo'yuvchilar (filial rahbarlari va komplaens direktori)
    // ------------------------------------------------------------------

    /**
     * @return array{komplaens: array{title:string,name:string}, filials: array<string, array{title:string,name:string}>}
     */
    public static function loadSettings(\PDO $db): array
    {
        $values = [];
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'cert\\_%'");
            foreach ($stmt->fetchAll() as $row) {
                $values[$row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (\Throwable $e) {
            // Jadval bo'lmasa — standart qiymatlar.
        }

        $pick = static fn (string $key, string $default): string => ($values[$key] ?? '') !== '' ? $values[$key] : $default;
        $result = [
            'komplaens' => [
                'title' => $pick('cert_komplaens_title', self::DEFAULT_KOMPLAENS_TITLE),
                'name' => $values['cert_komplaens_name'] ?? '',
            ],
            'filials' => [],
        ];
        foreach (Filials::ALL as $key) {
            $result['filials'][$key] = [
                'title' => $pick("cert_{$key}_title", self::DEFAULT_FILIAL_TITLE),
                'name' => $values["cert_{$key}_name"] ?? '',
            ];
        }
        return $result;
    }

    public static function getSettings(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        Response::success(self::loadSettings(Database::connection()));
    }

    public static function saveSettings(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);

        $rows = [];
        $komplaens = is_array($input['komplaens'] ?? null) ? $input['komplaens'] : [];
        $rows['cert_komplaens_title'] = Validate::str($komplaens, 'title', 100);
        $rows['cert_komplaens_name'] = Validate::str($komplaens, 'name', 100);
        $filials = is_array($input['filials'] ?? null) ? $input['filials'] : [];
        foreach (Filials::ALL as $key) {
            $item = is_array($filials[$key] ?? null) ? $filials[$key] : [];
            $rows["cert_{$key}_title"] = Validate::str($item, 'title', 100);
            $rows["cert_{$key}_name"] = Validate::str($item, 'name', 100);
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        $db->beginTransaction();
        try {
            foreach ($rows as $key => $value) {
                $value = trim($value);
                $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Response::success(self::loadSettings($db));
    }

    // ------------------------------------------------------------------
    // Sertifikat olganlar ro'yxati va bekor qilish (Hisobotlar bo'limi)
    // ------------------------------------------------------------------

    /**
     * Amaldagi sertifikati bor xodimlar (testdan o'tgan va bekor qilinmagan).
     * Sertifikat fayli hali yaratilmagan bo'lsa ham ro'yxatga kiradi — u
     * birinchi ochilishda yaratiladi. id — xodim (users.id).
     */
    public static function listCertificates(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_VIEW);
        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT t.id AS attempt_id, t.user_id, t.points, t.max_points, t.attempted_at,
                    u.familiya, u.ism, u.otasining_ismi, u.filial
             FROM test_attempts t
             JOIN users u ON u.id = t.user_id
             WHERE t.passed = 1 AND u.rol != :superAdmin
             ORDER BY t.user_id, t.percent DESC, t.attempted_at DESC, t.id DESC'
        );
        $stmt->execute(['superAdmin' => Roles::SUPER_ADMIN]);
        $revoked = self::revokedUserIds($db);

        $best = [];
        foreach ($stmt->fetchAll() as $r) {
            $uid = (int) $r['user_id'];
            if (!isset($best[$uid]) && !isset($revoked[$uid])) {
                $best[$uid] = $r;
            }
        }
        usort($best, static fn (array $a, array $b): int => strcmp((string) $b['attempted_at'], (string) $a['attempted_at']));

        $list = array_map(static fn (array $r): array => [
            'id' => (int) $r['user_id'],
            'raqam' => self::number((string) $r['attempted_at'], (int) $r['attempt_id']),
            'fish' => Util::fullName($r),
            'filial' => $r['filial'] ?: null,
            'ball' => (int) $r['points'] . '/' . (int) $r['max_points'],
            'sana' => date('d.m.Y', strtotime((string) $r['attempted_at'])),
        ], $best);

        Response::success(['certificates' => array_values($list)]);
    }

    /**
     * Tanlangan xodimlarning (ids — users.id) amaldagi sertifikatini bekor
     * qiladi: PDF o'chiriladi, QR "amal qilmaydi" deb ko'rsatadi.
     */
    public static function revokeCertificates(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $ids = array_values(array_unique(array_filter(
            array_map('intval', Validate::array($input, 'ids')),
            static fn (int $id) => $id > 0
        )));
        if (!$ids) {
            Response::error('ID ro\'yxati talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);
        Util::ensureSchema($db, self::ALTER_DDL);
        $dir = self::certificatesDir();
        $userStmt = $db->prepare('SELECT * FROM users WHERE id = :id AND rol != :superAdmin LIMIT 1');
        $certStmt = $db->prepare('SELECT id, file_name FROM certificates WHERE user_id = :uid LIMIT 1');
        $update = $db->prepare('UPDATE certificates SET revoked_attempt_id = :aid, file_name = \'\' WHERE id = :id');
        $insert = $db->prepare(
            "INSERT INTO certificates (user_id, test_attempt_id, fish, filial, file_name, issued_at, revoked_attempt_id)
             VALUES (:uid, :aid, :fish, :filial, '', :issued, :aid2)"
        );

        $revoked = 0;
        foreach ($ids as $userId) {
            $userStmt->execute(['id' => $userId, 'superAdmin' => Roles::SUPER_ADMIN]);
            $user = $userStmt->fetch();
            $attempt = $user ? self::bestPassingAttempt($db, $userId) : null;
            if (!$attempt) {
                continue;
            }
            $certStmt->execute(['uid' => $userId]);
            $cert = $certStmt->fetch();
            if ($cert) {
                $update->execute(['aid' => $attempt['id'], 'id' => $cert['id']]);
                if ($cert['file_name'] !== '' && basename((string) $cert['file_name']) === $cert['file_name']) {
                    @unlink($dir . '/' . $cert['file_name']);
                }
            } else {
                $insert->execute([
                    'uid' => $userId, 'aid' => $attempt['id'], 'aid2' => $attempt['id'],
                    'fish' => Util::fullName($user), 'filial' => ($user['filial'] ?? null) ?: null,
                    'issued' => $attempt['attempted_at'],
                ]);
            }
            $revoked++;
        }

        Response::success(['deleted' => $revoked]);
    }

    /**
     * Tekshiruv sahifasi uchun: token bo'yicha amaldagi sertifikat va uning
     * egasi (xodim hali ham testdan o'tgan bo'lsa), aks holda null.
     * @return array{certificate: array, user: array}|null
     */
    public static function findByToken(\PDO $db, string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{24}$/', $token)) {
            return null;
        }
        try {
            $stmt = $db->prepare(
                'SELECT u.* FROM certificates c JOIN users u ON u.id = c.user_id
                 WHERE c.verify_token = :t LIMIT 1'
            );
            $stmt->execute(['t' => $token]);
            $user = $stmt->fetch();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$user || $user['rol'] === Roles::SUPER_ADMIN) {
            return null;
        }
        // Hozirgi holat bo'yicha (masalan test natijalari o'chirilgan bo'lsa — endi amal qilmaydi).
        $certificate = self::ensureForUser($db, $user);
        return $certificate ? ['certificate' => $certificate, 'user' => $user] : null;
    }
}
