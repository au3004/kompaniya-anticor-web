<?php
declare(strict_types=1);

/**
 * CLI zaxira nusxa skripti — MySQL bazasi (mysqldump) va profil rasmlarini
 * (uploads.zip) `backend/backups/<sana_vaqt>/` papkasiga saqlaydi, so'ng
 * BACKUP_KEEP_DAYS (standart 30 kun)dan eski nusxalarni avtomatik o'chiradi.
 *
 * Ishlatish (qo'lda tekshirish uchun):
 *   php backend/scripts/backup.php
 *
 * Avtomatik (kunlik) ishga tushirish uchun:
 *   - Windows (XAMPP): Task Scheduler'da yangi vazifa yarating, dastur sifatida
 *     "C:\xampp\php\php.exe" ni, argument sifatida ushbu faylning to'liq
 *     yo'lini ko'rsating, kuniga bir marta (masalan har kuni soat 03:00) ishga
 *     tushirishni belgilang.
 *   - Linux/cPanel hosting: crontab'ga qo'shing, masalan:
 *       0 3 * * * php /full/path/backend/scripts/backup.php >> /full/path/backend/backups/cron.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu skript faqat buyruqlar qatoridan (CLI) ishga tushiriladi.\n");
}

require dirname(__DIR__) . '/src/autoload.php';

use App\Config;
use App\Controllers\BackupController;

Config::load();

$result = BackupController::performBackup();

if (!$result['success']) {
    fwrite(STDERR, '[backup] XATOLIK: ' . $result['error'] . "\n");
    exit(1);
}

$sizeMb = round($result['sizeBytes'] / 1024 / 1024, 2);
echo "[backup] Muvaffaqiyatli: {$result['name']} ({$sizeMb} MB)\n";
exit(0);
