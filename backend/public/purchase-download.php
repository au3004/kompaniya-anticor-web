<?php
declare(strict_types=1);

// Xaridlar reyestridagi shartnoma faylini (PDF) yuklab beradi. JSON
// action-dispatch API'dan tashqarida turadi (fayl oqimini to'g'ridan-
// to'g'ri yuborish uchun) — lekin xuddi shunday HttpOnly sessiya cookie
// orqali avtorizatsiya qilinadi, faqat Xaridlar reyestriga kirish huquqi
// bor rollar (xarid/anticor-admin/super-admin).

ini_set('display_errors', '0');
error_reporting(E_ALL);
header_remove('X-Powered-By');

require dirname(__DIR__) . '/src/autoload.php';

use App\Auth;
use App\Config;
use App\Controllers\PurchaseController;
use App\Database;
use App\Roles;

Config::load();

$user = Auth::optionalUser([]);
if (!$user || !in_array($user['rol'], Roles::PURCHASE_VIEW, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ruxsat yo'q";
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo "Noto'g'ri so'rov";
    exit;
}

$db = Database::connection();
$stmt = $db->prepare('SELECT file_name, original_name, shartnoma_raqami FROM purchases WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row || empty($row['file_name'])) {
    http_response_code(404);
    echo 'Topilmadi';
    exit;
}

$dir = PurchaseController::contractsDir();
$path = $dir . '/' . $row['file_name'];
$realPath = realpath($path);
$realBase = realpath($dir);
if (!$realPath || !$realBase || !str_starts_with($realPath, $realBase) || !is_file($realPath)) {
    http_response_code(404);
    echo 'Topilmadi';
    exit;
}

$rawName = (string) ($row['original_name'] ?: $row['shartnoma_raqami'] ?: 'shartnoma');
$safeName = preg_replace('/[^\p{L}\p{N}_\-. ]+/u', '_', $rawName);
$safeName = $safeName !== '' ? $safeName : 'shartnoma';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $safeName . '.pdf"');
header('Content-Length: ' . (string) filesize($realPath));
readfile($realPath);
