<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\PwnedPasswords;
use App\RateLimit;
use App\Response;
use App\Util;
use App\Validate;

final class AuthController
{
    private const TOTP_DDL = "ALTER TABLE users
        ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL AFTER rol,
        ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret";

    private const TOTP_PENDING_DDL = 'CREATE TABLE IF NOT EXISTS totp_pending (
        token       CHAR(64) PRIMARY KEY,
        user_id     INT NOT NULL,
        expires_at  DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB';

    private const REMEMBER_DDL = 'CREATE TABLE IF NOT EXISTS remember_tokens (
        token       CHAR(64) PRIMARY KEY,
        user_id     INT NOT NULL,
        created_at  DATETIME NOT NULL,
        expires_at  DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB';

    /**
     * login/parol'ni tekshiradi (bloklash, timing-oraliq himoyasi bilan) va
     * mos foydalanuvchi qatorini qaytaradi. login() (veb, cookie) va
     * mobileLogin() (Bearer token) ikkalasi ham shu bitta yo'lni ishlatadi —
     * shu bilan kelajakda bu yerga kiritiladigan har qanday tuzatish
     * (masalan yana bir enumeration kanali topilsa) ikkala oqimga ham bir
     * vaqtda tegishli bo'ladi.
     */
    private static function authenticateWithCredentials(array $input): array
    {
        $login = Validate::requiredStr($input, 'login', 100);
        $parol = Validate::requiredStr($input, 'parol', 255);

        if (Auth::isLocked($login)) {
            Response::error(
                "Ko'p marta noto'g'ri urinildi. 15 daqiqadan so'ng qayta urinib ko'ring.",
                'LOCKED',
                423
            );
        }

        $db = Database::connection();
        \App\Util::ensureSchema($db, self::TOTP_DDL);
        $stmt = $db->prepare('SELECT * FROM users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        // Foydalanuvchi topilmasa ham password_verify() ni bajaramiz (soxta hash bilan) —
        // aks holda javob vaqti orqali "bu login mavjud/mavjud emas"ligini bilib olish mumkin bo'lardi.
        $hashToCheck = $user['password_hash'] ?? Auth::dummyHash();
        $passwordOk = Auth::verifyPassword($parol, $hashToCheck);

        if (!$user || !$passwordOk) {
            // MUHIM: bloklanish chegarasi mavjud/mavjud bo'lmagan login uchun
            // ATAYLAB bir xil (PASSWORD_MAX_ATTEMPTS) — avval bu ikkisi turlicha
            // (3 va 5) edi, bu esa javob vaqtini (bcrypt hisoblangan/hisoblanmagan)
            // kuzatib, "bu login mavjudmi?" ni ANIQ bilib olish imkonini berardi:
            // login N-chi urinishda bloklansa — N=3 bo'lsa login yo'q, N=5 bo'lsa
            // login bor. Bir xil chegara bu kanalni butunlay yopadi.
            Auth::registerFailedAttempt($login, Config::int('PASSWORD_MAX_ATTEMPTS', 5));
            Response::error("Login yoki parol noto'g'ri", 'INVALID_CREDENTIALS');
        }

        Auth::resetAttempts($login);

        return $user;
    }

    /**
     * 2FA yoqilgan bo'lsa, hali haqiqiy sessiya yaratilmaydi — pendingToken
     * qaytariladi va javob true bilan tugaydi (chaqiruvchi shu yerda to'xtaydi).
     * 2FA yo'q bo'lsa, hech narsa qaytarmaydi — chaqiruvchi sessiya yaratishda davom etadi.
     */
    private static function maybeRequireTotp(\PDO $db, array $user): void
    {
        if (empty($user['totp_enabled'])) {
            return;
        }
        Util::ensureSchema($db, self::TOTP_PENDING_DDL);
        $pendingToken = Auth::generateToken();
        $ins = $db->prepare(
            'INSERT INTO totp_pending (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)'
        );
        $ins->execute([
            'token' => $pendingToken,
            'user_id' => $user['id'],
            'expires_at' => date('Y-m-d H:i:s', time() + 300),
        ]);
        Response::success(['needsTotp' => true, 'pendingToken' => $pendingToken]);
    }

    private static function userProfileFields(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'familiya' => $user['familiya'],
            'ism' => $user['ism'],
            'otasi' => $user['otasining_ismi'],
            'tugilganSana' => $user['tugilgan_sana'] ?? null,
            'lavozim' => $user['lavozim'],
            'bolinma' => $user['bolinma'],
            'telefon' => $user['telefon'],
            'rasm' => Util::photoUrl($user['rasm_url']),
            'rol' => $user['rol'],
        ];
    }

    public static function login(array $input): void
    {
        $user = self::authenticateWithCredentials($input);
        $db = Database::connection();
        self::maybeRequireTotp($db, $user);

        $token = Auth::generateToken();
        $idleMinutes = Config::int('SESSION_IDLE_MINUTES', 10);
        $expiresAt = date('Y-m-d H:i:s', time() + $idleMinutes * 60);

        $ins = $db->prepare(
            'INSERT INTO sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)'
        );
        $ins->execute(['token' => $token, 'user_id' => $user['id'], 'expires_at' => $expiresAt]);

        // Haqiqiy token endi JS o'qiy olmaydigan HttpOnly cookie'da saqlanadi (XSS orqali
        // o'g'irlanishining oldini olish uchun) — javob tanasida faqat "kirilgan" belgisi
        // (haqiqiy bo'lmagan qiymat) qaytariladi, frontend eski "token bor/yo'q" tekshiruvlari
        // uchun buni ishlataveradi, lekin bu qiymatning o'zi hech qanday amalga ruxsat bermaydi.
        Auth::setSessionCookie($token);

        if (!empty($input['rememberMe'])) {
            Auth::issueRememberToken($db, (int) $user['id']);
        }

        Response::success(array_merge(['token' => true], self::userProfileFields($user)));
    }

    /**
     * Swift/Flutter (native) ilovalar uchun — cookie o'rniga haqiqiy
     * sessiya tokenini to'g'ridan-to'g'ri javob tanasida qaytaradi. Mobil
     * ilova buni Keychain (iOS) / Keystore orqali xavfsiz saqlashi va har
     * bir keyingi so'rovda "Authorization: Bearer <token>" sarlavhasi
     * sifatida yuborishi kerak. Veb frontend bu amalni HECH QACHON
     * chaqirmaydi — shuning uchun login.html/admin.html/main.html kodida
     * bu tokenni JS orqali o'qish imkoniyati umuman yo'q (XSS himoyasi
     * shu tarzda saqlanib qoladi, faqat native ilova kontekstida token
     * brauzer DOM/JS'dan butunlay tashqarida — Keychain/Keystore'da yotadi).
     */
    public static function mobileLogin(array $input): void
    {
        $user = self::authenticateWithCredentials($input);
        $db = Database::connection();
        self::maybeRequireTotp($db, $user);

        $token = Auth::generateToken();
        $idleMinutes = Config::int('SESSION_IDLE_MINUTES', 10);
        $expiresAt = date('Y-m-d H:i:s', time() + $idleMinutes * 60);

        $ins = $db->prepare(
            'INSERT INTO sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)'
        );
        $ins->execute(['token' => $token, 'user_id' => $user['id'], 'expires_at' => $expiresAt]);

        $extra = ['sessionToken' => $token];
        if (!empty($input['rememberMe'])) {
            $extra['rememberToken'] = Auth::issueRememberToken($db, (int) $user['id'], false);
        }

        Response::success(array_merge($extra, self::userProfileFields($user)));
    }

    /**
     * Mobil ilova uchun "meni eslab qol" — loginViaRememberToken() bilan
     * bir xil (bitta martalik, mutlaq 1 soatlik) mantiq, faqat token
     * cookie'dan emas, so'rov tanasidan ("rememberToken") olinadi va
     * yangi sessiya/remember tokenlar cookie o'rniga javob tanasida
     * qaytariladi.
     */
    public static function mobileLoginViaRememberToken(array $input): void
    {
        $token = trim((string) ($input['rememberToken'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            Response::error('Sessiya topilmadi', 'NO_REMEMBER_SESSION', 401);
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::REMEMBER_DDL);

        $stmt = $db->prepare(
            'SELECT rt.user_id, rt.expires_at, u.*
             FROM remember_tokens rt
             JOIN users u ON u.id = rt.user_id
             WHERE rt.token = :token LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            if ($row) {
                $del = $db->prepare('DELETE FROM remember_tokens WHERE token = :token');
                $del->execute(['token' => $token]);
            }
            Response::error("Muddati o'tgan, qaytadan kiring", 'NO_REMEMBER_SESSION', 401);
        }

        $del = $db->prepare('DELETE FROM remember_tokens WHERE token = :token');
        $del->execute(['token' => $token]);

        $newToken = Auth::generateToken();
        $ins = $db->prepare(
            'INSERT INTO remember_tokens (token, user_id, created_at, expires_at) VALUES (:token, :user_id, NOW(), :expires_at)'
        );
        $ins->execute(['token' => $newToken, 'user_id' => $row['user_id'], 'expires_at' => $row['expires_at']]);

        $sessToken = Auth::generateToken();
        $idleMinutes = Config::int('SESSION_IDLE_MINUTES', 10);
        $sessExpiresAt = date('Y-m-d H:i:s', time() + $idleMinutes * 60);
        $insSess = $db->prepare(
            'INSERT INTO sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)'
        );
        $insSess->execute(['token' => $sessToken, 'user_id' => $row['user_id'], 'expires_at' => $sessExpiresAt]);

        Response::success(array_merge(
            ['sessionToken' => $sessToken, 'rememberToken' => $newToken],
            self::userProfileFields($row)
        ));
    }

    /**
     * Joriy sessiyaga tegishli foydalanuvchi profilini qaytaradi (login()
     * javobi bilan bir xil shakl). Asosan mobil ilova ilova qayta
     * ochilganda (saqlangan tokenni tekshirish/profilni yangilash uchun)
     * ishlatishi mo'ljallangan — hech qanday yozish amalini bajarmaydi,
     * faqat Auth::requireUser() orqali sessiya haqiqiyligini tasdiqlaydi.
     */
    public static function me(array $input): void
    {
        $user = Auth::requireUser($input);
        Response::success(self::userProfileFields($user));
    }

    /**
     * Parolni tiklash so'rovi — hali tizimga kira olmaydigan (sessiyasi yo'q)
     * foydalanuvchi uchun. Haqiqiy avtomatik parol tiklash emas: login
     * bazada borligini tekshiradi va topilsa, mavjud "Yordam so'rovlari"
     * navbatiga (gl-admin/admin ko'radigan) murojaat sifatida yozib qo'yadi.
     */
    public static function requestPasswordReset(array $input): void
    {
        // Avtorizatsiyasiz amal — Yordam navbatini soxta so'rovlar bilan
        // to'ldirib yuborilishining oldini olish uchun IP bo'yicha cheklov.
        RateLimit::enforce('requestPasswordReset', 5, 3600);

        $login = Validate::requiredStr($input, 'login', 100);
        $telefon = Validate::requiredStr($input, 'telefon', 20);

        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, telefon FROM users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error(
                "Login aniqlanmadi, iltimos tekshirib qaytadan kiriting",
                'LOGIN_NOT_FOUND',
                404
            );
        }

        // Kiritilgan telefon raqami hisobdagisiga mos kelmasa ham so'rovni rad
        // etmaymiz (bu login mavjudligini "ha/yo'q" javob orqali bilib olish
        // uchun yangi kanal ochib qo'yardi) — buning o'rniga Yordam navbatini
        // ko'rib chiquvchi xodimga bu ziddiyatni aniq ko'rsatib qo'yamiz, shu
        // bilan murojaat qiluvchi shaxsni qo'shimcha tekshirmasdan ishonib
        // qolmaydi (qarang: ProfileController::checkStatus'dagi bir xil
        // normalizatsiya usuli).
        $normalize = static fn (string $v): string => preg_replace('/\D+/', '', $v) ?? '';
        $phoneMatches = $normalize($telefon) !== '' && $normalize($telefon) === $normalize((string) ($user['telefon'] ?? ''));

        $murojaat = $phoneMatches
            ? "Parolni tiklash so'rovi. Ko'rsatilgan aloqa uchun telefon raqami: {$telefon}"
            : "Parolni tiklash so'rovi. DIQQAT: ko'rsatilgan telefon raqami ({$telefon}) tizimdagi hisobga bog'langan telefon bilan mos kelmadi — murojaat qiluvchi shaxsni boshqa yo'l bilan tasdiqlang.";
        $ins = $db->prepare('INSERT INTO support_requests (user_id, murojaat) VALUES (:user_id, :murojaat)');
        $ins->execute(['user_id' => $user['id'], 'murojaat' => $murojaat]);

        Response::success();
    }

    public static function logout(array $input): void
    {
        // Auth::tokenFromRequest() veb (cookie) va mobil (Authorization: Bearer)
        // ikkala transportni ham qamrab oladi — shu bilan mobil ilova ham
        // shu bitta amal orqali chiqish qila oladi.
        $token = Auth::tokenFromRequest();
        if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM sessions WHERE token = :token');
            $stmt->execute(['token' => $token]);
        }
        Auth::clearSessionCookie();

        $rememberToken = trim((string) ($_COOKIE[Auth::REMEMBER_COOKIE_NAME] ?? ''));
        if ($rememberToken !== '' && preg_match('/^[a-f0-9]{64}$/', $rememberToken)) {
            $db = $db ?? Database::connection();
            Util::ensureSchema($db, self::REMEMBER_DDL);
            $del = $db->prepare('DELETE FROM remember_tokens WHERE token = :token');
            $del->execute(['token' => $rememberToken]);
        }
        Auth::clearRememberCookie();

        Response::success();
    }

    /**
     * "Meni eslab qol" tokeni orqali parolsiz jim (silent) qayta kirish —
     * sessiya harakatsizlikdan tugagach (odatda 10 daqiqa), foydalanuvchi
     * login.html'ga qaytganda ko'rinmasdan chaqiriladi. Token bir martalik
     * ishlatiladi va darhol yangisiga almashtiriladi (rotatsiya), lekin uning
     * MUTLAQ tugash vaqti (expires_at) hech qachon uzaytirilmaydi — shu bilan
     * "1 soatdan keyin albatta parol so'raladi" talabi ta'minlanadi.
     */
    public static function loginViaRememberToken(array $input): void
    {
        $token = trim((string) ($_COOKIE[Auth::REMEMBER_COOKIE_NAME] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            Response::error('Sessiya topilmadi', 'NO_REMEMBER_SESSION', 401);
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::REMEMBER_DDL);

        $stmt = $db->prepare(
            'SELECT rt.user_id, rt.expires_at, u.*
             FROM remember_tokens rt
             JOIN users u ON u.id = rt.user_id
             WHERE rt.token = :token LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            if ($row) {
                $del = $db->prepare('DELETE FROM remember_tokens WHERE token = :token');
                $del->execute(['token' => $token]);
            }
            Auth::clearRememberCookie();
            Response::error("Muddati o'tgan, qaytadan kiring", 'NO_REMEMBER_SESSION', 401);
        }

        $del = $db->prepare('DELETE FROM remember_tokens WHERE token = :token');
        $del->execute(['token' => $token]);

        $newToken = Auth::generateToken();
        $ins = $db->prepare(
            'INSERT INTO remember_tokens (token, user_id, created_at, expires_at) VALUES (:token, :user_id, NOW(), :expires_at)'
        );
        $ins->execute(['token' => $newToken, 'user_id' => $row['user_id'], 'expires_at' => $row['expires_at']]);

        $secure = Config::get('FORCE_HTTPS', 'false') === 'true';
        setcookie(Auth::REMEMBER_COOKIE_NAME, $newToken, [
            'expires' => strtotime((string) $row['expires_at']),
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        $sessToken = Auth::generateToken();
        $idleMinutes = Config::int('SESSION_IDLE_MINUTES', 10);
        $sessExpiresAt = date('Y-m-d H:i:s', time() + $idleMinutes * 60);
        $insSess = $db->prepare(
            'INSERT INTO sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)'
        );
        $insSess->execute(['token' => $sessToken, 'user_id' => $row['user_id'], 'expires_at' => $sessExpiresAt]);
        Auth::setSessionCookie($sessToken);

        Response::success([
            'token' => true,
            'login' => $row['login'],
            'id' => (int) $row['user_id'],
            'familiya' => $row['familiya'],
            'ism' => $row['ism'],
            'otasi' => $row['otasining_ismi'],
            'tugilganSana' => $row['tugilgan_sana'] ?? null,
            'lavozim' => $row['lavozim'],
            'bolinma' => $row['bolinma'],
            'telefon' => $row['telefon'],
            'rasm' => Util::photoUrl($row['rasm_url']),
            'rol' => $row['rol'],
        ]);
    }

    public static function changePassword(array $input): void
    {
        $user = Auth::requireUser($input);
        $oldPass = Validate::requiredStr($input, 'eskiParol', 255);
        $newPass = Validate::requiredStr($input, 'yangiParol', 255);

        if (!Validate::isStrongPassword($newPass)) {
            Response::error(Validate::WEAK_PASSWORD_MESSAGE, 'WEAK_PASSWORD', 422);
        }
        if (PwnedPasswords::isBreached($newPass)) {
            Response::error(
                "Bu parol avval ma'lumotlar sizib chiqishlarida uchragan. Iltimos boshqa parol tanlang.",
                'PWNED_PASSWORD',
                422
            );
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $user['id']]);
        $row = $stmt->fetch();

        if (!$row || !Auth::verifyPassword($oldPass, $row['password_hash'])) {
            Response::error('Eski parol noto\'g\'ri', 'OLD_PASSWORD_WRONG');
        }

        $upd = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $upd->execute(['hash' => Auth::hashPassword($newPass), 'id' => $user['id']]);

        // Parol o'zgartirilgach, shu foydalanuvchining boshqa barcha faol sessiyalarini
        // bekor qilamiz (masalan, o'g'irlangan token bo'lsa, u endi ishlamaydi) —
        // joriy sessiya (hozir ishlatilayotgan token) tegilmaydi.
        $revoke = $db->prepare('DELETE FROM sessions WHERE user_id = :id AND token != :token');
        $revoke->execute(['id' => $user['id'], 'token' => $user['token']]);

        Response::success();
    }

    public static function getProfileRu(array $input): void
    {
        $user = Auth::requireUser($input);
        Response::success([
            'lavozim_ru' => $user['lavozim_ru'],
            'bolinma_ru' => $user['bolinma_ru'],
        ]);
    }
}
