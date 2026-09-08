<?php
declare(strict_types=1);

namespace App;

/**
 * Oddiy IP + amal asosidagi so'rovlar chastotasini cheklash — login uchun
 * mavjud bo'lgan login_attempts mexanizmidan tashqari, avtorizatsiya talab
 * qilmaydigan amallar (anonim so'rovnoma, parolni tiklash so'rovi va h.k.)
 * cheksiz marta chaqirilib, ma'lumotlar bazasini spam bilan to'ldirib
 * yuborilishining oldini oladi.
 *
 * ESLATMA: IP manzili $_SERVER['REMOTE_ADDR']'dan olinadi — agar kelajakda
 * reverse proxy (Nginx) yoki CDN orqasiga joylashtirilsa, buni ishonchli
 * proxy ro'yxatidan tekshirilgan X-Forwarded-For qiymatiga almashtirish kerak.
 */
final class RateLimit
{
    private const DDL = 'CREATE TABLE IF NOT EXISTS rate_limits (
        bucket_key   VARCHAR(191) PRIMARY KEY,
        count        INT NOT NULL DEFAULT 0,
        window_start DATETIME NOT NULL
    ) ENGINE=InnoDB';

    /**
     * $maxPerWindow marotabadan ko'p chaqirilsa, 429 xatolik bilan javob
     * qaytarib so'rovni to'xtatadi (Response::error ichida exit qilinadi).
     */
    public static function enforce(string $action, int $maxPerWindow, int $windowSeconds): void
    {
        $ip = self::clientIp();
        $bucketKey = $action . ':' . $ip;

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $stmt = $db->prepare('SELECT count, window_start FROM rate_limits WHERE bucket_key = :k');
        $stmt->execute(['k' => $bucketKey]);
        $row = $stmt->fetch();

        $now = time();
        if (!$row || strtotime((string) $row['window_start']) < $now - $windowSeconds) {
            // Yangi oyna boshlanadi.
            $upsert = $db->prepare(
                'INSERT INTO rate_limits (bucket_key, count, window_start) VALUES (:k, 1, :now)
                 ON DUPLICATE KEY UPDATE count = 1, window_start = :now2'
            );
            $upsert->execute(['k' => $bucketKey, 'now' => date('Y-m-d H:i:s', $now), 'now2' => date('Y-m-d H:i:s', $now)]);
            return;
        }

        if ((int) $row['count'] >= $maxPerWindow) {
            Response::error(
                "Juda ko'p so'rov yuborildi. Birozdan so'ng qayta urinib ko'ring.",
                'RATE_LIMITED',
                429
            );
        }

        $upd = $db->prepare('UPDATE rate_limits SET count = count + 1 WHERE bucket_key = :k');
        $upd->execute(['k' => $bucketKey]);
    }

    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
