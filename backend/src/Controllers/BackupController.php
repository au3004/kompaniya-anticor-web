<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Response;

/**
 * MySQL bazasi va profil rasmlarining zaxira nusxasini oladi. Fayllar
 * `backend/backups/` papkasida saqlanadi — bu papka `backend/.htaccess`
 * orqali (public/ dan tashqari hamma narsa kabi) web orqali to'g'ridan-to'g'ri
 * ochilishdan himoyalangan, faqat backup-download.php orqali (avtorizatsiya
 * bilan) yuklab olinadi.
 *
 * `performBackup()` metodi ham shu yerdagi HTTP amali (createBackup), ham
 * jadval (cron/Task Scheduler) orqali ishga tushiriladigan CLI skripti
 * (backend/scripts/backup.php) tomonidan ishlatiladi.
 */
final class BackupController
{
    public static function create(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $result = self::performBackup();
        if (!$result['success']) {
            Response::error($result['error'], 'BACKUP_FAILED', 500);
        }

        Response::success(['name' => $result['name'], 'sizeBytes' => $result['sizeBytes']]);
    }

    public static function list(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $dir = self::backupDir();
        $entries = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $path = $dir . '/' . $name;
                if (!is_dir($path)) {
                    continue;
                }
                $entries[] = [
                    'name' => $name,
                    'sizeBytes' => self::dirSize($path),
                    'createdAt' => date('d.m.Y G:i', filemtime($path) ?: time()),
                    'mtime' => filemtime($path) ?: 0,
                ];
            }
        }
        usort($entries, static fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);
        foreach ($entries as &$e) {
            unset($e['mtime']);
        }

        Response::success(['backups' => $entries]);
    }

    /**
     * @return array{success: bool, name?: string, sizeBytes?: int, error?: string}
     */
    public static function performBackup(): array
    {
        $dir = self::backupDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return ['success' => false, 'error' => "Zaxira papkasini yaratib bo'lmadi: {$dir}"];
        }

        $stamp = date('Y-m-d_His');
        $backupPath = $dir . '/' . $stamp;
        if (!mkdir($backupPath, 0750, true)) {
            return ['success' => false, 'error' => "Zaxira papkasini yaratib bo'lmadi: {$backupPath}"];
        }

        $dbResult = self::dumpDatabase($backupPath . '/db.sql');
        if (!$dbResult['success']) {
            self::deleteDir($backupPath);
            return $dbResult;
        }

        self::zipUploads($backupPath . '/uploads.zip');

        self::cleanupOld($dir);

        return [
            'success' => true,
            'name' => $stamp,
            'sizeBytes' => self::dirSize($backupPath),
        ];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private static function dumpDatabase(string $outFile): array
    {
        $mysqldump = Config::get('MYSQLDUMP_PATH', 'mysqldump');
        $host = Config::get('DB_HOST', 'localhost');
        $port = Config::get('DB_PORT', '3306');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');
        $name = Config::get('DB_NAME', 'kompaniya_anticor');

        $errFile = $outFile . '.err';

        // Parolni buyruq qatorida ko'rinib qolmasligi (masalan `ps aux` orqali)
        // uchun muhit o'zgaruvchisi (MYSQL_PWD) sifatida uzatamiz.
        putenv('MYSQL_PWD=' . $pass);
        $cmd = escapeshellarg($mysqldump)
            . ' --host=' . escapeshellarg($host)
            . ' --port=' . escapeshellarg($port)
            . ' --user=' . escapeshellarg($user)
            . ' --single-transaction --routines --triggers '
            . escapeshellarg($name)
            . ' > ' . escapeshellarg($outFile)
            . ' 2> ' . escapeshellarg($errFile);
        exec($cmd, $unusedOutput, $exitCode);
        putenv('MYSQL_PWD');

        $errText = is_file($errFile) ? trim((string) file_get_contents($errFile)) : '';
        if (is_file($errFile)) {
            @unlink($errFile);
        }

        if ($exitCode !== 0 || !is_file($outFile) || filesize($outFile) === 0) {
            if (is_file($outFile)) {
                @unlink($outFile);
            }
            $detail = $errText !== '' ? $errText : "mysqldump ishga tushmadi (chiqish kodi: {$exitCode})";
            return ['success' => false, 'error' => $detail];
        }

        return ['success' => true];
    }

    private static function zipUploads(string $zipPath): void
    {
        $uploadsDir = dirname(__DIR__, 2) . '/public/uploads/photos';
        if (!is_dir($uploadsDir) || !class_exists(\ZipArchive::class)) {
            return;
        }

        $files = array_values(array_diff(scandir($uploadsDir) ?: [], ['.', '..', '.gitkeep']));
        if (!$files) {
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return;
        }
        foreach ($files as $file) {
            $full = $uploadsDir . '/' . $file;
            if (is_file($full)) {
                $zip->addFile($full, $file);
            }
        }
        $zip->close();
    }

    private static function cleanupOld(string $dir): void
    {
        $keepDays = Config::int('BACKUP_KEEP_DAYS', 30);
        $cutoff = time() - $keepDays * 86400;

        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            if (is_dir($path) && (filemtime($path) ?: time()) < $cutoff) {
                self::deleteDir($path);
            }
        }
    }

    private static function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            is_dir($path) ? self::deleteDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function dirSize(string $dir): int
    {
        $size = 0;
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            $size += is_dir($path) ? self::dirSize($path) : (int) filesize($path);
        }
        return $size;
    }

    public static function backupDir(): string
    {
        $configured = Config::get('BACKUP_DIR', '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        return dirname(__DIR__, 2) . '/backups';
    }
}
