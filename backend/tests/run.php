<?php
declare(strict_types=1);

/**
 * Avtomatik testlar — asosiy oqimlarni haqiqiy PHP serveri va MySQL bilan
 * tekshiradi (Composer/PHPUnit shart emas):
 *
 *   php backend/tests/run.php            # hammasi
 *   php backend/tests/run.php --keep     # test bazasini oxirida o'chirmaslik
 *
 * backend/.env'dagi MySQL ulanishidan foydalanib ALOHIDA vaqtinchalik baza
 * (standart: kompaniya_anticor_test, TEST_DB_NAME bilan o'zgartiriladi)
 * yaratadi, schema.sql'ni yuklaydi, o'rnatilgan PHP serverini bo'sh portda
 * ishga tushiradi va HTTP orqali API'ni sinaydi. Ishchi bazaga tegmaydi.
 */

$root = dirname(__DIR__, 2);
require $root . '/backend/src/autoload.php';

use App\Config;
use App\QrCode;

Config::load();

$keep = in_array('--keep', $argv, true);
$testDb = getenv('TEST_DB_NAME') ?: 'kompaniya_anticor_test';
if (!preg_match('/^[A-Za-z0-9_]+$/', $testDb) || $testDb === Config::get('DB_NAME', 'kompaniya_anticor')) {
    fwrite(STDERR, "TEST_DB_NAME noto'g'ri yoki ishchi baza bilan bir xil: {$testDb}\n");
    exit(2);
}

// ---------------------------------------------------------------------
// Natijalar
// ---------------------------------------------------------------------
$passed = 0;
$failed = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  \u{2713} {$name}\n";
    } else {
        $failed[] = $name;
        echo "  \u{2717} {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}
function section(string $title): void
{
    echo "\n{$title}\n";
}

// ---------------------------------------------------------------------
// Test bazasi
// ---------------------------------------------------------------------
$host = Config::get('DB_HOST', 'localhost');
$port = Config::get('DB_PORT', '3306');
$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", Config::get('DB_USER', 'root'), Config::get('DB_PASS', '') ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("DROP DATABASE IF EXISTS `{$testDb}`");
$pdo->exec("CREATE DATABASE `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$testDb}`");
$pdo->exec("SET time_zone = '+05:00'");

$schema = (string) file_get_contents($root . '/schema.sql');
$schema = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $schema);
foreach (preg_split('/;\s*(\r?\n|$)/', $schema) as $statement) {
    $lines = array_filter(preg_split('/\r?\n/', $statement), static fn ($l) => !preg_match('/^\s*(--|$)/', $l));
    if ($lines) {
        $pdo->exec(implode("\n", $lines));
    }
}

$hash = password_hash('Test@12345', PASSWORD_BCRYPT);
$addUser = $pdo->prepare('INSERT INTO users (login, password_hash, familiya, ism, filial, rol) VALUES (?, ?, ?, ?, ?, ?)');
foreach ([
    ['boss', 'Bosh', 'Admin', null, 'super-admin'],
    ['admin', 'Adminov', 'Anvar', 'markaziy', 'anticor-admin'],
    ['admin2', 'Ikkinchi', 'Admin', 'markaziy', 'anticor-admin'],
    ['viewer', 'Kuzatuvchi', 'Kamol', 'shimoliy', 'anticor'],
    ['u1', 'Birinchi', 'Xodim', 'sharqiy', 'user'],
    ['u2', 'Ikkinchi', 'Xodim', 'sharqiy', 'user'],
    ['u3', 'Uchinchi', 'Xodim', 'janubiy', 'user'],
] as [$login, $fam, $ism, $filial, $rol]) {
    $addUser->execute([$login, $hash, $fam, $ism, $filial, $rol]);
}
$ids = $pdo->query('SELECT login, id FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);
$pdo->exec("DELETE FROM test_questions");
$q = $pdo->prepare('INSERT INTO test_questions (savol, variant_a, variant_b, variant_c, variant_d, togri_javob) VALUES (?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= 5; $i++) {
    $q->execute(["Savol {$i}", "To'g'ri {$i}", "Xato {$i}", "Xato2 {$i}", "Xato3 {$i}", "To'g'ri {$i}"]);
}

// ---------------------------------------------------------------------
// Server
// ---------------------------------------------------------------------
$sock = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
fclose($sock);
$base = "http://127.0.0.1:{$port}";
$env = array_merge(getenv() ?: [], [
    'DB_NAME' => $testDb,
    'PUBLIC_BASE_URL' => "{$base}/backend/public",
    'FORCE_HTTPS' => 'false',
    'TEST_MAX_ATTEMPTS' => '1',
]);
$logFile = sys_get_temp_dir() . '/anticor-test-server-' . $port . '.log';
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root], [
    0 => ['pipe', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a'],
], $pipes, $root, $env);
for ($i = 0; $i < 50; $i++) {
    $c = @fsockopen('127.0.0.1', $port);
    if ($c) {
        fclose($c);
        break;
    }
    usleep(100000);
}

register_shutdown_function(static function () use (&$server, $pdo, $testDb, $keep, $logFile): void {
    if (is_resource($server)) {
        proc_terminate($server);
    }
    if (!$keep) {
        $pdo->exec("DROP DATABASE IF EXISTS `{$testDb}`");
        @unlink($logFile);
    } else {
        echo "\nTest bazasi saqlab qolindi: {$testDb}; server jurnali: {$logFile}\n";
    }
});

// ---------------------------------------------------------------------
// HTTP mijoz (cookie bilan)
// ---------------------------------------------------------------------
final class Client
{
    private array $cookies = [];

    public function __construct(private string $base)
    {
    }

    /** @return array{0:int,1:string} [status, body] */
    public function request(string $method, string $path, ?string $body = null): array
    {
        $headers = ["Content-Type: application/json"];
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(
                static fn ($k, $v) => "{$k}={$v}", array_keys($this->cookies), $this->cookies
            ));
        }
        $ctx = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
            'ignore_errors' => true, 'timeout' => 30,
        ]]);
        $response = @file_get_contents($this->base . $path, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                $status = (int) $m[1];
            } elseif (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $h, $m)) {
                if ($m[2] === '' || $m[2] === 'deleted') {
                    unset($this->cookies[$m[1]]);
                } else {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
        }
        return [$status, (string) $response];
    }

    public function api(string $action, array $data = []): array
    {
        [, $body] = $this->request('POST', '/backend/public/index.php', json_encode(['action' => $action] + $data));
        return json_decode($body, true) ?: ['success' => false, 'raw' => substr($body, 0, 300)];
    }

    public function login(string $login, string $parol = 'Test@12345'): array
    {
        return $this->api('login', ['login' => $login, 'parol' => $parol]);
    }
}

$c = static fn (): Client => new Client($base);
$answers = static function (Client $client, bool $correct): array {
    $qs = $client->api('getTestQuestions')['questions'] ?? [];
    return array_map(static fn ($q) => ['id' => $q['id'], 'letter' => $correct ? 'A' : 'B'], $qs);
};

echo "Test bazasi: {$testDb}, server: {$base}\n";

// ---------------------------------------------------------------------
section('QR-kod generatori');
$m = QrCode::matrix('A');
check('1-versiya: 21×21', count($m) === 21 && count($m[0]) === 21);
$m = QrCode::matrix(str_repeat('x', 150));
check('uzun matn: 8–10-versiya', count($m) >= 49 && count($m) <= 57, (string) count($m));
check('finder pattern burchagi qora', $m[0][0] === true && $m[6][6] === true && $m[1][1] === false);

// ---------------------------------------------------------------------
section('Login va rollar');
$admin = $c();
check("noto'g'ri parol rad etiladi", ($admin->login('admin', 'xato')['success'] ?? true) === false);
check('anticor-admin kiradi', ($admin->login('admin')['success'] ?? false) === true);
$users = $admin->api('getUsersList')['users'] ?? [];
check("super-admin xodimlar ro'yxatida yo'q", !in_array('boss', array_column($users, 'login'), true) && count($users) === 6);
$u1 = $c();
$u1->login('u1');
check('oddiy xodim statistikani ko\'ra olmaydi', ($u1->api('getStats')['code'] ?? '') === 'FORBIDDEN');
check('oddiy xodim amallar jurnalini ko\'ra olmaydi', ($u1->api('getAuditLog')['code'] ?? '') === 'FORBIDDEN');
$viewer = $c();
$viewer->login('viewer');
check("anticor roli statistikani ko'radi", ($viewer->api('getStats')['success'] ?? false) === true);
check("anticor roli amallar jurnalini ko'ra olmaydi", ($viewer->api('getAuditLog')['code'] ?? '') === 'FORBIDDEN');
$r = $admin->api('editEmployee', ['id' => $ids['admin2'], 'familiya' => 'Ikkinchi', 'ism' => 'Admin', 'filial' => 'markaziy', 'lavozim' => 'Bosh mutaxassis', 'rol' => 'anticor-admin']);
check('anticor-admin boshqa anticor-admin\'ni tahrirlay oladi', ($r['success'] ?? false) === true, json_encode($r));
$r = $admin->api('editEmployee', ['id' => $ids['boss'], 'familiya' => 'X', 'ism' => 'Y', 'rol' => 'super-admin']);
check('anticor-admin super-admin\'ni tahrirlay olmaydi', ($r['code'] ?? '') === 'FORBIDDEN');

// ---------------------------------------------------------------------
section('Test: 1 ta urinish va qayta topshirish ruxsati');
$info = $u1->api('getTestQuestions');
check('boshida 0/1 urinish', ($info['attemptsUsed'] ?? -1) === 0 && ($info['maxAttempts'] ?? 0) === 1);
$r = $u1->api('submitTest', ['answers' => $answers($u1, false)]);
check("noto'g'ri javoblar — o'tmadi", ($r['success'] ?? false) && $r['passed'] === false);
check('ikkinchi urinish taqiqlangan', ($u1->api('submitTest', ['answers' => $answers($u1, true)])['code'] ?? '') === 'ATTEMPTS_EXHAUSTED');
check('anticor roli ruxsat bera olmaydi', ($viewer->api('grantTestRetake', ['userId' => $ids['u1']])['code'] ?? '') === 'FORBIDDEN');
check('admin ruxsat beradi', ($admin->api('grantTestRetake', ['userId' => $ids['u1']])['success'] ?? false) === true);
check('ikkinchi ruxsat berilmaydi (ishlatilmagan)', ($admin->api('grantTestRetake', ['userId' => $ids['u1']])['code'] ?? '') === 'RETAKE_PENDING');
$notes = $u1->api('getMyNotifications')['notifications'] ?? [];
check('xodimga ruxsat haqida xabarnoma keldi', (bool) array_filter($notes, static fn ($n) => str_contains($n['text'], 'qayta topshirishga ruxsat')));
$r = $u1->api('submitTest', ['answers' => $answers($u1, true)]);
check("qayta topshirib o'tdi va sertifikat oldi", ($r['passed'] ?? false) === true && ($r['certificate'] ?? false) === true, json_encode($r));
check("o'tgan xodim qayta topshira olmaydi", ($u1->api('submitTest', ['answers' => $answers($u1, true)])['code'] ?? '') === 'ALREADY_PASSED');
check("o'tgan xodimga ruxsat berilmaydi", ($admin->api('grantTestRetake', ['userId' => $ids['u1']])['code'] ?? '') === 'ALREADY_PASSED');
$progress = $admin->api('getProgressReport')['rows'] ?? [];
$row = array_values(array_filter($progress, static fn ($p) => str_starts_with($p['fish'], 'Birinchi')))[0] ?? [];
check('Progress: ruxsat va qayta topshirish belgilangan', ($row['qaytaRuxsat'] ?? '') !== '' && str_contains($row['qaytaTopshirish'] ?? '', "o'tdi"));

// ---------------------------------------------------------------------
section('Sertifikat va QR tekshiruvi');
[$status, $pdf] = $u1->request('GET', '/backend/public/certificate-download.php');
check('sertifikat PDF yuklanadi', $status === 200 && str_starts_with($pdf, '%PDF'), (string) $status);
[$status] = $viewer->request('GET', '/backend/public/certificate-download.php?userId=' . $ids['u1']);
check("anticor roli xodim sertifikatini ko'radi", $status === 200);
[$status] = $u1->request('GET', '/backend/public/certificate-download.php?userId=' . $ids['u2']);
check("xodim boshqaning sertifikatini ko'ra olmaydi", $status === 403);
$token = (string) $pdo->query("SELECT verify_token FROM `{$testDb}`.certificates WHERE user_id = {$ids['u1']}")->fetchColumn();
[$status, $html] = $c()->request('GET', "/backend/public/certificate-verify.php?t={$token}&s=1");
check('QR sahifasi: sertifikat haqiqiy', $status === 200 && str_contains($html, 'Sertifikat haqiqiy'));
[$status] = $c()->request('GET', '/backend/public/certificate-verify.php?t=' . str_repeat('0', 24));
check("QR sahifasi: soxta token — topilmadi", $status === 404);
check("anticor roli sertifikatni bekor qila olmaydi", ($viewer->api('revokeCertificates', ['ids' => [$ids['u1']]])['code'] ?? '') === 'FORBIDDEN');
check('admin sertifikatni bekor qiladi', ($admin->api('revokeCertificates', ['ids' => [$ids['u1']]])['deleted'] ?? 0) === 1);
[$status] = $u1->request('GET', '/backend/public/certificate-download.php');
check('bekor qilingan sertifikat yuklanmaydi', $status === 404);
[$status] = $c()->request('GET', "/backend/public/certificate-verify.php?t={$token}&s=1");
check("bekor qilingan sertifikat QR'da amal qilmaydi", $status === 404);

// ---------------------------------------------------------------------
section('Muddatlar va yillik qayta attestatsiya');
check("noto'g'ri sana rad etiladi", ($admin->api('saveTestSettings', ['deadline' => '2026-02-31', 'validityMonths' => 12])['code'] ?? '') === 'VALIDATION_ERROR');
$deadline = date('Y-m-d', strtotime('+30 days'));
$r = $admin->api('saveTestSettings', ['deadline' => $deadline, 'validityMonths' => 12]);
check('muddatlar saqlanadi', ($r['deadline'] ?? '') === $deadline && ($r['validityMonths'] ?? 0) === 12);
$pdo->exec("INSERT INTO `{$testDb}`.test_attempts (user_id, attempted_at, points, max_points, percent, passed)
            VALUES ({$ids['u2']}, DATE_SUB(NOW(), INTERVAL 2 YEAR), 5, 5, 100, 1)");
$u2 = $c();
$u2->login('u2');
$info = $u2->api('getTestQuestions');
check('muddati tugagan: qayta attestatsiya, yangi urinish', ($info['renewal'] ?? false) === true && $info['attemptsUsed'] === 0 && $info['certificate'] === false);
check('test sahifasida muddat ko\'rsatiladi', ($info['deadline'] ?? '') === $deadline);
$stats = $admin->api('getStats');
$emp = array_values(array_filter($stats['employees'] ?? [], static fn ($e) => $e['id'] === (int) $ids['u2']))[0] ?? [];
check('statistikada "qayta attestatsiya", test topshirilmagan', ($emp['renewal'] ?? false) === true && ($emp['testTaken'] ?? true) === false);
check("statistikada filial bor", ($emp['filial'] ?? '') === 'sharqiy');
$r = $u2->api('submitTest', ['answers' => $answers($u2, true)]);
check("qayta attestatsiyadan o'tdi — yangi sertifikat", ($r['passed'] ?? false) && ($r['certificate'] ?? false));
$admin->api('saveTestSettings', ['deadline' => $deadline, 'validityMonths' => 0]);
check('muddatsiz rejimda eski natija ham amal qiladi', ($u2->api('getTestQuestions')['renewal'] ?? true) === false);

// ---------------------------------------------------------------------
section('Eslatmalar');
$r = $admin->api('sendReminders', ['types' => ['test', 'doc'], 'filial' => 'janubiy', 'preview' => true]);
check("oldindan ko'rish: janubiy filialda 1 ta xodim", ($r['counts']['test'] ?? -1) === 1 && ($r['counts']['doc'] ?? -1) === 1, json_encode($r));
$r = $admin->api('sendReminders', ['types' => ['test'], 'filial' => '']);
check('eslatma yuborildi', ($r['success'] ?? false) && ($r['counts']['test'] ?? 0) >= 1);
$u3 = $c();
$u3->login('u3');
check('xodimga eslatma keldi', (bool) array_filter($u3->api('getMyNotifications')['notifications'] ?? [], static fn ($n) => str_contains($n['text'], 'Eslatma')));
check("super-admin'ga eslatma bormaydi", !$pdo->query("SELECT COUNT(*) FROM `{$testDb}`.notifications WHERE FIND_IN_SET('boss', target_value)")->fetchColumn());

// ---------------------------------------------------------------------
section('Amallar jurnali');
$boss = $c();
$boss->login('boss');
$boss->api('setTestActive', ['active' => true]);
$log = $admin->api('getAuditLog')['entries'] ?? [];
$actions = array_column($log, 'amal');
foreach (['employee_edit', 'retake_grant', 'cert_revoke', 'test_settings', 'reminders_send'] as $a) {
    check("jurnalda: {$a}", in_array($a, $actions, true));
}
check('super-admin amallari yozilmaydi', !in_array('Bosh Admin', array_column($log, 'kim'), true));
$edit = array_values(array_filter($log, static fn ($e) => $e['amal'] === 'employee_edit'))[0] ?? [];
check("tahrirda o'zgargan maydonlar yozilgan", str_contains($edit['tafsilot'] ?? '', 'lavozim'));

// ---------------------------------------------------------------------
section('Migratsiyalar');
$migrations = $pdo->query("SELECT id FROM `{$testDb}`.schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
check('bir martalik migratsiyalar belgilangan', in_array('once:roles-v4', $migrations, true) && count($migrations) > 3);
$enum = $pdo->query("SHOW COLUMNS FROM `{$testDb}`.users LIKE 'rol'")->fetch()['Type'] ?? '';
check("rollar ro'yxati to'g'ri (4 ta)", $enum === "enum('user','anticor-admin','anticor','super-admin')", $enum);

// ---------------------------------------------------------------------
$total = $passed + count($failed);
echo "\n" . str_repeat('-', 50) . "\n";
if ($failed) {
    echo "NATIJA: {$passed}/{$total} o'tdi, " . count($failed) . " ta xato:\n  - " . implode("\n  - ", $failed) . "\n";
    exit(1);
}
echo "NATIJA: hammasi o'tdi ({$total}/{$total})\n";
exit(0);
