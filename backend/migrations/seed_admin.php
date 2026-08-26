<?php
declare(strict_types=1);

/**
 * CLI skript: birinchi "gl-admin" (bosh administrator) xodimni yaratadi/yangilaydi.
 *
 * Ishlatish:
 *   php backend/migrations/seed_admin.php <login> <parol> <familiya> <ism> [otasi] [lavozim] [bolinma] [telefon]
 *
 * Misol:
 *   php backend/migrations/seed_admin.php glavadmin "KuchliParol123!" Aliyev Vali
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu skript faqat buyruqlar qatoridan (CLI) ishga tushiriladi.\n");
}

require dirname(__DIR__) . '/src/autoload.php';

use App\Auth;
use App\Config;
use App\Database;

Config::load();

[$login, $parol, $familiya, $ism] = [$argv[1] ?? null, $argv[2] ?? null, $argv[3] ?? null, $argv[4] ?? null];

if (!$login || !$parol || !$familiya || !$ism) {
    fwrite(STDERR, "Foydalanish: php seed_admin.php <login> <parol> <familiya> <ism> [otasi] [lavozim] [bolinma] [telefon]\n");
    exit(1);
}

if (strlen($parol) < 8) {
    fwrite(STDERR, "Parol kamida 8 belgidan iborat bo'lishi kerak.\n");
    exit(1);
}

$otasi = $argv[5] ?? null;
$lavozim = $argv[6] ?? 'Bosh administrator';
$bolinma = $argv[7] ?? null;
$telefon = $argv[8] ?? null;

$db = Database::connection();
$hash = Auth::hashPassword($parol);

$stmt = $db->prepare(
    'INSERT INTO users (login, password_hash, familiya, ism, otasining_ismi, lavozim, bolinma, telefon, rol)
     VALUES (:login, :hash, :familiya, :ism, :otasi, :lavozim, :bolinma, :telefon, \'gl-admin\')
     ON DUPLICATE KEY UPDATE
        password_hash = VALUES(password_hash),
        familiya = VALUES(familiya),
        ism = VALUES(ism),
        otasining_ismi = VALUES(otasining_ismi),
        lavozim = VALUES(lavozim),
        bolinma = VALUES(bolinma),
        telefon = VALUES(telefon),
        rol = \'gl-admin\''
);
$stmt->execute([
    'login' => $login,
    'hash' => $hash,
    'familiya' => $familiya,
    'ism' => $ism,
    'otasi' => $otasi,
    'lavozim' => $lavozim,
    'bolinma' => $bolinma,
    'telefon' => $telefon,
]);

echo "gl-admin xodim tayyor: login=\"{$login}\"\n";
