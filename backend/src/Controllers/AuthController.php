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

    public static function login(array $input): void
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
            // Login mavjud bo'lmasa qattiqroq (tezroq bloklaydigan) chegara, mavjud
            // bo'lib parol xato bo'lsa birozroq yumshoqroq chegara qo'llaniladi.
            $maxAttempts = $user
                ? Config::int('PASSWORD_MAX_ATTEMPTS', 5)
                : Config::int('LOGIN_UNKNOWN_MAX_ATTEMPTS', 3);
            Auth::registerFailedAttempt($login, $maxAttempts);
            Response::error("Login yoki parol noto'g'ri", 'INVALID_CREDENTIALS');
        }

        Auth::resetAttempts($login);

        // 2FA yoqilgan bo'lsa, hali haqiqiy sessiya yaratilmaydi — foydalanuvchi
        // avtentifikator ilovasidagi 6 xonali kodni tasdiqlashi kerak
        // (TotpController::verifyLogin, "pendingToken" orqali).
        if (!empty($user['totp_enabled'])) {
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

        Response::success([
            'token' => true,
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
        ]);
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
        $stmt = $db->prepare('SELECT id FROM users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error(
                "Login aniqlanmadi, iltimos tekshirib qaytadan kiriting",
                'LOGIN_NOT_FOUND',
                404
            );
        }

        $murojaat = "Parolni tiklash so'rovi. Ko'rsatilgan aloqa uchun telefon raqami: {$telefon}";
        $ins = $db->prepare('INSERT INTO support_requests (user_id, murojaat) VALUES (:user_id, :murojaat)');
        $ins->execute(['user_id' => $user['id'], 'murojaat' => $murojaat]);

        Response::success();
    }

    public static function logout(array $input): void
    {
        $token = trim((string) ($_COOKIE[Auth::COOKIE_NAME] ?? ''));
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
