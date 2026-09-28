<?php
declare(strict_types=1);

// Sertifikatdagi QR-kod olib keladigan ochiq tekshiruv sahifasi (login
// talab qilinmaydi — sertifikatni ko'rgan har kim haqiqiyligini tekshira
// olishi uchun). Faqat taxmin qilib bo'lmaydigan token (96 bit) bo'yicha
// ishlaydi va minimal ma'lumot ko'rsatadi: raqam, F.I.Sh, filial, sana,
// imzo qo'yuvchi. ?s=1 — filial rahbari QR'i, ?s=2 — komplaens direktori QR'i.

ini_set('display_errors', '0');
error_reporting(E_ALL);
header_remove('X-Powered-By');

require dirname(__DIR__) . '/src/autoload.php';

use App\Config;
use App\Controllers\CertificateController;
use App\Database;
use App\Filials;
use App\Logger;
use App\TestPolicy;
use App\Util;

Config::load();

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'");
header('Cache-Control: no-store');

$token = isset($_GET['t']) ? (string) $_GET['t'] : '';
$signerIndex = (int) ($_GET['s'] ?? 0);

$state = 'invalid';
$rows = [];
$signerText = null;

try {
    if ($token === CertificateController::SAMPLE_TOKEN) {
        $state = 'sample';
    } else {
        $db = Database::connection();
        $found = CertificateController::findByToken($db, $token);
        if ($found) {
            $state = 'valid';
            $cert = $found['certificate'];
            $user = $found['user'];
            $filialKey = ($user['filial'] ?? null) ?: null;
            $rows = [
                'Sertifikat raqami' => CertificateController::number((string) $cert['issued_at'], (int) $cert['test_attempt_id']),
                'F.I.Sh' => Util::fullName($user),
                'Filial' => Filials::labelUz($filialKey) ?? '—',
                'Berilgan sana' => date('d.m.Y', strtotime((string) $cert['issued_at'])),
            ];
            $validUntil = TestPolicy::validUntil($db, (string) $cert['issued_at']);
            if ($validUntil !== null) {
                $rows['Amal qiladi'] = date('d.m.Y', strtotime($validUntil)) . ' gacha';
            }
            $settings = CertificateController::loadSettings($db);
            $signer = null;
            if ($signerIndex === 1 && $filialKey !== null && isset($settings['filials'][$filialKey])) {
                $signer = $settings['filials'][$filialKey];
            } elseif ($signerIndex === 2) {
                $signer = $settings['komplaens'];
            }
            if ($signer) {
                $signerText = trim($signer['title'] . ($signer['name'] !== '' ? ': ' . $signer['name'] : ''));
            }
        }
    }
} catch (\Throwable $e) {
    error_log('[certificate-verify] ' . $e->getMessage());
    Logger::error('certificateVerify', $e->getMessage());
    $state = 'error';
}

if ($state === 'invalid') {
    http_response_code(404);
} elseif ($state === 'error') {
    http_response_code(500);
}

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
[$badgeClass, $title, $subtitle] = match ($state) {
    'valid' => ['ok', 'Sertifikat haqiqiy', "«Korrupsiyaga qarshi kurashish» o‘quv kursi bo‘yicha berilgan"],
    'sample' => ['warn', 'Bu sertifikat namunasi', 'Namuna faqat dizaynni ko‘rish uchun — u hech kimga berilmagan.'],
    'error' => ['bad', 'Tekshirib bo‘lmadi', 'Serverda xatolik yuz berdi. Birozdan so‘ng qayta urinib ko‘ring.'],
    default => ['bad', 'Sertifikat topilmadi', 'Bunday sertifikat mavjud emas, uning muddati tugagan yoki u bekor qilingan.'],
};
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sertifikatni tekshirish</title>
<style>
  :root{ --blue:#004391; --ink:#16223A; --muted:#5A6A83; --tint:#EDF2F9; --line:#C9D6E8; }
  *{ box-sizing:border-box; margin:0; padding:0; }
  body{ font-family:"Segoe UI", Roboto, Arial, sans-serif; background:var(--tint); color:var(--ink);
    min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px; }
  .card{ background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(0,48,107,.12); width:100%; max-width:480px; overflow:hidden; }
  .top{ background:linear-gradient(160deg,#0A5BC0 0%,var(--blue) 45%,#00306B 100%); color:#fff; padding:18px 24px; font-size:14px; }
  .top b{ display:block; font-size:17px; margin-top:2px; }
  .body{ padding:24px; }
  .badge{ display:flex; align-items:center; gap:12px; margin-bottom:6px; }
  .badge .ico{ width:40px; height:40px; border-radius:50%; flex:none; display:flex; align-items:center; justify-content:center; color:#fff; font-size:22px; font-weight:700; }
  .ok .ico{ background:#1E9E5A; } .warn .ico{ background:#D98A00; } .bad .ico{ background:#D2463C; }
  h1{ font-size:20px; }
  .sub{ color:var(--muted); font-size:14px; margin:4px 0 18px 52px; }
  dl{ border-top:1px solid var(--line); }
  .row{ display:flex; justify-content:space-between; gap:16px; padding:10px 0; border-bottom:1px solid var(--line); font-size:14px; }
  dt{ color:var(--muted); } dd{ font-weight:600; text-align:right; }
  .signer{ margin-top:16px; font-size:13px; color:var(--muted); }
  .signer b{ color:var(--ink); }
</style>
</head>
<body>
<div class="card">
  <div class="top">O‘zbektelekom AK<b>Sertifikatni tekshirish</b></div>
  <div class="body">
    <div class="badge <?= $badgeClass ?>">
      <div class="ico"><?= $state === 'valid' ? '&#10003;' : '!' ?></div>
      <h1><?= $h($title) ?></h1>
    </div>
    <p class="sub"><?= $h($subtitle) ?></p>
<?php if ($rows): ?>
    <dl>
<?php foreach ($rows as $label => $value): ?>
      <div class="row"><dt><?= $h($label) ?></dt><dd><?= $h($value) ?></dd></div>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
<?php if ($signerText !== null): ?>
    <p class="signer">Imzolagan: <b><?= $h($signerText) ?></b></p>
<?php endif; ?>
  </div>
</div>
</body>
</html>
