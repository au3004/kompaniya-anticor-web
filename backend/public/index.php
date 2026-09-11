<?php
declare(strict_types=1);

// Kutilmagan xatoliklar (masalan, DB ulanish nosozligi) brauzerga fayl yo'llari yoki
// stack trace kabi ichki ma'lumotlarni chiqarib yubormasligi uchun — bular faqat
// server jurnaliga (error_log) yoziladi, mijozga esa umumiy xabar qaytariladi.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require dirname(__DIR__) . '/src/autoload.php';

use App\Config;
use App\Cors;
use App\Response;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\ConflictDeclarationController;
use App\Controllers\DocsController;
use App\Controllers\HrDocumentController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\PurchaseController;
use App\Controllers\ReportsController;
use App\Controllers\SupportController;
use App\Controllers\SurveyController;
use App\Controllers\TestController;
use App\Controllers\TotpController;

Config::load();

// Production'da HTTPS'ni majburlash (ixtiyoriy — .env'da FORCE_HTTPS=true qilinganda
// yoqiladi, shu bilan mahalliy XAMPP/http test muhitini buzmaydi).
if (Config::get('FORCE_HTTPS', 'false') === 'true') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
    // HSTS: brauzerga shu domenga keyingi safar ham faqat HTTPS orqali murojaat
    // qilishni "eslatib qo'yadi" — parol/token kabi ma'lumotlar hech qachon
    // shifrlanmagan (http) tarmoq orqali yubormaydi.
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Serverning o'zi ("X-Powered-By: PHP/x.y.z") aniq versiyani oshkor qilib
// qo'ymasligi uchun — bu tashqi hujumchiga versiyaga xos zaifliklarni
// qidirish uchun ortiqcha ma'lumot berardi.
header_remove('X-Powered-By');

Cors::handle();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Response::error('Faqat POST so\'rovlar qabul qilinadi', 'METHOD_NOT_ALLOWED', 405);
}

// Brauzerdan JSON matnli (form emas) so'rov kelayotganini talab qilamiz —
// aks holda oddiy HTML <form enctype="text/plain"> orqali (JavaScript'siz,
// kesishma-sayt sahifadan) shu manzilga deyarli haqiqiy JSON tanasi yuborish
// mumkin bo'lardi. Amalda sessiya cookie'si SameSite=Strict bo'lgani uchun
// bunday so'rovga baribir cookie biriktirilmaydi — bu shunchaki qo'shimcha
// himoya qatlami.
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (!str_starts_with(strtolower(trim($contentType)), 'application/json')) {
    Response::error('Content-Type: application/json talab qilinadi', 'BAD_REQUEST', 400);
}

// So'rov tanasi hajmini, qaysi amal ekanini (uni bilish uchun JSON'ni
// dekodlash kerak) hali bilmasdan turib ham cheklaymiz — aks holda fayl
// yuklamaydigan har qanday amal (masalan oddiy "login") ham cheksiz katta
// tana bilan xotira/protsessorni band qilib qo'yishi mumkin edi.
const MAX_BODY_BYTES = 2 * 1024 * 1024; // 2 MB — fayl yubormaydigan amallar uchun yetarli
const MAX_FILE_BODY_BYTES = 36 * 1024 * 1024; // ~25 MB fayl * 1.4 (base64) + zaxira

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > MAX_FILE_BODY_BYTES) {
    Response::error("So'rov tanasi juda katta", 'PAYLOAD_TOO_LARGE', 413);
}

$raw = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    Response::error('So\'rov tanasi noto\'g\'ri JSON', 'BAD_REQUEST', 400);
}

$action = (string) ($input['action'] ?? '');

// Fayl (base64 PDF/rasm) olib yuruvchi amallardan tashqari hammasi uchun
// ancha qattiqroq chegara — faqat shu amallarga katta tana kerak bo'lishi mumkin.
$fileCarryingActions = ['updateProfilePhoto', 'addDocument', 'editDocument', 'submitHrDocument', 'addPurchase'];
if (!in_array($action, $fileCarryingActions, true) && strlen($raw) > MAX_BODY_BYTES) {
    Response::error("So'rov tanasi juda katta", 'PAYLOAD_TOO_LARGE', 413);
}

/** @var array<string, array{0: class-string, 1: string}> $routes */
$routes = [
    'login' => [AuthController::class, 'login'],
    'mobileLogin' => [AuthController::class, 'mobileLogin'],
    'loginViaRememberToken' => [AuthController::class, 'loginViaRememberToken'],
    'requestPasswordReset' => [AuthController::class, 'requestPasswordReset'],
    'logout' => [AuthController::class, 'logout'],
    'changePassword' => [AuthController::class, 'changePassword'],
    'getProfileRu' => [AuthController::class, 'getProfileRu'],

    'checkStatus' => [ProfileController::class, 'checkStatus'],
    'updateProfilePhoto' => [ProfileController::class, 'updateProfilePhoto'],
    'getMySessions' => [ProfileController::class, 'getMySessions'],
    'revokeSession' => [ProfileController::class, 'revokeSession'],
    'revokeOtherSessions' => [ProfileController::class, 'revokeOtherSessions'],

    'submitSupport' => [SupportController::class, 'submit'],

    'getDocuments' => [DocsController::class, 'getDocuments'],
    'markDocRead' => [DocsController::class, 'markDocRead'],
    'addDocument' => [DocsController::class, 'add'],
    'editDocument' => [DocsController::class, 'edit'],
    'deleteDocument' => [DocsController::class, 'delete'],

    'submitHrDocument' => [HrDocumentController::class, 'submit'],
    'getHrDocuments' => [HrDocumentController::class, 'list'],
    'deleteHrDocument' => [HrDocumentController::class, 'delete'],

    'getTestQuestions' => [TestController::class, 'getQuestions'],
    'submitTest' => [TestController::class, 'submit'],
    'setTestActive' => [TestController::class, 'setActive'],
    'addTestQuestion' => [TestController::class, 'add'],
    'editTestQuestion' => [TestController::class, 'edit'],
    'deleteTestQuestion' => [TestController::class, 'delete'],

    'getSurveyQuestions' => [SurveyController::class, 'getQuestions'],
    'submitSurveyAnswers' => [SurveyController::class, 'submit'],
    'setSurveyActive' => [SurveyController::class, 'setActive'],
    'addSurveyQuestion' => [SurveyController::class, 'add'],
    'editSurveyQuestion' => [SurveyController::class, 'edit'],
    'deleteSurveyQuestion' => [SurveyController::class, 'delete'],
    'getSurveyResults' => [SurveyController::class, 'results'],

    'getMyNotifications' => [NotificationController::class, 'mine'],
    'markNotificationRead' => [NotificationController::class, 'markRead'],
    'sendNotification' => [NotificationController::class, 'send'],
    'getNotificationReport' => [NotificationController::class, 'report'],

    'getUsersList' => [AdminController::class, 'usersList'],
    'getStats' => [AdminController::class, 'stats'],
    'addEmployee' => [AdminController::class, 'addEmployee'],
    'editEmployee' => [AdminController::class, 'editEmployee'],
    'deleteEmployee' => [AdminController::class, 'deleteEmployee'],
    'unlockLogin' => [AdminController::class, 'unlockLogin'],
    'getErrorLog' => [AdminController::class, 'getErrorLog'],
    'getPendingEmployeeRequests' => [AdminController::class, 'getPendingEmployeeRequests'],
    'decidePendingEmployeeRequest' => [AdminController::class, 'decidePendingEmployeeRequest'],

    'addPurchase' => [PurchaseController::class, 'addPurchase'],
    'getPurchases' => [PurchaseController::class, 'getPurchases'],

    'submitDeclaration' => [ConflictDeclarationController::class, 'submit'],
    'getDeclarations' => [ConflictDeclarationController::class, 'adminList'],
    'getDeclaration' => [ConflictDeclarationController::class, 'adminGetOne'],
    'deleteDeclaration' => [ConflictDeclarationController::class, 'adminDelete'],

    'getSupportRequests' => [ReportsController::class, 'getSupportRequests'],
    'addSupportComment' => [ReportsController::class, 'addSupportComment'],

    'getUsersReport' => [ReportsController::class, 'getUsersReport'],
    'getProgressReport' => [ReportsController::class, 'getProgressReport'],
    'getTestAttemptsRaw' => [ReportsController::class, 'getTestAttemptsRaw'],
    'getDocReadsRaw' => [ReportsController::class, 'getDocReadsRaw'],
    'getSurveySubmissionsRaw' => [ReportsController::class, 'getSurveySubmissionsRaw'],
    'getNotificationsRaw' => [ReportsController::class, 'getNotificationsRaw'],
    'getNotificationReadsRaw' => [ReportsController::class, 'getNotificationReadsRaw'],
    'getSurveyAnswersWide' => [ReportsController::class, 'getSurveyAnswersWide'],

    'deleteTestAttempts' => [ReportsController::class, 'deleteTestAttempts'],
    'deleteDocReads' => [ReportsController::class, 'deleteDocReads'],
    'deleteSurveySubmissions' => [ReportsController::class, 'deleteSurveySubmissions'],
    'deleteNotifications' => [ReportsController::class, 'deleteNotifications'],
    'deleteNotificationReads' => [ReportsController::class, 'deleteNotificationReads'],
    'deleteSupportRequests' => [ReportsController::class, 'deleteSupportRequests'],

    'createBackup' => [BackupController::class, 'create'],
    'listBackups' => [BackupController::class, 'list'],

    'totpStatus' => [TotpController::class, 'status'],
    'totpSetupStart' => [TotpController::class, 'setupStart'],
    'totpSetupConfirm' => [TotpController::class, 'setupConfirm'],
    'totpDisable' => [TotpController::class, 'disable'],
    'verifyTotpLogin' => [TotpController::class, 'verifyLogin'],
    'mobileVerifyTotpLogin' => [TotpController::class, 'mobileVerifyLogin'],
];

if (!isset($routes[$action])) {
    Response::error("Noma'lum amal: {$action}", 'UNKNOWN_ACTION', 404);
}

[$controller, $method] = $routes[$action];

try {
    $controller::$method($input);
} catch (\Throwable $e) {
    error_log('[api] ' . $action . ': ' . $e->getMessage());
    \App\Logger::error($action, $e->getMessage());
    Response::error('Ichki server xatoligi', 'SERVER_ERROR', 500);
}
