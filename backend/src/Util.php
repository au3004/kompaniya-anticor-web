<?php
declare(strict_types=1);

namespace App;

final class Util
{
    public static function photoUrl(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }
        if (preg_match('#^https?://#i', $relativePath)) {
            return $relativePath;
        }
        $base = rtrim((string) Config::get('PUBLIC_BASE_URL', ''), '/');
        return $base . '/' . ltrim($relativePath, '/');
    }

    public static function fullName(array $user): string
    {
        return trim(implode(' ', array_filter([
            $user['familiya'] ?? '',
            $user['ism'] ?? '',
            $user['otasining_ismi'] ?? '',
        ])));
    }

    /**
     * Eski o'rnatishlarda admin schema.sql'ga keyinroq qo'shilgan jadval/ustunni
     * qo'lda migratsiya qilishni unutib qo'yishi mumkin — shu bilan butun
     * amal ishlamay qolishining oldini olish uchun, shu yerning o'zida
     * (zararsiz, IF NOT EXISTS bilan) CREATE/ALTER'ni qayta bajarib qo'yamiz.
     */
    public static function ensureSchema(\PDO $db, string $ddlSql): void
    {
        try {
            $db->exec($ddlSql);
        } catch (\Throwable $e) {
            // Bajara olmasak (masalan huquq yetishmasa), pastdagi asosiy so'rov
            // baribir o'zining aniq xatoligini beradi — bu yerda indamaymiz.
        }
    }
}
