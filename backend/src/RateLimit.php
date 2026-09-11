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
     *
     * Hisoblash va oynani yangilash bitta atomik UPSERT ichida, MySQL'ning
     * o'zida (qatorning o'z lock'i ostida) bajariladi — avval o'qib, keyin
     * alohida yozadigan ikki bosqichli yondashuv parallel so'rovlar
     * o'rtasida poyga holatiga yo'l qo'yardi (xuddi shu naqsh
     * Auth::registerFailedAttempt()da ham topilib tuzatilgan edi): bitta
     * IP bir vaqtning o'zida bir nechta so'rov yuborsa, ularning har biri
     * eski `count` qiymatini o'qib qolib, belgilangan chegaradan ko'proq
     * so'rovni o'tkazib yuborishi mumkin edi.
     */
    public static function enforce(string $action, int $maxPerWindow, int $windowSeconds): void
    {
        $ip = self::clientIp();
        $bucketKey = $action . ':' . $ip;
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $windowCutoff = date('Y-m-d H:i:s', $now - $windowSeconds);

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        // - Oyna eski (window_start < cutoff) bo'lsa: 1 tadan qayta boshlanadi.
        // - Oyna hali amal qilsa: count += 1 (chegaradan oshsa ham baribir
        //   yoziladi — pastda shu YANGILANGAN qiymat tekshirilib, oshib
        //   ketgan bo'lsa rad javobi qaytariladi; bu poyga sharoitida ham
        //   hech bir so'rov "ko'rinmasdan" o'tib ketmasligini kafolatlaydi).
        $upsert = $db->prepare(
            'INSERT INTO rate_limits (bucket_key, count, window_start) VALUES (:k, 1, :now)
             ON DUPLICATE KEY UPDATE
                 count = IF(window_start < :cutoff, 1, count + 1),
                 window_start = IF(window_start < :cutoff2, :now2, window_start)'
        );
        $upsert->execute([
            'k' => $bucketKey,
            'now' => $nowStr,
            'now2' => $nowStr,
            'cutoff' => $windowCutoff,
            'cutoff2' => $windowCutoff,
        ]);

        $stmt = $db->prepare('SELECT count FROM rate_limits WHERE bucket_key = :k');
        $stmt->execute(['k' => $bucketKey]);
        $count = (int) $stmt->fetchColumn();

        if ($count > $maxPerWindow) {
            Response::error(
                "Juda ko'p so'rov yuborildi. Birozdan so'ng qayta urinib ko'ring.",
                'RATE_LIMITED',
                429
            );
        }
    }

    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
