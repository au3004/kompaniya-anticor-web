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
            'telefon' => $telefon !== '' ? $telefon : null,
            'rol' => $rol,
        ];

        if (self::needsApproval($me['rol'], $rol, null)) {
            $requestId = self::createPendingRequest($db, 'add', null, (int) $me['id'], $payload);
            Response::success(['pending' => true, 'requestId' => $requestId]);
            return;
        }

        $id = self::insertUser($db, $payload);
        Response::success(['id' => $id]);
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
        if ($me['rol'] === Roles::HR) {
            // "hr" xodim ma'lumotlarini (F.I.Sh, telefon va h.k.) tahrirlay oladi,
            // lekin rolni o'zgartira olmaydi — yuborilgan qiymatdan qat'i nazar,
            // mavjud rol saqlanib qoladi.
            $rol = $existing['rol'];
        } else {
            $rol = self::sanitizeEditedRole($db, $me, $existing, $rol);
        }

        $payload = [
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
        if ($parol !== '') {
            $payload['password_hash'] = Auth::hashPassword($parol);
        }

        if (self::needsApproval($me['rol'], $rol, $existing['rol'])) {
            $requestId = self::createPendingRequest($db, 'edit', $id, (int) $me['id'], $payload);
            Response::success(['pending' => true, 'requestId' => $requestId]);
            return;
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
     * hr-admin/hr faqat shu (past darajali) rollardagi MAVJUD xodimlarni
     * tahrira/o'chira oladi — anticor-admin/hr-admin/super-admin darajasidagi
     * xodim yozuviga tegilmaydi (rolni o'zgartirish huquqidan mustaqil
     * cheklov — qarang: allowedRolesFor()).
     */
    private const EDITABLE_TARGET_ROLES = [Roles::USER, Roles::ANTICOR, Roles::HR, Roles::RAHBARIYAT];

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
     * editEmployee'ning rol-o'zgartirish yo'li orqali, ya'ni hr-admin
     * yoki super-admin kelishi mumkin — "hr" rolni umuman o'zgartirmaydi,
     * qarang: editEmployee()). super-admin istalgan rolni, jumladan
     * super-admin'ning o'zini ham beradi (rolni topshirish/transfer).
     * hr-admin esa super-admin'dan boshqa BARCHA rolni beradi — bu keng
     * huquq ataylab shunday: yuqori (anticor-admin/hr-admin) rol berilishi
     * alohida kelishuv (approval) jarayoni bilan nazorat qilinadi.
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
     * hr-admin/hr o'zidan yuqori yoki teng darajadagi xodimni (anticor-admin/
     * hr-admin/super-admin) tahrirlay olmaydi — bu boshqaruv paneli orqali
     * yuqori huquqli hisoblarni tasodifan yoki niyat bilan "egallab olish"
     * dan himoya qiladi. super-admin uchun cheklov yo'q.
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
        if (!in_array($existing['rol'], self::EDITABLE_TARGET_ROLES, true)) {
            Response::error("Sizda bu xodimni o'chirish huquqi yo'q", 'FORBIDDEN', 403);
        }
    }

    /* ================================================================
     * KELISHUV (APPROVAL) JARAYONI
     *
     * hr-admin xodimga "user"dan boshqa rol berayotganda (yangi xodim
     * qo'shishda yoki mavjud xodimning rolini o'zgartirishda), o'zgarish
     * DARHOL saqlanmaydi — to'liq so'rov (barcha maydon + so'ralgan rol)
     * `employee_pending_requests`da "pending" holatda kutib turadi.
     * Kvorum: kamida bitta rahbariyat VA kamida bitta anticor-admin
     * "tasdiqlash" bosishi shart (ikki mustaqil nazorat nuqtasi) —
     * ulardan BIRI "rad etish" bossa, so'rov darhol yakuniy rad etiladi.
     * super-admin yakka o'zi (kvorumdan tashqari) darhol tasdiqlashi
     * yoki rad etishi mumkin — bu yakuniy zaxira (override) mexanizmi.
     * ================================================================ */

    private const PENDING_REQUEST_DDL = "CREATE TABLE IF NOT EXISTS employee_pending_requests (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        request_type    ENUM('add','edit') NOT NULL,
        target_user_id  INT NULL,
        requested_by    INT NOT NULL,
        payload         TEXT NOT NULL,
        status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        decided_at      DATETIME NULL,
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status)
    ) ENGINE=InnoDB";

    private const PENDING_APPROVAL_DDL = "CREATE TABLE IF NOT EXISTS employee_pending_approvals (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        request_id    INT NOT NULL,
        approver_id   INT NOT NULL,
        approver_rol  VARCHAR(20) NOT NULL,
        decision      ENUM('approved','rejected') NOT NULL,
        izoh          TEXT NULL,
        decided_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES employee_pending_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_request_approver (request_id, approver_id)
    ) ENGINE=InnoDB";

    private static function ensurePendingSchema(\PDO $db): void
    {
        Util::ensureSchema($db, self::PENDING_REQUEST_DDL);
        Util::ensureSchema($db, self::PENDING_APPROVAL_DDL);
    }

    /**
     * hr-admin uchun kelishuv talab qilinadimi — faqat hr-admin chaqirganda
     * (super-admin'ning o'zi ustidan hech kim yo'q) VA rol haqiqatan ham
     * "user"dan boshqa BIRON narsaga o'zgarayotganda (avvalgi bilan bir xil
     * bo'lsa yoki "user"ga tushirilayotgan bo'lsa — kelishuv shart emas).
     */
    private static function needsApproval(string $callerRol, string $newRol, ?string $previousRol): bool
    {
        if ($callerRol !== Roles::HR_ADMIN) {
            return false;
        }
        if ($newRol === Roles::USER) {
            return false;
        }
        if ($previousRol !== null && $previousRol === $newRol) {
            return false;
        }
        return true;
    }

    /**
     * addEmployee/kelishuv tasdiqlanganda: login band emasligini (real
     * jadval + boshqa kutilayotgan so'rovlar) tekshiradi. $excludeRequestId
     * — kelishuv tasdiqlanayotganda TEKShirilayotgan so'rovning O'ZINI
     * o'ziga "band" deb hisoblab qo'ymaslik uchun (u hali "pending" holatda
     * turibdi, chunki holat faqat qo'llash MUVAFFAQIYATLI bo'lgach yangilanadi).
     */
    private static function assertLoginAvailable(\PDO $db, string $login, ?int $excludeRequestId = null): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE login = :login');
        $stmt->execute(['login' => $login]);
        if ((int) $stmt->fetchColumn() > 0) {
            Response::error('Bu login band', 'LOGIN_TAKEN', 409);
        }

        self::ensurePendingSchema($db);
        $pendingStmt = $db->prepare(
            "SELECT id, payload FROM employee_pending_requests WHERE request_type = 'add' AND status = 'pending'"
        );
        $pendingStmt->execute();
        foreach ($pendingStmt->fetchAll() as $row) {
            if ($excludeRequestId !== null && (int) $row['id'] === $excludeRequestId) {
                continue;
            }
            $p = json_decode((string) $row['payload'], true);
            if (is_array($p) && ($p['login'] ?? null) === $login) {
                Response::error('Bu login uchun kelishuv so\'rovi allaqachon kutilmoqda', 'LOGIN_TAKEN', 409);
            }
        }
    }

    /** Yangi xodim yozuvini bazaga yozadi (LOGIN_TAKEN'ni ham shu yerda ushlaydi). */
    private static function insertUser(\PDO $db, array $payload): int
    {
        Util::ensureSchema($db, self::TUGILGAN_SANA_DDL);
        $stmt = $db->prepare(
            'INSERT INTO users (login, password_hash, familiya, ism, otasining_ismi, tugilgan_sana, lavozim, lavozim_ru, bolinma, bolinma_ru, telefon, rol)
             VALUES (:login, :password_hash, :familiya, :ism, :otasi, :tugilgan_sana, :lavozim, :lavozim_ru, :bolinma, :bolinma_ru, :telefon, :rol)'
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
        $setSql = 'familiya = :familiya, ism = :ism, otasining_ismi = :otasi,
                    tugilgan_sana = :tugilgan_sana,
                    lavozim = :lavozim, lavozim_ru = :lavozim_ru,
                    bolinma = :bolinma, bolinma_ru = :bolinma_ru,
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

    /** Yangi kelishuv so'rovini yaratadi va tegishli rollarga (rahbariyat + anticor-admin) xabarnoma yuboradi. */
    private static function createPendingRequest(\PDO $db, string $type, ?int $targetUserId, int $requestedBy, array $payload): int
    {
        self::ensurePendingSchema($db);

        $stmt = $db->prepare(
            'INSERT INTO employee_pending_requests (request_type, target_user_id, requested_by, payload)
             VALUES (:type, :target_user_id, :requested_by, :payload)'
        );
        $stmt->execute([
            'type' => $type,
            'target_user_id' => $targetUserId,
            'requested_by' => $requestedBy,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $requestId = (int) $db->lastInsertId();

        self::notifyApprovers($db, $requestId, $type, $requestedBy, $payload);

        return $requestId;
    }

    private static function notifyApprovers(\PDO $db, int $requestId, string $type, int $requestedBy, array $payload): void
    {
        $stmt = $db->prepare('SELECT login FROM users WHERE rol IN (:a, :r)');
        $stmt->execute(['a' => Roles::ANTICOR_ADMIN, 'r' => Roles::RAHBARIYAT]);
        $logins = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (!$logins) {
            return;
        }

        $fish = trim(($payload['familiya'] ?? '') . ' ' . ($payload['ism'] ?? ''));
        $roleLabel = (string) ($payload['rol'] ?? '');
        $text = $type === 'add'
            ? "Yangi xodimga \"{$roleLabel}\" roli berilishi tasdiqlanishini kutmoqda: {$fish}. Boshqaruv panelidagi \"Tasdiqlash so'rovlari\" bo'limidan ko'ring."
            : "Xodimga \"{$roleLabel}\" roli berilishi tasdiqlanishini kutmoqda: {$fish}. Boshqaruv panelidagi \"Tasdiqlash so'rovlari\" bo'limidan ko'ring.";

        $ins = $db->prepare(
            "INSERT INTO notifications (sender_id, matn, target_type, target_value) VALUES (:sender_id, :matn, 'users', :target_value)"
        );
        $ins->execute([
            'sender_id' => $requestedBy,
            'matn' => $text,
            'target_value' => mb_substr(implode(',', $logins), 0, 1000),
        ]);
    }

    /**
     * Kelishuv so'rovlari ro'yxati: hr-admin o'zi yuborgan so'rovlarning
     * holatini (kutilmoqda/tasdiqlangan/rad etilgan) kuzatadi;
     * rahbariyat/anticor-admin/super-admin esa hozir qaror kutayotgan
     * ("pending") barcha so'rovlarni, ularning joriy tasdiq holati bilan
     * birga ko'radi.
     */
    public static function getPendingEmployeeRequests(array $input): void
    {
        $me = Auth::requireUser($input);
        $isApprover = in_array($me['rol'], Roles::REQUEST_APPROVE, true);
        if (!$isApprover && $me['rol'] !== Roles::HR_ADMIN) {
            Response::error("Sizda bu amal uchun huquq yo'q", 'FORBIDDEN', 403);
        }

        $db = Database::connection();
        self::ensurePendingSchema($db);

        if ($isApprover) {
            $stmt = $db->prepare(
                "SELECT r.*, u.familiya AS req_familiya, u.ism AS req_ism, u.otasining_ismi AS req_otasi
                 FROM employee_pending_requests r
                 JOIN users u ON u.id = r.requested_by
                 WHERE r.status = 'pending'
                 ORDER BY r.created_at ASC"
            );
            $stmt->execute();
        } else {
            $stmt = $db->prepare(
                "SELECT r.*, u.familiya AS req_familiya, u.ism AS req_ism, u.otasining_ismi AS req_otasi
                 FROM employee_pending_requests r
                 JOIN users u ON u.id = r.requested_by
                 WHERE r.requested_by = :me
                 ORDER BY r.created_at DESC
                 LIMIT 100"
            );
            $stmt->execute(['me' => $me['id']]);
        }
        $rows = $stmt->fetchAll();

        $approvalsByRequest = [];
        if ($rows) {
            $ids = array_map(static fn (array $r) => (int) $r['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $apprStmt = $db->prepare(
                "SELECT a.request_id, a.decision, a.approver_rol, u.familiya, u.ism, u.otasining_ismi
                 FROM employee_pending_approvals a
                 JOIN users u ON u.id = a.approver_id
                 WHERE a.request_id IN ({$placeholders})"
            );
            $apprStmt->execute($ids);
            foreach ($apprStmt->fetchAll() as $a) {
                $approvalsByRequest[(int) $a['request_id']][] = [
                    'rol' => $a['approver_rol'],
                    'decision' => $a['decision'],
                    'fish' => Util::fullName($a),
                ];
            }
        }

        $list = array_map(static function (array $r) use ($approvalsByRequest) {
            $payload = json_decode((string) $r['payload'], true) ?: [];
            return [
                'id' => (int) $r['id'],
                'type' => $r['request_type'],
                'status' => $r['status'],
                'targetUserId' => $r['target_user_id'] !== null ? (int) $r['target_user_id'] : null,
                'requestedByFish' => Util::fullName(['familiya' => $r['req_familiya'], 'ism' => $r['req_ism'], 'otasining_ismi' => $r['req_otasi']]),
                'fish' => trim(($payload['familiya'] ?? '') . ' ' . ($payload['ism'] ?? '')),
                'rol' => $payload['rol'] ?? null,
                'lavozim' => $payload['lavozim'] ?? null,
                'bolinma' => $payload['bolinma'] ?? null,
                'telefon' => $payload['telefon'] ?? null,
                'sana' => date('d.m.Y G:i', strtotime((string) $r['created_at'])),
                'approvals' => $approvalsByRequest[(int) $r['id']] ?? [],
            ];
        }, $rows);

        Response::success(['requests' => $list]);
    }

    /**
     * Bitta kelishuv so'roviga ovoz beradi. Kvorum: kamida bitta rahbariyat
     * VA kamida bitta anticor-admin "tasdiqlash" bossa — so'rov qo'llaniladi
     * (xodim yaratiladi/yangilanadi). Ulardan BIRI "rad etish" bossa —
     * so'rov darhol (kvorumsiz) yakuniy rad etiladi. super-admin yakka o'zi
     * kvorumdan tashqari darhol yakuniy qaror bera oladi.
     */
    public static function decidePendingEmployeeRequest(array $input): void
    {
        $me = Auth::requireRole($input, Roles::REQUEST_APPROVE);

        $requestId = Validate::int($input, 'requestId');
        $decision = Validate::str($input, 'decision', 20);
        $izoh = Validate::str($input, 'izoh', 1000);
        if (!$requestId || !in_array($decision, ['approved', 'rejected'], true)) {
            Response::error("Ma'lumotlar to'liq emas", 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        self::ensurePendingSchema($db);

        $reqStmt = $db->prepare('SELECT * FROM employee_pending_requests WHERE id = :id LIMIT 1');
        $reqStmt->execute(['id' => $requestId]);
        $request = $reqStmt->fetch();
        if (!$request) {
            Response::error("So'rov topilmadi", 'NOT_FOUND', 404);
        }
        if ($request['status'] !== 'pending') {
            Response::error('Bu so\'rov bo\'yicha qaror allaqachon qabul qilingan', 'ALREADY_DECIDED', 409);
        }

        $voteStmt = $db->prepare(
            'INSERT INTO employee_pending_approvals (request_id, approver_id, approver_rol, decision, izoh)
             VALUES (:request_id, :approver_id, :approver_rol, :decision, :izoh)
             ON DUPLICATE KEY UPDATE decision = :decision2, izoh = :izoh2, decided_at = NOW()'
        );
        $voteStmt->execute([
            'request_id' => $requestId,
            'approver_id' => $me['id'],
            'approver_rol' => $me['rol'],
            'decision' => $decision,
            'izoh' => $izoh !== '' ? $izoh : null,
            'decision2' => $decision,
            'izoh2' => $izoh !== '' ? $izoh : null,
        ]);

        $finalStatus = null;
        if ($me['rol'] === Roles::SUPER_ADMIN) {
            // Yakka o'zi darhol yakuniy qaror — kvorumdan mustasno (zaxira huquq).
            $finalStatus = $decision;
        } elseif ($decision === 'rejected') {
            // Kamida bitta majburiy tomon rad etsa, kvorumsiz darhol yakuniy rad etiladi.
            $finalStatus = 'rejected';
        } else {
            $votesStmt = $db->prepare(
                "SELECT DISTINCT approver_rol FROM employee_pending_approvals
                 WHERE request_id = :id AND decision = 'approved'"
            );
            $votesStmt->execute(['id' => $requestId]);
            $approvedRoles = $votesStmt->fetchAll(\PDO::FETCH_COLUMN);
            if (in_array(Roles::ANTICOR_ADMIN, $approvedRoles, true) && in_array(Roles::RAHBARIYAT, $approvedRoles, true)) {
                $finalStatus = 'approved';
            }
        }

        if ($finalStatus === null) {
            Response::success(['status' => 'pending']);
            return;
        }

        $payload = json_decode((string) $request['payload'], true) ?: [];

        if ($finalStatus === 'approved') {
            try {
                if ($request['request_type'] === 'add') {
                    self::assertLoginAvailable($db, (string) ($payload['login'] ?? ''), $requestId);
                    self::insertUser($db, $payload);
                } else {
                    $targetId = (int) $request['target_user_id'];
                    $existsStmt = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
                    $existsStmt->execute(['id' => $targetId]);
                    if (!$existsStmt->fetch()) {
                        Response::error("Xodim topilmadi — so'rov bekor qilindi", 'NOT_FOUND', 404);
                    }
                    self::applyEditPayload($db, $targetId, $payload);
                }
            } catch (\Throwable $e) {
                // Qo'llash muvaffaqiyatsiz bo'lsa, so'rovni "pending" holatida
                // qoldiramiz (ovoz allaqachon yozildi) — xatolik xabari
                // ko'rsatiladi, keyinroq qayta urinib ko'rish mumkin bo'ladi.
                throw $e;
            }
        }

        $updStmt = $db->prepare(
            'UPDATE employee_pending_requests SET status = :status, decided_at = NOW() WHERE id = :id'
        );
        $updStmt->execute(['status' => $finalStatus, 'id' => $requestId]);

        Response::success(['status' => $finalStatus]);
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
