<?php
declare(strict_types=1);

// Testdan o'tgan xodimning sertifikatini (PDF) beradi — kerak bo'lsa shu
// yerda yaratib (CertificateController::ensureForUser). Parametrsiz — o'z
// sertifikati; ?userId=N — boshqa xodimniki, faqat ANTICOR_VIEW rollari
// uchun. JSON action-dispatch API'dan tashqarida turadi (fayl oqimini
// to'g'ridan-to'g'ri yuborish uchun), avtorizatsiya boshqa yuklab olish
// skriptlari kabi sessiya cookie / Bearer token orqali.

ini_set('display_errors', '0');
error_reporting(E_ALL);
header_remove('X-Powered-By');

require dirname(__DIR__) . '/src/autoload.php';

use App\Auth;
use App\Config;
use App\Controllers\CertificateController;
use App\Database;
use App\Logger;
use App\Roles;

Config::load();

function certificateFail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$viewer = Auth::optionalUser([]);
if (!$viewer) {
    certificateFail(403, "Ruxsat yo'q");
}

$targetId = isset($_GET['userId']) ? (int) $_GET['userId'] : (int) $viewer['id'];
if ($targetId <= 0) {
    certificateFail(400, "Noto'g'ri so'rov");
}

$db = Database::connection();

if ($targetId === (int) $viewer['id']) {
    $target = $viewer;
} else {
    if (!in_array($viewer['rol'], Roles::ANTICOR_VIEW, true)) {
        certificateFail(403, "Ruxsat yo'q");
    }
    // super-admin hech qaysi ro'yxat/hisobotda ko'rinmasligi shart.
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id AND rol != :superAdmin LIMIT 1');
    $stmt->execute(['id' => $targetId, 'superAdmin' => Roles::SUPER_ADMIN]);
    $target = $stmt->fetch();
    if (!$target) {
        certificateFail(404, 'Topilmadi');
    }
}

try {
    $certificate = CertificateController::ensureForUser($db, $target);
} catch (\Throwable $e) {
    error_log('[certificate-download] ' . $e->getMessage());
    Logger::error('certificateDownload', $e->getMessage());
    certificateFail(500, "Sertifikatni tayyorlab bo'lmadi");
}

if (!$certificate) {
    certificateFail(404, "Sertifikat mavjud emas — test hali muvaffaqiyatli topshirilmagan");
}

$dir = CertificateController::certificatesDir();
$realPath = realpath($dir . '/' . $certificate['file_name']);
$realBase = realpath($dir);
if (!$realPath || !$realBase || !str_starts_with($realPath, $realBase) || !is_file($realPath)) {
    certificateFail(404, 'Topilmadi');
}

$safeName = preg_replace('/[^\p{L}\p{N}_\-. ]+/u', '_', 'Sertifikat ' . $certificate['fish']);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $safeName . '.pdf"');
header('Content-Length: ' . (string) filesize($realPath));
// F.I.Sh/filial o'zgarsa fayl qayta yaratiladi, URL esa o'sha — eski nusxa keshdan chiqmasin.
header('Cache-Control: private, no-store');
readfile($realPath);
