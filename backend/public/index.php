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
use App\Controllers\DocsController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReportsController;
use App\Controllers\SupportController;
use App\Controllers\SurveyController;
use App\Controllers\TestController;

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

Cors::handle();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Response::error('Faqat POST so\'rovlar qabul qilinadi', 'METHOD_NOT_ALLOWED', 405);
}

$raw = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    Response::error('So\'rov tanasi noto\'g\'ri JSON', 'BAD_REQUEST', 400);
}

$action = (string) ($input['action'] ?? '');

/** @var array<string, array{0: class-string, 1: string}> $routes */
$routes = [
    'login' => [AuthController::class, 'login'],
    'logout' => [AuthController::class, 'logout'],
    'changePassword' => [AuthController::class, 'changePassword'],
    'getProfileRu' => [AuthController::class, 'getProfileRu'],

    'checkStatus' => [ProfileController::class, 'checkStatus'],
    'updateProfilePhoto' => [ProfileController::class, 'updateProfilePhoto'],

    'submitSupport' => [SupportController::class, 'submit'],

    'getDocuments' => [DocsController::class, 'getDocuments'],
    'markDocRead' => [DocsController::class, 'markDocRead'],
    'addDocument' => [DocsController::class, 'add'],
    'editDocument' => [DocsController::class, 'edit'],
    'deleteDocument' => [DocsController::class, 'delete'],

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

    'getSupportRequests' => [ReportsController::class, 'getSupportRequests'],
    'updateSupportIzoh' => [ReportsController::class, 'updateSupportIzoh'],

    'getUsersReport' => [ReportsController::class, 'getUsersReport'],
    'getProgressReport' => [ReportsController::class, 'getProgressReport'],
    'getNotificationsRaw' => [ReportsController::class, 'getNotificationsRaw'],
    'getNotificationReadsRaw' => [ReportsController::class, 'getNotificationReadsRaw'],
    'getSurveyAnswersWide' => [ReportsController::class, 'getSurveyAnswersWide'],
];

if (!isset($routes[$action])) {
    Response::error("Noma'lum amal: {$action}", 'UNKNOWN_ACTION', 404);
}

[$controller, $method] = $routes[$action];

try {
    $controller::$method($input);
} catch (\Throwable $e) {
    error_log('[api] ' . $action . ': ' . $e->getMessage());
    Response::error('Ichki server xatoligi', 'SERVER_ERROR', 500);
}
