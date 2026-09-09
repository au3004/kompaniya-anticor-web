<?php
declare(strict_types=1);

/**
 * VAQTINCHALIK CLI skript: super-admin parolini unutib qo'yilganda tiklash
 * uchun. Faqat serverga SSH/CLI orqali kirish huquqi bor kishi ishlatishi
 * mumkin (brauzer orqali ishlamaydi). Ishlatilgandan so'ng ushbu faylni
 * repodan o'chirib tashlash tavsiya etiladi — doimiy parol-bypass skripti
 * production kodida qolib ketmasligi kerak.
 *
 * Ishlatish:
 *   php backend/migrations/reset_super_admin_password.php <yangi_parol>
 *
 * Nima qiladi:
 *   - rol='super-admin' bo'lgan yagona xodimni topadi
 *   - yangi parolni (Validate::isStrongPassword talablariga mos) bcrypt bilan xeshlab yozadi
 *   - o'sha login uchun login_attempts qulfini (agar bo'lsa) tozalaydi
 *
 * Skript ishlagach, ilovaga shu yangi parol bilan kirib, DARHOL
 * Sozlamalar -> Parolni o'zgartirish orqali o'z doimiy parolingizni
 * o'rnating.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu skript faqat buyruqlar qatoridan (CLI) ishga tushiriladi.\n");
}

require dirname(__DIR__) . '/src/autoload.php';

use App\Auth;
use App\Config;
use App\Database;
use App\Validate;

Config::load();

$parol = $argv[1] ?? null;

if (!$parol) {
    fwrite(STDERR, "Foydalanish: php reset_super_admin_password.php <yangi_parol>\n");
    exit(1);
}

if (!Validate::isStrongPassword($parol)) {
    fwrite(STDERR, Validate::WEAK_PASSWORD_MESSAGE . "\n");
    exit(1);
}

$db = Database::connection();

$stmt = $db->query("SELECT id, login FROM users WHERE rol = 'super-admin'");
$admins = $stmt->fetchAll();

if (count($admins) === 0) {
    fwrite(STDERR, "Bazada super-admin topilmadi.\n");
    exit(1);
}
if (count($admins) > 1) {
    fwrite(STDERR, "Bazada bir nechta super-admin topildi — bu kutilmagan holat, qo'lda tekshiring.\n");
    exit(1);
}

$admin = $admins[0];
$hash = Auth::hashPassword($parol);

$upd = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
$upd->execute(['hash' => $hash, 'id' => $admin['id']]);

$clearLock = $db->prepare(
    "UPDATE login_attempts SET fail_count = 0, locked_until = NULL WHERE login = :login"
);
$clearLock->execute(['login' => $admin['login']]);

echo "super-admin (\"{$admin['login']}\") paroli yangilandi.\n";
echo "Endi shu login va yangi parol bilan kirib, DARHOL Sozlamalar -> Parolni o'zgartirish orqali doimiy parolingizni o'rnating.\n";
