<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\Response;
use App\Util;
use App\Validate;

final class AuthController
{
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
        $stmt = $db->prepare('SELECT * FROM users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        // Foydalanuvchi topilmasa ham password_verify() ni bajaramiz (soxta hash bilan) —
        // aks holda javob vaqti orqali "bu login mavjud/mavjud emas"ligini bilib olish mumkin bo'lardi.
        $hashToCheck = $user['password_hash'] ?? Auth::dummyHash();
        $passwordOk = Auth::verifyPassword($parol, $hashToCheck);

        if (!$user || !$passwordOk) {
            Auth::registerFailedAttempt($login);
            Response::error("Login yoki parol noto'g'ri", 'INVALID_CREDENTIALS');
        }

        Auth::resetAttempts($login);

        $token = Auth::generateToken();
        $ttlHours = Config::int('SESSION_TTL_HOURS', 12);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlHours * 3600);

        $ins = $db->prepare(
            'INSERT INTO sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)'
        );
        $ins->execute(['token' => $token, 'user_id' => $user['id'], 'expires_at' => $expiresAt]);

        Response::success([
            'token' => $token,
            'familiya' => $user['familiya'],
            'ism' => $user['ism'],
            'otasi' => $user['otasining_ismi'],
            'lavozim' => $user['lavozim'],
            'bolinma' => $user['bolinma'],
            'telefon' => $user['telefon'],
            'rasm' => Util::photoUrl($user['rasm_url']),
            'rol' => $user['rol'],
        ]);
    }

    public static function logout(array $input): void
    {
        $token = trim((string) ($input['token'] ?? ''));
        if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM sessions WHERE token = :token');
            $stmt->execute(['token' => $token]);
        }
        Response::success();
    }

    public static function changePassword(array $input): void
    {
        $user = Auth::requireUser($input);
        $oldPass = Validate::requiredStr($input, 'eskiParol', 255);
        $newPass = Validate::requiredStr($input, 'yangiParol', 255);

        if (mb_strlen($newPass) < 6) {
            Response::error('Yangi parol kamida 6 belgidan iborat bo\'lishi kerak', 'WEAK_PASSWORD', 422);
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
