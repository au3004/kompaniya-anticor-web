<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Util;
use App\Validate;
use PDOException;

final class AdminController
{
    private const TUGILGAN_SANA_DDL = "ALTER TABLE users ADD COLUMN IF NOT EXISTS tugilgan_sana DATE AFTER otasining_ismi";

    public static function addEmployee(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $login = Validate::requiredStr($input, 'login', 100);
        $parol = Validate::requiredStr($input, 'parol', 255);
        $familiya = Validate::requiredStr($input, 'familiya', 150);
        $ism = Validate::requiredStr($input, 'ism', 150);
        $otasi = Validate::str($input, 'otasi', 150);
        $tugilganSana = self::normalizeDate(Validate::str($input, 'tugilganSana', 10));
        $lavozim = Validate::str($input, 'lavozim', 200);
        $lavozimRu = Validate::str($input, 'lavozimRu', 200);
        $bolinma = Validate::str($input, 'bolinma', 200);
        $bolinmaRu = Validate::str($input, 'bolinmaRu', 200);
        $telefon = Validate::str($input, 'telefon', 20);
        $rol = Validate::str($input, 'rol', 20);

        if (!Validate::isStrongPassword($parol)) {
            Response::error(Validate::WEAK_PASSWORD_MESSAGE, 'WEAK_PASSWORD', 422);
        }
        if (!in_array($rol, ['user', 'admin', 'gl-admin'], true)) {
            $rol = 'user';
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        $stmt = $db->prepare(
            'INSERT INTO users (login, password_hash, familiya, ism, otasining_ismi, tugilgan_sana, lavozim, lavozim_ru, bolinma, bolinma_ru, telefon, rol)
             VALUES (:login, :hash, :familiya, :ism, :otasi, :tugilgan_sana, :lavozim, :lavozim_ru, :bolinma, :bolinma_ru, :telefon, :rol)'
        );

        try {
            $stmt->execute([
                'login' => $login,
                'hash' => Auth::hashPassword($parol),
                'familiya' => $familiya,
                'ism' => $ism,
                'otasi' => $otasi !== '' ? $otasi : null,
                'tugilgan_sana' => $tugilganSana,
                'lavozim' => $lavozim !== '' ? $lavozim : null,
                'lavozim_ru' => $lavozimRu !== '' ? $lavozimRu : null,
                'bolinma' => $bolinma !== '' ? $bolinma : null,
                'bolinma_ru' => $bolinmaRu !== '' ? $bolinmaRu : null,
                'telefon' => $telefon !== '' ? $telefon : null,
                'rol' => $rol,
            ]);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                Response::error('Bu login band', 'LOGIN_TAKEN', 409);
            }
            throw $e;
        }

        Response::success(['id' => (int) $db->lastInsertId()]);
    }

    public static function usersList(array $input): void
    {
        Auth::requireRole($input, ['gl-admin', 'admin']);

        $db = Database::connection();
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        $rows = $db->query(
            'SELECT u.id, u.login, u.familiya, u.ism, u.otasining_ismi, u.tugilgan_sana, u.lavozim, u.lavozim_ru,
                    u.bolinma, u.bolinma_ru, u.telefon, u.rol, la.locked_until
             FROM users u
             LEFT JOIN login_attempts la ON la.login = u.login
             ORDER BY u.id ASC'
        )->fetchAll();

        $users = array_map(static function (array $r) {
            $lockedUntil = $r['locked_until'] ?? null;
            $locked = $lockedUntil && strtotime((string) $lockedUntil) > time();
            return [
                'id' => (int) $r['id'],
                'login' => $r['login'],
                'familiya' => $r['familiya'],
                'ism' => $r['ism'],
                'otasi' => $r['otasining_ismi'],
                'tugilganSana' => $r['tugilgan_sana'],
                'lavozim' => $r['lavozim'],
                'lavozimRu' => $r['lavozim_ru'],
                'bolinma' => $r['bolinma'],
                'bolinmaRu' => $r['bolinma_ru'],
                'telefon' => $r['telefon'],
                'rol' => $r['rol'],
                'locked' => $locked,
                'lockedUntil' => $locked ? $lockedUntil : null,
            ];
        }, $rows);

        Response::success(['users' => $users]);
    }

    public static function editEmployee(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $familiya = Validate::requiredStr($input, 'familiya', 150);
        $ism = Validate::requiredStr($input, 'ism', 150);
        $otasi = Validate::str($input, 'otasi', 150);
        $tugilganSana = self::normalizeDate(Validate::str($input, 'tugilganSana', 10));
        $lavozim = Validate::str($input, 'lavozim', 200);
        $lavozimRu = Validate::str($input, 'lavozimRu', 200);
        $bolinma = Validate::str($input, 'bolinma', 200);
        $bolinmaRu = Validate::str($input, 'bolinmaRu', 200);
        $telefon = Validate::str($input, 'telefon', 20);
        $rol = Validate::str($input, 'rol', 20);
        $parol = Validate::str($input, 'parol', 255);

        if (!in_array($rol, ['user', 'admin', 'gl-admin'], true)) {
            $rol = 'user';
        }
        if ($parol !== '' && !Validate::isStrongPassword($parol)) {
            Response::error(Validate::WEAK_PASSWORD_MESSAGE, 'WEAK_PASSWORD', 422);
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);

        $existingStmt = $db->prepare('SELECT rol FROM users WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            Response::error('Xodim topilmadi', 'NOT_FOUND', 404);
        }

        if ($existing['rol'] === 'gl-admin' && $rol !== 'gl-admin' && self::glAdminCount($db) <= 1) {
            Response::error("Tizimda kamida bitta bosh administrator (gl-admin) qolishi shart", 'LAST_GL_ADMIN', 409);
        }

        $params = [
            'id' => $id,
            'familiya' => $familiya,
            'ism' => $ism,
            'otasi' => $otasi !== '' ? $otasi : null,
            'tugilgan_sana' => $tugilganSana,
            'lavozim' => $lavozim !== '' ? $lavozim : null,
            'lavozim_ru' => $lavozimRu !== '' ? $lavozimRu : null,
            'bolinma' => $bolinma !== '' ? $bolinma : null,
            'bolinma_ru' => $bolinmaRu !== '' ? $bolinmaRu : null,
            'telefon' => $telefon !== '' ? $telefon : null,
            'rol' => $rol,
        ];

        $setSql = 'familiya = :familiya, ism = :ism, otasining_ismi = :otasi,
                    tugilgan_sana = :tugilgan_sana,
                    lavozim = :lavozim, lavozim_ru = :lavozim_ru,
                    bolinma = :bolinma, bolinma_ru = :bolinma_ru,
                    telefon = :telefon, rol = :rol';

        if ($parol !== '') {
            $setSql .= ', password_hash = :hash';
            $params['hash'] = Auth::hashPassword($parol);
        }

        $stmt = $db->prepare("UPDATE users SET {$setSql} WHERE id = :id");
        $stmt->execute($params);

        // Parol qayta o'rnatilgan bo'lsa, shu xodimning barcha faol sessiyalarini bekor qilamiz.
        if ($parol !== '') {
            $revoke = $db->prepare('DELETE FROM sessions WHERE user_id = :id');
            $revoke->execute(['id' => $id]);
        }

        Response::success();
    }

    public static function deleteEmployee(array $input): void
    {
        $me = Auth::requireRole($input, ['gl-admin']);

        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }
        if ($id === (int) $me['id']) {
            Response::error("O'zingizni o'chira olmaysiz", 'CANNOT_DELETE_SELF', 409);
        }

        $db = Database::connection();
        $existingStmt = $db->prepare('SELECT rol FROM users WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            Response::success();
            return;
        }

        if ($existing['rol'] === 'gl-admin' && self::glAdminCount($db) <= 1) {
            Response::error("Tizimda kamida bitta bosh administrator (gl-admin) qolishi shart", 'LAST_GL_ADMIN', 409);
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success();
    }

    /**
     * Login urinishlari ko'p noto'g'ri bo'lgani uchun avtomatik bloklangan
     * xodimni gl-admin/admin darhol (15 daqiqa kutmasdan) blokdan chiqaradi.
     */
    public static function unlockLogin(array $input): void
    {
        Auth::requireRole($input, ['gl-admin', 'admin']);

        $login = Validate::requiredStr($input, 'login', 100);
        Auth::resetAttempts($login);

        Response::success();
    }

    /**
     * `<input type="date">`dan keladigan "YYYY-MM-DD" qatorini tekshiradi;
     * noto'g'ri yoki bo'sh bo'lsa null qaytaradi (maydon ixtiyoriy).
     */
    private static function normalizeDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $parts = explode('-', $value);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return null;
        }
        return $value;
    }

    private static function glAdminCount(\PDO $db): int
    {
        return (int) $db->query("SELECT COUNT(*) FROM users WHERE rol = 'gl-admin'")->fetchColumn();
    }

    public static function stats(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        $employeeRows = $db->query(
            "SELECT u.*, (SELECT MAX(read_at) FROM doc_reads d WHERE d.user_id = u.id) AS last_doc_read
             FROM users u ORDER BY u.familiya ASC, u.ism ASC"
        )->fetchAll();

        $attemptRows = $db->query(
            'SELECT * FROM test_attempts ORDER BY user_id ASC, attempted_at DESC'
        )->fetchAll();
        $latestAttempt = [];
        foreach ($attemptRows as $a) {
            $uid = (int) $a['user_id'];
            if (!isset($latestAttempt[$uid])) {
                $latestAttempt[$uid] = $a;
            }
        }

        $employees = [];
        $docsDone = 0;
        $testsPassed = 0;
        $testsFailed = 0;

        foreach ($employeeRows as $u) {
            $uid = (int) $u['id'];
            $hujjatSana = $u['last_doc_read'] ? date('Y-m-d', strtotime((string) $u['last_doc_read'])) : null;
            if ($hujjatSana) {
                $docsDone++;
            }

            $attempt = $latestAttempt[$uid] ?? null;
            $testTaken = $attempt !== null;
            if ($testTaken) {
                if ((bool) $attempt['passed']) {
                    $testsPassed++;
                } else {
                    $testsFailed++;
                }
            }

            $employees[] = [
                'fish' => Util::fullName($u),
                'lavozim' => $u['lavozim'],
                'bolinma' => $u['bolinma'],
                'telefon' => $u['telefon'],
                'hujjatSana' => $hujjatSana,
                'testTaken' => $testTaken,
                'testPoints' => $testTaken ? (int) $attempt['points'] : null,
                'testPercent' => $testTaken ? (int) $attempt['percent'] : null,
                'passed' => $testTaken ? (bool) $attempt['passed'] : false,
            ];
        }

        $total = count($employeeRows);

        Response::success([
            'summary' => [
                'total' => $total,
                'docsDone' => $docsDone,
                'testsPassed' => $testsPassed,
                'testsFailed' => $testsFailed,
                'notStarted' => $total - $testsPassed - $testsFailed,
            ],
            'employees' => $employees,
        ]);
    }
}
