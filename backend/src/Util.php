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

    /**
     * Eski (2 pog'onali: user/admin/gl-admin) rol tizimidan yangi, bo'limlarga
     * ajratilgan rol tizimiga (7 ta rol) o'zini o'zi bir marta ko'chiradi —
     * har bir so'rovda tekshiriladi, lekin faqat eski uslubdagi rol qolgan
     * bo'lsagina haqiqiy ALTER/UPDATE ishga tushadi (aks holda bitta arzon
     * SELECT bilan cheklanadi).
     *
     * Xaritalash: gl-admin -> anticor-admin (yagona eski "bosh admin" yangi
     * "Korrupsiyaga qarshi kurashish" bo'limining to'liq boshqaruvchisiga
     * aylanadi); admin va user -> user (ehtiyotkorlik uchun hech kimga
     * avtomatik ravishda ortiqcha huquq berilmaydi — kerakli xodimlarga
     * anticor-admin keyinroq Xodimlar bo'limidan qo'lda yangi rol beradi).
     */
    public static function ensureRoleMigration(\PDO $db): void
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE rol IN ('admin','gl-admin')");
            if ((int) $stmt->fetchColumn() === 0) {
                return;
            }
            $db->exec(
                "ALTER TABLE users MODIFY COLUMN rol " .
                "ENUM('user','admin','gl-admin','anticor-admin','anticor','hr-admin','hr','super-admin','rahbariyat') " .
                "NOT NULL DEFAULT 'user'"
            );
            $db->exec(
                "UPDATE users SET rol = CASE rol " .
                "WHEN 'gl-admin' THEN 'anticor-admin' " .
                "WHEN 'admin' THEN 'user' " .
                "ELSE rol END " .
                "WHERE rol IN ('admin','gl-admin')"
            );
            $db->exec(
                "ALTER TABLE users MODIFY COLUMN rol " .
                "ENUM('user','anticor-admin','anticor','hr-admin','hr','super-admin','rahbariyat') " .
                "NOT NULL DEFAULT 'user'"
            );
        } catch (\Throwable $e) {
            // Best-effort: bajara olmasak, rol tekshiruvlari eski qiymatlar
            // bilan ishlashda davom etadi va shu joyda aniq xato chiqadi.
        }
    }

    /**
     * "xarid" rolini (Xaridlar reyestri) users.rol ENUM'iga bir martalik
     * qo'shadi — eski (allaqachon ishlab turgan) bazalarda bu qiymat
     * ENUM'da yo'q bo'lgani uchun, aks holda rol='xarid' bilan yozish/
     * o'qishga urinish MySQL xatoligiga olib kelardi. Har ulanishda arzon
     * SHOW COLUMNS bilan tekshiriladi, faqat kerak bo'lgandagina ALTER
     * ishga tushadi.
     */
    public static function ensureXaridRole(\PDO $db): void
    {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'rol'");
            $col = $stmt->fetch();
            if ($col && str_contains((string) $col['Type'], "'xarid'")) {
                return;
            }
            $db->exec(
                "ALTER TABLE users MODIFY COLUMN rol " .
                "ENUM('user','anticor-admin','anticor','hr-admin','hr','super-admin','rahbariyat','xarid') " .
                "NOT NULL DEFAULT 'user'"
            );
        } catch (\Throwable $e) {
            // Best-effort — bajarilmasa, "xarid" roli bilan bog'liq amal
            // pastda o'zining aniq DB xatoligini beradi.
        }
    }
}
