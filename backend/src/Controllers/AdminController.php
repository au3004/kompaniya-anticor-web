<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\PwnedPasswords;
use App\Response;
use App\Roles;
use App\Util;
use App\Validate;
use PDOException;

final class AdminController
{
    private const TUGILGAN_SANA_DDL = "ALTER TABLE users ADD COLUMN IF NOT EXISTS tugilgan_sana DATE AFTER otasining_ismi";

    /**
     * VAQTINCHALIK BOOTSTRAP: bazada hali super-admin bo'lmagan paytda,
     * anticor-adminga birinchi super-adminni (odatda o'ziga alohida hisob
     * sifatida) tayinlash imkonini beradi — aks holda buni hech kim qila
     * olmas edi (faqat super-adminning o'zi bu rolni bera oladi, lekin
     * hali birortasi yo'q). Foydalanuvchi birinchi super-adminni
     * tayinlagach, BU QATORNI olib tashlab, pastdagi Auth::requireRole
     * chaqiruvini shunchaki Roles::HR_MANAGE'ga qaytarish so'ralgan edi.
     */
    private const ADD_EMPLOYEE_ROLES = [Roles::HR_ADMIN, Roles::SUPER_ADMIN, Roles::ANTICOR_ADMIN];

    public static function addEmployee(array $input): void
    {
        $me = Auth::requireRole($input, self::ADD_EMPLOYEE_ROLES);

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
        // Xodimlar bo'limi (hr-tomon) VA xabarnoma qabul qiluvchini tanlash
        // (anticor-tomon ham xabarnoma yubora oladi) — shu bois barcha
        // boshqaruv panelidagi rollarga ochiq, faqat aniq bir xodim yozuvini
        // TAHRIRLASH huquqi bundan alohida (pastdagi editEmployee'da) tekshiriladi.
        Auth::requireRole($input, Roles::ANY_PANEL_ACCESS);

        $db = Database::connection();
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        $stmt = $db->prepare(
            'SELECT u.id, u.login, u.familiya, u.ism, u.otasining_ismi, u.tugilgan_sana, u.lavozim, u.lavozim_ru,
                    u.bolinma, u.bolinma_ru, u.telefon, u.rol, la.locked_until
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
                'telefon' => $r['telefon'],
                'rol' => $r['rol'],
                'locked' => $locked,
                'lockedUntil' => $locked ? $lockedUntil : null,
            ];
        }, $rows);

        // Frontend'dagi rol tanlash ro'yxati (populateRoleSelect) anticor-admin
        // uchun to'liq/qisqartirilgan variantni shu bayroqqa qarab tanlaydi —
        // backend'dagi allowedRolesFor() bilan bir xil bootstrap mantig'i.
        Response::success(['users' => $users, 'superAdminExists' => self::superAdminExists($db)]);
    }

    public static function editEmployee(array $input): void
    {
        $me = Auth::requireRole($input, Roles::HR_MANAGE);

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

        $existingStmt = $db->prepare('SELECT id, rol FROM users WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            Response::error('Xodim topilmadi', 'NOT_FOUND', 404);
        }

        self::assertCanEditTarget($me, $existing);
        $rol = self::sanitizeEditedRole($db, $me, $existing, $rol);

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

    /** hr-admin faqat past darajali rollarni (oddiy xodim, anticor, hr, rahbariyat) tayinlashi mumkin. */
    private const HR_ASSIGNABLE_ROLES = [Roles::USER, Roles::ANTICOR, Roles::HR, Roles::RAHBARIYAT];

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
     * tayinlashi mumkinligini aniqlaydi. super-admin — istalgan rolni,
     * jumladan super-admin'ni ham beradi. anticor-admin FAQAT tizimda
     * hali birorta ham super-admin yo'q bo'lgan (vaqtinchalik bootstrap)
     * paytda xuddi shunday to'liq huquqqa ega bo'ladi — birinchi
     * super-admin tayinlangach, bu maxsus huquq avtomatik yopiladi va
     * anticor-admin ham hr-admin kabi faqat past darajali rollarni
     * beradi. Aks holda anticor-admin cheksiz muddat o'zini yoki
     * boshqa birortasini anticor-admin/hr-admin/super-admin qilib
     * tayinlab, begona boshqaruv panellariga kirish huquqini "sotib
     * olishi" mumkin bo'lardi.
     */
    private static function allowedRolesFor(\PDO $db, string $callerRol): array
    {
        if ($callerRol === Roles::SUPER_ADMIN) {
            return array_merge(Roles::ASSIGNABLE, [Roles::SUPER_ADMIN]);
        }
        if ($callerRol === Roles::ANTICOR_ADMIN && !self::superAdminExists($db)) {
            return array_merge(Roles::ASSIGNABLE, [Roles::SUPER_ADMIN]);
        }
        return self::HR_ASSIGNABLE_ROLES;
    }

    /**
     * super-admin rolini faqat hozirgi super-adminning o'zi (yoki hali
     * hech kim super-admin bo'lmagan paytda, vaqtinchalik bootstrap sifatida
     * anticor-admin) bera oladi.
     */
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
        $allowed = self::allowedRolesFor($db, $me['rol']);
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
     * hr-admin o'zidan yuqori yoki teng darajadagi xodimni (anticor-admin/
     * hr-admin/super-admin) tahrirlay olmaydi — bu boshqaruv paneli orqali
     * yuqori huquqli hisoblarni tasodifan yoki niyat bilan "egallab olish"
     * dan himoya qiladi. super-admin uchun cheklov yo'q.
     */
    private static function assertCanEditTarget(array $me, array $existing): void
    {
        if ($me['rol'] === Roles::SUPER_ADMIN) {
            return;
        }
        if (!in_array($existing['rol'], self::HR_ASSIGNABLE_ROLES, true)) {
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
        $allowed = self::allowedRolesFor($db, $me['rol']);
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

    /** hr-admin faqat past darajali xodimlarni o'chira oladi; super-adminni bu yerdan o'chirib bo'lmaydi. */
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
        if (!in_array($existing['rol'], self::HR_ASSIGNABLE_ROLES, true)) {
            Response::error("Sizda bu xodimni o'chirish huquqi yo'q", 'FORBIDDEN', 403);
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

    /**
     * Server tomonida ushlangan xatoliklar (backend/public/index.php'ning
     * umumiy catch bloki) ro'yxati — serverning fayl tizimiga kirmasdan
     * admin panelidan so'nggi xatoliklarni ko'rish imkonini beradi.
     */
    public static function getErrorLog(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_VIEW);

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
