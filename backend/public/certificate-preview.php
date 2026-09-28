<?php
declare(strict_types=1);

// Sertifikat namunasi (PDF) — admin dizayn va imzo qo'yuvchilar qanday
// chiqishini ko'rishi uchun. ?filial=<kalit> — shu filial rahbari bilan.
// Faqat ANTICOR_MANAGE (anticor-admin, super-admin).

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

$viewer = Auth::optionalUser([]);
if (!$viewer || !in_array($viewer['rol'], Roles::ANTICOR_MANAGE, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ruxsat yo'q";
    exit;
}

try {
    $pdf = CertificateController::previewPdf(Database::connection(), isset($_GET['filial']) ? (string) $_GET['filial'] : null);
} catch (\Throwable $e) {
    error_log('[certificate-preview] ' . $e->getMessage());
    Logger::error('certificatePreview', $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Namunani tayyorlab bo'lmadi";
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Sertifikat namunasi.pdf"');
header('Content-Length: ' . (string) strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
