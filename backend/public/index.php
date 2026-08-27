<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use App\Config;
use App\Cors;
use App\Response;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DocsController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\SupportController;
use App\Controllers\SurveyController;
use App\Controllers\TestController;

Config::load();
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
