<?php
declare(strict_types=1);

// Zaxira nusxa faylini (db.sql + uploads.zip) bitta ZIP qilib yuklab beradi.
// JSON action-dispatch API'dan tashqarida turadi (fayl oqimini to'g'ridan-to'g'ri
// yuborish uchun) — lekin xuddi shunday HttpOnly sessiya cookie orqali
// avtorizatsiya qilinadi, faqat gl-admin.

ini_set('display_errors', '0');
error_reporting(E_ALL);

require dirname(__DIR__) . '/src/autoload.php';

use App\Auth;
use App\Config;
use App\Controllers\BackupController;

Config::load();

$user = Auth::optionalUser([]);
if (!$user || $user['rol'] !== 'gl-admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ruxsat yo'q";
    exit;
}

$name = (string) ($_GET['name'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $name)) {
    http_response_code(400);
    echo "Noto'g'ri so'rov";
    exit;
}

$dir = BackupController::backupDir() . '/' . $name;
$realDir = realpath($dir);
$realBase = realpath(BackupController::backupDir());
if (!$realDir || !$realBase || !str_starts_with($realDir, $realBase) || !is_dir($realDir)) {
    http_response_code(404);
    echo 'Topilmadi';
    exit;
}

$tmpZip = tempnam(sys_get_temp_dir(), 'backup_') . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    echo 'Zip yaratib bo\'lmadi';
    exit;
}
foreach (scandir($realDir) ?: [] as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    $full = $realDir . '/' . $file;
    if (is_file($full)) {
        $zip->addFile($full, $file);
    }
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="backup_' . $name . '.zip"');
header('Content-Length: ' . (string) filesize($tmpZip));
readfile($tmpZip);
unlink($tmpZip);
