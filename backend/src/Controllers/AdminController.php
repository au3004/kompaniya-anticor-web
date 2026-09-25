<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\Filials;
use App\PwnedPasswords;
use App\Response;
use App\Roles;
use App\Util;
use App\Validate;
use PDOException;

final class AdminController
{
    private const TUGILGAN_SANA_DDL = "ALTER TABLE users ADD COLUMN IF NOT EXISTS tugilgan_sana DATE AFTER otasining_ismi";
    private const FILIAL_DDL = "ALTER TABLE users ADD COLUMN IF NOT EXISTS filial VARCHAR(50) AFTER bolinma_ru";

    public static function addEmployee(array $input): void
    {
        $me = Auth::requireRole($input, Roles::HR_MANAGE);

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
        $filial = Validate::str($input, 'filial', 50);
        $telefon = Validate::str($input, 'telefon', 20);
        $rol = Validate::str($input, 'rol', 20);

        if ($filial !== '' && !Filials::isValid($filial)) {
            Response::error("Noto'g'ri filial tanlandi", 'VALIDATION_ERROR', 422);
        }
        if (!Validate::isStrongPassword($parol)) {
            Response::error(Validate::WEAK_PASSWORD_MESSAGE, 'WEAK_PASSWORD', 422);
        }
        if (PwnedPasswords::isBreached($parol)) {
            Response::error(
                "Bu parol avval ma'lumotlar sizib chiqishlarida uchragan. Iltimos boshqa parol tanlang.",
                'PWNED_PASSWORD',
                422
            );
        }

        $db = Database::connection();
        $rol = self::sanitizeAssignedRole($db, $me, $rol, null);
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        Util::ensureSchema($db, self::FILIAL_DDL);
        self::assertLoginAvailable($db, $login);

        $payload = [
            'login' => $login,
            'password_hash' => Auth::hashPassword($parol),
            'familiya' => $familiya,
            'ism' => $ism,
            'otasi' => $otasi !== '' ? $otasi : null,
            'tugilgan_sana' => $tugilganSana,
            'lavozim' => $lavozim !== '' ? $lavozim : null,
            'lavozim_ru' => $lavozimRu !== '' ? $lavozimRu : null,
            'bolinma' => $bolinma !== '' ? $bolinma : null,
            'bolinma_ru' => $bolinmaRu !== '' ? $bolinmaRu : null,
            'filial' => $filial !== '' ? $filial : null,
            'telefon' => $telefon !== '' ? $telefon : null,
            'rol' => $rol,
        ];

        $id = self::insertUser($db, $payload);
        Response::success(['id' => $id]);
    }

    public static function usersList(array $input): void
    {
        // Xodimlar bo'limi VA xabarnoma qabul qiluvchini tanlash (anticor-tomon
        // ham xabarnoma yubora oladi) — shu bois barcha boshqaruv panelidagi
        // rollarga ochiq, faqat aniq bir xodim yozuvini TAHRIRLASH huquqi
        // bundan alohida (pastdagi editEmployee'da) tekshiriladi.
        Auth::requireRole($input, Roles::ANY_PANEL_ACCESS);

        $db = Database::connection();
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        Util::ensureSchema($db, self::FILIAL_DDL);
        $stmt = $db->prepare(
            'SELECT u.id, u.login, u.familiya, u.ism, u.otasining_ismi, u.tugilgan_sana, u.lavozim, u.lavozim_ru,
                    u.bolinma, u.bolinma_ru, u.filial, u.telefon, u.rol, la.locked_until
             FROM users u
             LEFT JOIN login_attempts la ON la.login = u.login
             WHERE u.rol != :superAdmin
             ORDER BY u.id ASC'
        );
        // super-admin hech qaysi ro'yxat/hisobotda ko'rinmasligi shart — go'yo
        // bunday profil umuman yo'qdek (loyiha davomida amal qiladigan qoida).
        $stmt->execute(['superAdmin' => Roles::SUPER_ADMIN]);
        $rows = $stmt->fetchAll();

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
                'filial' => $r['filial'],
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
        $me = Auth::requireRole($input, Roles::HR_EDIT);

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
        $filial = Validate::str($input, 'filial', 50);
        $telefon = Validate::str($input, 'telefon', 20);
        $rol = Validate::str($input, 'rol', 20);
        $parol = Validate::str($input, 'parol', 255);

        if ($filial !== '' && !Filials::isValid($filial)) {
            Response::error("Noto'g'ri filial tanlandi", 'VALIDATION_ERROR', 422);
        }
        if ($parol !== '' && !Validate::isStrongPassword($parol)) {
            Response::error(Validate::WEAK_PASSWORD_MESSAGE, 'WEAK_PASSWORD', 422);
        }
        if ($parol !== '' && PwnedPasswords::isBreached($parol)) {
            Response::error(
                "Bu parol avval ma'lumotlar sizib chiqishlarida uchragan. Iltimos boshqa parol tanlang.",
                'PWNED_PASSWORD',
                422
            );
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        Util::ensureSchema($db, self::FILIAL_DDL);

        $existingStmt = $db->prepare('SELECT id, rol FROM users WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            Response::error('Xodim topilmadi', 'NOT_FOUND', 404);
        }

        self::assertCanEditTarget($me, $existing);
        $rol = self::sanitizeEditedRole($db, $me, $existing, $rol);

        $payload = [
            'familiya' => $familiya,
            'ism' => $ism,
            'otasi' => $otasi !== '' ? $otasi : null,
            'tugilgan_sana' => $tugilganSana,
            'lavozim' => $lavozim !== '' ? $lavozim : null,
            'lavozim_ru' => $lavozimRu !== '' ? $lavozimRu : null,
            'bolinma' => $bolinma !== '' ? $bolinma : null,
            'bolinma_ru' => $bolinmaRu !== '' ? $bolinmaRu : null,
            'filial' => $filial !== '' ? $filial : null,
            'telefon' => $telefon !== '' ? $telefon : null,
            'rol' => $rol,
        ];
        if ($parol !== '') {
            $payload['password_hash'] = Auth::hashPassword($parol);
        }

        self::applyEditPayload($db, $id, $payload);
        Response::success();
    }

    public static function deleteEmployee(array $input): void
    {
        $me = Auth::requireRole($input, Roles::HR_MANAGE);

        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }
        if ($id === (int) $me['id']) {
            Response::error("O'zingizni o'chira olmaysiz", 'CANNOT_DELETE_SELF', 409);
        }

        $db = Database::connection();
        $existingStmt = $db->prepare('SELECT id, rol FROM users WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            Response::success();
            return;
        }

        self::assertCanDeleteTarget($me, $existing);

        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success();
    }

    /**
     * Login urinishlari ko'p noto'g'ri bo'lgani uchun avtomatik bloklangan
     * xodimni darhol (15 daqiqa kutmasdan) blokdan chiqaradi.
     */
    public static function unlockLogin(array $input): void
    {
        Auth::requireRole($input, Roles::HR_MANAGE);

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

    /**
     * anticor-admin (super-admin bo'lmagan) faqat shu (past darajali)
     * rollardagi MAVJUD xodimlarni tahrira/o'chira oladi — boshqa
     * anticor-admin/super-admin darajasidagi xodim yozuviga tegilmaydi
     * (peer-darajadagi hisoblarni tasodifan/niyat bilan egallab olishdan
     * himoya — rolni o'zgartirish huquqidan mustaqil cheklov, qarang:
     * allowedRolesFor()).
     */
    private const EDITABLE_TARGET_ROLES = [Roles::USER, Roles::ANTICOR];

    /**
     * Tizimda hozir kamida bitta super-admin bor-yo'qligini tekshiradi —
     * bootstrap oynasi (birinchi super-adminni tayinlash imkoniyati) hali
     * ochiq yoki yopilganini aniqlash uchun ishlatiladi.
     */
    private static function superAdminExists(\PDO $db): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE rol = :rol');
        $stmt->execute(['rol' => Roles::SUPER_ADMIN]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Chaqiruvchining rolidan kelib chiqib, u xodimga qaysi rollarni
     * tayinlashi mumkinligini aniqlaydi (bu yerga faqat addEmployee/
     * editEmployee'ning rol-o'zgartirish yo'li orqali, ya'ni anticor-admin
     * yoki super-admin kelishi mumkin). super-admin istalgan rolni, jumladan
     * super-admin'ning o'zini ham beradi (rolni topshirish/transfer).
     * anticor-admin esa USER/ANTICOR/ANTICOR_ADMIN rollaridan birini beradi.
     */
    private static function allowedRolesFor(string $callerRol): array
    {
        if ($callerRol === Roles::SUPER_ADMIN) {
            return array_merge(Roles::ASSIGNABLE, [Roles::SUPER_ADMIN]);
        }
        return Roles::ASSIGNABLE;
    }

    /** super-admin rolini faqat hozirgi super-adminning o'zi boshqa xodimga bera oladi. */
    private static function assertCanGrantSuperAdmin(\PDO $db, string $callerRol): void
    {
        if ($callerRol === Roles::SUPER_ADMIN) {
            return;
        }
        if (self::superAdminExists($db)) {
            Response::error("Faqat super-adminning o'zi bu rolni boshqa xodimga bera oladi", 'FORBIDDEN', 403);
        }
    }

    /**
     * Yangi super-admin tayinlanganda, tizimda FAQAT bitta super-admin
     * qolishi uchun avvalgisi (agar bo'lsa) avtomatik anticor-admin
     * roliga tushiriladi — bu "rolni topshirish" (transfer) mexanizmi.
     */
    private static function demoteExistingSuperAdmins(\PDO $db, int $exceptId): void
    {
        $stmt = $db->prepare('UPDATE users SET rol = :fallback WHERE rol = :super AND id != :exceptId');
        $stmt->execute(['fallback' => Roles::ANTICOR_ADMIN, 'super' => Roles::SUPER_ADMIN, 'exceptId' => $exceptId]);
    }

    /** addEmployee uchun: ruxsat etilmagan rol yuborilsa, xavfsiz standart holatga ("user") tushiriladi. */
    private static function sanitizeAssignedRole(\PDO $db, array $me, string $rol, ?string $unused = null): string
    {
        $allowed = self::allowedRolesFor($me['rol']);
        if (!in_array($rol, $allowed, true)) {
            return Roles::USER;
        }
        if ($rol === Roles::SUPER_ADMIN) {
            self::assertCanGrantSuperAdmin($db, $me['rol']);
            self::demoteExistingSuperAdmins($db, 0);
        }
        return $rol;
    }

    /**
     * anticor-admin (super-admin bo'lmagan) o'zidan yuqori yoki teng
     * darajadagi xodimni (boshqa anticor-admin/super-admin) tahrirlay
     * olmaydi — bu boshqaruv paneli orqali yuqori huquqli hisoblarni
     * tasodifan yoki niyat bilan "egallab olish"dan himoya qiladi.
     * super-admin uchun cheklov yo'q.
     */
    private static function assertCanEditTarget(array $me, array $existing): void
    {
        if ($me['rol'] === Roles::SUPER_ADMIN) {
            return;
        }
        if (!in_array($existing['rol'], self::EDITABLE_TARGET_ROLES, true)) {
            Response::error("Sizda bu xodimni tahrirlash huquqi yo'q", 'FORBIDDEN', 403);
        }
    }

    /**
     * editEmployee uchun rolni tekshiradi: o'zgarmagan bo'lsa erkin, aks
     * holda chaqiruvchining huquqi yetarli ekanini va super-admin'ning
     * o'zini-o'zi (vorissiz) tushirib qo'ymasligini ta'minlaydi.
     */
    private static function sanitizeEditedRole(\PDO $db, array $me, array $existing, string $rol): string
    {
        if ($rol === $existing['rol']) {
            return $rol;
        }
        $allowed = self::allowedRolesFor($me['rol']);
        if (!in_array($rol, $allowed, true)) {
            Response::error('Bu rolni tayinlashga sizda huquq yo\'q', 'FORBIDDEN', 403);
        }
        if ($rol === Roles::SUPER_ADMIN) {
            self::assertCanGrantSuperAdmin($db, $me['rol']);
            self::demoteExistingSuperAdmins($db, (int) $existing['id']);
        }
        if ($existing['rol'] === Roles::SUPER_ADMIN) {
            Response::error(
                "Avval boshqa xodimga super-admin rolini bering — shunda sizniki avtomatik almashadi",
                'CANNOT_SELF_DEMOTE',
                409
            );
        }
        return $rol;
    }

    /** anticor-admin faqat past darajali xodimlarni o'chira oladi; super-adminni bu yerdan o'chirib bo'lmaydi. */
    private static function assertCanDeleteTarget(array $me, array $existing): void
    {
        if ($existing['rol'] === Roles::SUPER_ADMIN) {
            Response::error(
                "Super-adminni bu yerdan o'chirib bo'lmaydi — avval rolini boshqa xodimga o'tkazing",
                'FORBIDDEN',
                403
            );
        }
        if ($me['rol'] === Roles::SUPER_ADMIN) {
            return;
        }
        if (!in_array($existing['rol'], self::EDITABLE_TARGET_ROLES, true)) {
            Response::error("Sizda bu xodimni o'chirish huquqi yo'q", 'FORBIDDEN', 403);
        }
    }

    /** addEmployee uchun: login band emasligini tekshiradi. */
    private static function assertLoginAvailable(\PDO $db, string $login): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE login = :login');
        $stmt->execute(['login' => $login]);
        if ((int) $stmt->fetchColumn() > 0) {
            Response::error('Bu login band', 'LOGIN_TAKEN', 409);
        }
    }

    /** Yangi xodim yozuvini bazaga yozadi (LOGIN_TAKEN'ni ham shu yerda ushlaydi). */
    private static function insertUser(\PDO $db, array $payload): int
    {
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        Util::ensureSchema($db, self::FILIAL_DDL);
        $stmt = $db->prepare(
            'INSERT INTO users (login, password_hash, familiya, ism, otasining_ismi, tugilgan_sana, lavozim, lavozim_ru, bolinma, bolinma_ru, filial, telefon, rol)
             VALUES (:login, :password_hash, :familiya, :ism, :otasi, :tugilgan_sana, :lavozim, :lavozim_ru, :bolinma, :bolinma_ru, :filial, :telefon, :rol)'
        );
        try {
            $stmt->execute($payload);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                Response::error('Bu login band', 'LOGIN_TAKEN', 409);
            }
            throw $e;
        }
        return (int) $db->lastInsertId();
    }

    /** Mavjud xodim yozuvini payload asosida yangilaydi (parol o'zgargan bo'lsa, sessiyalarini ham bekor qiladi). */
    private static function applyEditPayload(\PDO $db, int $id, array $payload): void
    {
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        Util::ensureSchema($db, self::FILIAL_DDL);
        $setSql = 'familiya = :familiya, ism = :ism, otasining_ismi = :otasi,
                    tugilgan_sana = :tugilgan_sana,
                    lavozim = :lavozim, lavozim_ru = :lavozim_ru,
                    bolinma = :bolinma, bolinma_ru = :bolinma_ru,
                    filial = :filial,
                    telefon = :telefon, rol = :rol';
        $hasPassword = isset($payload['password_hash']);
        if ($hasPassword) {
            $setSql .= ', password_hash = :password_hash';
        }

        $params = $payload;
        $params['id'] = $id;
        $stmt = $db->prepare("UPDATE users SET {$setSql} WHERE id = :id");
        $stmt->execute($params);

        if ($hasPassword) {
            $revoke = $db->prepare('DELETE FROM sessions WHERE user_id = :id');
            $revoke->execute(['id' => $id]);
        }
    }

    public static function stats(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_VIEW);

        $db = Database::connection();
        $employeeStmt = $db->prepare(
            "SELECT u.*, (SELECT MAX(read_at) FROM doc_reads d WHERE d.user_id = u.id) AS last_doc_read
             FROM users u WHERE u.rol != :superAdmin ORDER BY u.familiya ASC, u.ism ASC"
        );
        // super-admin hech qaysi hisobot/statistikada ko'rinmasligi shart.
        $employeeStmt->execute(['superAdmin' => Roles::SUPER_ADMIN]);
        $employeeRows = $employeeStmt->fetchAll();

        $attemptRows = $db->query(
            'SELECT * FROM test_attempts ORDER BY user_id ASC, attempted_at DESC'
        )->fetchAll();
        // Xodim testni cheklangan marta topshiradi — hisobga eng yuqori natijali
        // urinish olinadi (teng bo'lsa — keyingisi, chunki qatorlar sana bo'yicha kamayib boradi).
        $bestAttempt = [];
        $attemptCount = [];
        $everPassed = [];
        foreach ($attemptRows as $a) {
            $uid = (int) $a['user_id'];
            $attemptCount[$uid] = ($attemptCount[$uid] ?? 0) + 1;
            if (!isset($bestAttempt[$uid]) || (int) $a['percent'] > (int) $bestAttempt[$uid]['percent']) {
                $bestAttempt[$uid] = $a;
            }
            if ((bool) $a['passed']) {
                $everPassed[$uid] = true;
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

            $attempt = $bestAttempt[$uid] ?? null;
            $testTaken = $attempt !== null;
            if ($testTaken) {
                if ((bool) $attempt['passed']) {
                    $testsPassed++;
                } else {
                    $testsFailed++;
                }
            }

            $employees[] = [
                'id' => $uid,
                'fish' => Util::fullName($u),
                'lavozim' => $u['lavozim'],
                'bolinma' => $u['bolinma'],
                'telefon' => $u['telefon'],
                'hujjatSana' => $hujjatSana,
                'testTaken' => $testTaken,
                'testPoints' => $testTaken ? (int) $attempt['points'] : null,
                'testPercent' => $testTaken ? (int) $attempt['percent'] : null,
                'passed' => $testTaken ? (bool) $attempt['passed'] : false,
                'testAttempts' => $attemptCount[$uid] ?? 0,
                'hasCertificate' => isset($everPassed[$uid]),
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
                'maxAttempts' => max(1, Config::int('TEST_MAX_ATTEMPTS', 2)),
            ],
            'employees' => $employees,
        ]);
    }

    /**
     * Server tomonida ushlangan xatoliklar (backend/public/index.php'ning
     * umumiy catch bloki) ro'yxati — serverning fayl tizimiga kirmasdan
     * admin panelidan so'nggi xatoliklarni ko'rish imkonini beradi.
     */
    public static function getErrorLog(array $input): void
    {
        // Xom (server ichki) xatolik matnlarini o'z ichiga olgani uchun
        // ANTICOR_VIEW ("anticor" — faqat ko'rish/operatsion rol ham kiradi)
        // emas, faqat boshqaruv darajasidagi ANTICOR_MANAGE ko'ra oladi.
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);

        $db = Database::connection();
        Util::ensureSchema($db, "CREATE TABLE IF NOT EXISTS error_log (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            action      VARCHAR(100),
            message     TEXT NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB");

        $rows = $db->query(
            'SELECT id, action, message, created_at FROM error_log ORDER BY id DESC LIMIT 200'
        )->fetchAll();

        $list = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'action' => $r['action'],
            'message' => $r['message'],
            'sana' => date('d.m.Y G:i:s', strtotime((string) $r['created_at'])),
        ], $rows);

        Response::success(['entries' => $list]);
    }
}
