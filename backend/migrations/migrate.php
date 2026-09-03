<?php
declare(strict_types=1);

/**
 * CLI migratsiya skripti: repo ildizidagi schema.sql faylini MySQL bazasiga qo'llaydi.
 *
 * Ishlatish:
 *   php backend/migrations/migrate.php
 *
 * .env fayli (backend/.env) DB_HOST, DB_PORT, DB_USER, DB_PASS qiymatlaridan foydalanadi.
 * schema.sql o'zi CREATE DATABASE IF NOT EXISTS bilan boshlanadi, shuning uchun
 * bazaga hali tanlanmagan holatda ulanamiz.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu skript faqat buyruqlar qatoridan (CLI) ishga tushiriladi.\n");
}

require dirname(__DIR__) . '/src/autoload.php';

use App\Config;

Config::load();

$host = Config::get('DB_HOST', 'localhost');
$port = (int) Config::get('DB_PORT', '3306');
$user = Config::get('DB_USER', 'root');
$pass = Config::get('DB_PASS', '');

$schemaPath = dirname(__DIR__, 2) . '/schema.sql';
if (!is_file($schemaPath)) {
    fwrite(STDERR, "schema.sql topilmadi: {$schemaPath}\n");
    exit(1);
}

$sql = file_get_contents($schemaPath);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "schema.sql bo'sh yoki o'qib bo'lmadi.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @mysqli_connect($host, $user, $pass, '', $port);
if (!$mysqli) {
    fwrite(STDERR, 'Bazaga ulanib bo\'lmadi: ' . mysqli_connect_error() . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

echo "schema.sql qo'llanmoqda...\n";

$ok = $mysqli->multi_query($sql);
if (!$ok) {
    fwrite(STDERR, 'Xatolik: ' . $mysqli->error . "\n");
    exit(1);
}

$statementIndex = 0;
$hasError = false;
do {
    $statementIndex++;
    if ($mysqli->errno) {
        fwrite(STDERR, "  [{$statementIndex}]-buyruqda xatolik: {$mysqli->error}\n");
        $hasError = true;
    }
    if ($result = $mysqli->store_result()) {
        $result->free();
    }
} while ($mysqli->more_results() && $mysqli->next_result());

$mysqli->close();

if ($hasError) {
    fwrite(STDERR, "Migratsiya xatoliklar bilan yakunlandi.\n");
    exit(1);
}

echo "Migratsiya muvaffaqiyatli yakunlandi. Baza va jadvallar tayyor.\n";
echo "Endi birinchi gl-admin xodimni yaratish uchun quyidagini bajaring:\n";
echo "  php backend/migrations/seed_admin.php <login> <parol> <familiya> <ism>\n";
