<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    /**
     * Requires a valid, non-expired session token in $input['token'].
     * Returns the joined user row. Exits with SESSION_EXPIRED on failure.
     */
    public static function requireUser(array $input): array
    {
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            Response::error('Sessiya topilmadi', 'SESSION_EXPIRED', 401);
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.*, s.expires_at
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = :token
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            if ($row) {
                $del = $db->prepare('DELETE FROM sessions WHERE token = :token');
                $del->execute(['token' => $token]);
            }
            Response::error('Sessiya muddati tugagan', 'SESSION_EXPIRED', 401);
        }

        // Sliding expiration: har bir muvaffaqiyatli so'rovda muddatni uzaytiramiz.
        $ttlHours = Config::int('SESSION_TTL_HOURS', 12);
        $newExpiry = date('Y-m-d H:i:s', time() + $ttlHours * 3600);
        $upd = $db->prepare('UPDATE sessions SET expires_at = :exp WHERE token = :token');
        $upd->execute(['exp' => $newExpiry, 'token' => $token]);

        unset($row['password_hash']);
        $row['token'] = $token;
        return $row;
    }

    /**
     * Like requireUser(), but returns null instead of erroring when no valid
     * session is present. Used by endpoints that are readable both publicly
     * and by authenticated staff (e.g. test/survey question lists).
     */
    public static function optionalUser(array $input): ?array
    {
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.*, s.expires_at
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = :token
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

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

    public static function registerFailedAttempt(string $login): void
    {
        $db = Database::connection();
        $maxAttempts = Config::int('LOGIN_MAX_ATTEMPTS', 5);
        $lockMinutes = Config::int('LOGIN_LOCK_MINUTES', 15);

        $stmt = $db->prepare('SELECT fail_count FROM login_attempts WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $row = $stmt->fetch();

        $failCount = ($row['fail_count'] ?? 0) + 1;
        $lockedUntil = null;
        if ($failCount >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockMinutes * 60);
            $failCount = 0;
        }

        $upsert = $db->prepare(
            'INSERT INTO login_attempts (login, fail_count, locked_until)
             VALUES (:login, :fail_count, :locked_until)
             ON DUPLICATE KEY UPDATE fail_count = :fail_count2, locked_until = :locked_until2'
        );
        $upsert->execute([
            'login' => $login,
            'fail_count' => $failCount,
            'locked_until' => $lockedUntil,
            'fail_count2' => $failCount,
            'locked_until2' => $lockedUntil,
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
