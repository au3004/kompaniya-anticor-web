<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\Response;
use App\Totp;
use App\Util;
use App\Validate;

/**
 * Ikki bosqichli tasdiqlash (2FA) — TOTP (Google Authenticator, Authy,
 * Microsoft Authenticator va h.k. bilan mos). Har bir foydalanuvchi o'zi
 * uchun ixtiyoriy ravishda (Sozlamalar orqali) yoqib/o'chirib qo'yadi.
 *
 * Kelajakda SMS orqali tasdiqlash kodi qo'shilishi rejalashtirilgan — TOTP
 * hozircha internet/SMS xizmatiga bog'liq bo'lmagan, darhol ishlaydigan yechim.
 */
final class TotpController
{
    private const DDL = "ALTER TABLE users
        ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL AFTER rol,
        ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret";

    private const PENDING_DDL = 'CREATE TABLE IF NOT EXISTS totp_pending (
        token       CHAR(64) PRIMARY KEY,
        user_id     INT NOT NULL,
        expires_at  DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB';

    public static function status(array $input): void
    {
        $user = Auth::requireUser($input);
        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $stmt = $db->prepare('SELECT totp_enabled FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $enabled = (bool) $stmt->fetchColumn();

        Response::success(['enabled' => $enabled]);
    }

    /**
     * Yangi (hali saqlanmagan) kalit yaratadi va qaytaradi — faqat
     * totpSetupConfirm orqali to'g'ri kod bilan tasdiqlangandan keyin
     * bazaga yoziladi va yoqiladi.
     */
    public static function setupStart(array $input): void
    {
        $user = Auth::requireUser($input);
        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $secret = Totp::generateSecret();

        Response::success([
            'secret' => $secret,
            'account' => $user['login'],
        ]);
    }

    public static function setupConfirm(array $input): void
    {
        $user = Auth::requireUser($input);
        $secret = Validate::requiredStr($input, 'secret', 64);
        $code = Validate::requiredStr($input, 'code', 10);

        if (!Totp::verifyCode($secret, $code)) {
            Response::error("Kod noto'g'ri, qaytadan urinib ko'ring", 'INVALID_CODE', 422);
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);
        $upd = $db->prepare('UPDATE users SET totp_secret = :secret, totp_enabled = 1 WHERE id = :id');
        $upd->execute(['secret' => $secret, 'id' => $user['id']]);

        Response::success();
    }

    public static function disable(array $input): void
    {
        $user = Auth::requireUser($input);
        $parol = Validate::requiredStr($input, 'parol', 255);

        $db = Database::connection();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $user['id']]);
        $row = $stmt->fetch();
        if (!$row || !Auth::verifyPassword($parol, $row['password_hash'])) {
            Response::error("Parol noto'g'ri", 'OLD_PASSWORD_WRONG');
        }

        $upd = $db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = :id');
        $upd->execute(['id' => $user['id']]);

        Response::success();
    }

    /**
     * Login qadam 2: parol to'g'ri bo'lgach (AuthController::login) va
     * totp_enabled bo'lsa, shu yerga keladigan pendingToken + 6 xonali kod
     * tekshiriladi, to'g'ri bo'lsa haqiqiy sessiya (cookie) yaratiladi.
     */
    public static function verifyLogin(array $input): void
    {
        $pendingToken = trim((string) ($input['pendingToken'] ?? ''));
        $code = Validate::requiredStr($input, 'code', 10);

        if ($pendingToken === '' || !preg_match('/^[a-f0-9]{64}$/', $pendingToken)) {
            Response::error('Sessiya topilmadi', 'SESSION_EXPIRED', 401);
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::PENDING_DDL);

        $stmt = $db->prepare(
            'SELECT tp.user_id, tp.expires_at, u.*
             FROM totp_pending tp
             JOIN users u ON u.id = tp.user_id
             WHERE tp.token = :token LIMIT 1'
        );
        $stmt->execute(['token' => $pendingToken]);
        $row = $stmt->fetch();

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            Response::error("Vaqt tugagan, qaytadan kiring", 'SESSION_EXPIRED', 401);
        }

        if (!Totp::verifyCode((string) $row['totp_secret'], $code)) {
            Auth::registerFailedAttempt((string) $row['login'], Config::int('PASSWORD_MAX_ATTEMPTS', 5));
            Response::error("Kod noto'g'ri", 'INVALID_CODE', 422);
        }

        $del = $db->prepare('DELETE FROM totp_pending WHERE token = :token');
        $del->execute(['token' => $pendingToken]);

        $token = Auth::generateToken();
        $idleMinutes = Config::int('SESSION_IDLE_MINUTES', 30);
        $expiresAt = date('Y-m-d H:i:s', time() + $idleMinutes * 60);
        $ins = $db->prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)');
        $ins->execute(['token' => $token, 'user_id' => $row['user_id'], 'expires_at' => $expiresAt]);

        Auth::setSessionCookie($token);

        Response::success([
            'token' => true,
            'familiya' => $row['familiya'],
            'ism' => $row['ism'],
            'otasi' => $row['otasining_ismi'],
            'lavozim' => $row['lavozim'],
            'bolinma' => $row['bolinma'],
            'telefon' => $row['telefon'],
            'rasm' => Util::photoUrl($row['rasm_url']),
            'rol' => $row['rol'],
        ]);
    }
}
