<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public const COOKIE_NAME = 'session_token';
    public const REMEMBER_COOKIE_NAME = 'remember_token';

    private const REMEMBER_DDL = 'CREATE TABLE IF NOT EXISTS remember_tokens (
        token       CHAR(64) PRIMARY KEY,
        user_id     INT NOT NULL,
        created_at  DATETIME NOT NULL,
        expires_at  DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB';

    /**
     * Sessiya tokeni endi javob tanasida (JSON) qaytarilmaydi va JS'dan
     * o'qilmaydi — faqat HttpOnly cookie orqali saqlanadi, shu bilan XSS
     * orqali token o'g'irlanishining oldi olinadi. $input parametri endi
     * ishlatilmaydi (eski frontend chaqiruvlari bilan moslik uchun saqlangan).
     */
    private static function tokenFromCookie(): string
    {
        return trim((string) ($_COOKIE[self::COOKIE_NAME] ?? ''));
    }

    public static function setSessionCookie(string $token): void
    {
        $secure = Config::get('FORCE_HTTPS', 'false') === 'true';
        $absoluteHours = Config::int('SESSION_ABSOLUTE_TTL_HOURS', 168);
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + $absoluteHours * 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public static function clearSessionCookie(): void
    {
        $secure = Config::get('FORCE_HTTPS', 'false') === 'true';
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * "Meni eslab qol" — parolni emas, faqat mutlaq (sliding bo'lmagan) 1 soatlik
     * muddatga ega alohida tokenni HttpOnly cookie'da saqlaydi. Sessiya
     * harakatsizlikdan (odatda 10 daqiqa) tugab qolsa, shu token orqali (parolsiz)
     * bitta martalik jim (silent) qayta kirish mumkin bo'ladi — lekin token
     * o'zi hech qachon uzaytirilmaydi, shuning uchun qurilma egasi bo'lmagan
     * odam 1 soatdan keyin baribir parolni qayta kiritishga majbur bo'ladi.
     */
    public static function issueRememberToken(PDO $db, int $userId): void
    {
        Util::ensureSchema($db, self::REMEMBER_DDL);
        $token = self::generateToken();
        $ttlMinutes = Config::int('REMEMBER_TTL_MINUTES', 60);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlMinutes * 60);
        $ins = $db->prepare(
            'INSERT INTO remember_tokens (token, user_id, created_at, expires_at) VALUES (:token, :user_id, NOW(), :expires_at)'
        );
        $ins->execute(['token' => $token, 'user_id' => $userId, 'expires_at' => $expiresAt]);

        $secure = Config::get('FORCE_HTTPS', 'false') === 'true';
        setcookie(self::REMEMBER_COOKIE_NAME, $token, [
            'expires' => strtotime($expiresAt),
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public static function clearRememberCookie(): void
    {
        $secure = Config::get('FORCE_HTTPS', 'false') === 'true';
        setcookie(self::REMEMBER_COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Requires a valid, non-expired session — token HttpOnly cookie'dan olinadi.
     * Returns the joined user row. Exits with SESSION_EXPIRED on failure.
     */
    public static function requireUser(array $input): array
    {
        $token = self::tokenFromCookie();
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            Response::error('Sessiya topilmadi', 'SESSION_EXPIRED', 401);
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.*, s.expires_at, s.created_at AS session_created_at
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = :token
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        $expired = !$row
            || strtotime((string) $row['expires_at']) < time()
            || self::sessionTooOld($row['session_created_at'] ?? null);

        if ($expired) {
            if ($row) {
                $del = $db->prepare('DELETE FROM sessions WHERE token = :token');
                $del->execute(['token' => $token]);
            }
            Response::error('Sessiya muddati tugagan', 'SESSION_EXPIRED', 401);
        }

        // Sliding expiration: har bir muvaffaqiyatli so'rovda muddatni uzaytiramiz —
        // shuning uchun bu aslida "harakatsizlik" (idle) muddati (standart: 10 daqiqa).
        // sessionTooOld() tekshiruvi orqali umumiy amal qilish muddati baribir cheklangan.
        $idleMinutes = Config::int('SESSION_IDLE_MINUTES', 10);
        $newExpiry = date('Y-m-d H:i:s', time() + $idleMinutes * 60);
        $upd = $db->prepare('UPDATE sessions SET expires_at = :exp WHERE token = :token');
        $upd->execute(['exp' => $newExpiry, 'token' => $token]);

        unset($row['password_hash'], $row['session_created_at']);
        $row['token'] = $token;
        return $row;
    }

    /**
     * Yon tomondan uzaytirilaverishi (sliding expiration) tufayli faol token
     * cheksiz amal qilib qolmasligi uchun mutlaq umr chegarasi (masalan, 7 kun).
     */
    private static function sessionTooOld(?string $createdAt): bool
    {
        if (!$createdAt) {
            return false;
        }
        $maxHours = Config::int('SESSION_ABSOLUTE_TTL_HOURS', 168);
        return strtotime($createdAt) < time() - $maxHours * 3600;
    }

    /**
     * Like requireUser(), but returns null instead of erroring when no valid
     * session is present. Used by endpoints that are readable both publicly
     * and by authenticated staff (e.g. test/survey question lists).
     */
    public static function optionalUser(array $input): ?array
    {
        $token = self::tokenFromCookie();
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.*, s.expires_at, s.created_at AS session_created_at
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = :token
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row || strtotime((string) $row['expires_at']) < time() || self::sessionTooOld($row['session_created_at'] ?? null)) {
            return null;
        }
        unset($row['session_created_at']);

        unset($row['password_hash']);
        $row['token'] = $token;
        return $row;
    }

    /**
     * Requires a valid session AND that the user's role is in $roles.
     */
    public static function requireRole(array $input, array $roles): array
    {
        $user = self::requireUser($input);
        if (!in_array($user['rol'], $roles, true)) {
            Response::error("Sizda bu amal uchun huquq yo'q", 'FORBIDDEN', 403);
        }
        return $user;
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Mavjud bo'lmagan login uchun password_verify()ni "haqiqiy" hisoblash vaqtiga
     * yaqinlashtirish uchun ishlatiladigan statik bcrypt hash (login enumeration'dan himoya).
     */
    public static function dummyHash(): string
    {
        return '$2y$12$bNHdSHT//Ad59PBf3qFRZe7t/2ecCXn.Ey6Rdz9i7yXlXGANYWYk.';
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Returns true (and sends an error response) if the given login is currently locked out.
     */
    public static function isLocked(string $login): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT locked_until FROM login_attempts WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $row = $stmt->fetch();
        if (!$row || !$row['locked_until']) {
            return false;
        }
        return strtotime((string) $row['locked_until']) > time();
    }

    /**
     * $maxAttempts qiymati chaqiruvchi tomonidan beriladi. MUHIM: login mavjud
     * yoki mavjud bo'lmasa ham chaqiruvchi BIR XIL qiymatni (PASSWORD_MAX_ATTEMPTS)
     * berishi shart — ikkisi turlicha bo'lsa, "nechinchi urinishda bloklandi"
     * kuzatuvi orqali login mavjudligini bilib olish (timing/enumeration
     * kanali) mumkin bo'lib qolardi.
     */
    public static function registerFailedAttempt(string $login, int $maxAttempts): void
    {
        $db = Database::connection();
        $lockMinutes = Config::int('LOGIN_LOCK_MINUTES', 15);
        $lockedUntilValue = date('Y-m-d H:i:s', time() + $lockMinutes * 60);

        // Yagona atomik UPSERT: hisoblash va bloklash qarori MySQL'ning
        // o'zida, shu qator uchun olingan lock ostida bajariladi. Avvalgi
        // SELECT (o'qish) va keyin alohida INSERT/UPDATE (yozish) ikki
        // bosqichli yondashuv parallel so'rovlar o'rtasida poyga holatiga
        // (race condition) yo'l qo'yardi — masalan, bir xil loginga bir
        // vaqtda kelgan bir nechta noto'g'ri urinish fail_count'ni
        // bir-birining ustidan yozib, hisobni yo'qotishi va bloklashni
        // chetlab o'tishga imkon berishi mumkin edi.
        $isFirstAttemptLock = 1 >= $maxAttempts;
        $upsert = $db->prepare(
            'INSERT INTO login_attempts (login, fail_count, locked_until)
             VALUES (:login, :init_fail_count, :init_locked_until)
             ON DUPLICATE KEY UPDATE
                 locked_until = IF(fail_count + 1 >= :max1, :locked_until, locked_until),
                 fail_count = IF(fail_count + 1 >= :max2, 0, fail_count + 1)'
        );
        $upsert->execute([
            'login' => $login,
            'init_fail_count' => $isFirstAttemptLock ? 0 : 1,
            'init_locked_until' => $isFirstAttemptLock ? $lockedUntilValue : null,
            'max1' => $maxAttempts,
            'max2' => $maxAttempts,
            'locked_until' => $lockedUntilValue,
        ]);
    }

    public static function resetAttempts(string $login): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO login_attempts (login, fail_count, locked_until)
             VALUES (:login, 0, NULL)
             ON DUPLICATE KEY UPDATE fail_count = 0, locked_until = NULL'
        );
        $stmt->execute(['login' => $login]);
    }
}
