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

    /** Shu so'rov davomida allaqachon bajarilgan migratsiyalar (id => true). */
    private static ?array $appliedMigrations = null;

    /**
     * Eski o'rnatishlarda admin schema.sql'ga keyinroq qo'shilgan jadval/ustunni
     * qo'lda migratsiya qilishni unutib qo'yishi mumkin — shu bilan butun
     * amal ishlamay qolishining oldini olish uchun, shu yerning o'zida
     * (zararsiz, IF NOT EXISTS bilan) CREATE/ALTER'ni bajarib qo'yamiz.
     *
     * Har bir DDL bazada faqat BIR MARTA bajariladi: muvaffaqiyatli bajarilgach
     * uning xeshi schema_migrations jadvaliga yoziladi va keyingi so'rovlarda
     * (bitta arzon SELECT bilan) o'tkazib yuboriladi. DDL matni o'zgarsa —
     * yangi xesh, demak yangi migratsiya sifatida yana bir marta bajariladi.
     * Eslatma: MySQL'da DDL ochiq tranzaksiyani yashirincha yakunlaydi, shuning
     * uchun ensureSchema'ni tranzaksiyadan OLDIN chaqiring.
     */
    public static function ensureSchema(\PDO $db, string $ddlSql): void
    {
        $id = 'ddl:' . md5((string) preg_replace('/\s+/', ' ', trim($ddlSql)));
        if (isset(self::appliedMigrations($db)[$id])) {
            return;
        }
        try {
            $db->exec($ddlSql);
        } catch (\Throwable $e) {
            // Bajara olmasak (masalan huquq yetishmasa), pastdagi asosiy so'rov
            // baribir o'zining aniq xatoligini beradi — bu yerda indamaymiz va
            // keyingi so'rovda yana urinib ko'ramiz (belgilanmaydi).
            return;
        }
        self::markMigrationApplied($db, $id);
    }

    /** $migration'ni bazada bir marta bajaradi (muvaffaqiyatli tugasa belgilanadi). */
    public static function runOnce(\PDO $db, string $name, callable $migration): void
    {
        $id = 'once:' . $name;
        if (isset(self::appliedMigrations($db)[$id])) {
            return;
        }
        try {
            $migration($db);
        } catch (\Throwable $e) {
            return;
        }
        self::markMigrationApplied($db, $id);
    }

    private static function appliedMigrations(\PDO $db): array
    {
        if (self::$appliedMigrations !== null) {
            return self::$appliedMigrations;
        }
        try {
            $ids = $db->query('SELECT id FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            try {
                $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                    id          VARCHAR(80) PRIMARY KEY,
                    applied_at  DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB');
            } catch (\Throwable $e2) {
                // Jadval yaratilmasa, DDL'lar avvalgidek har safar bajariladi.
            }
            $ids = [];
        }
        return self::$appliedMigrations = array_fill_keys($ids, true);
    }

    private static function markMigrationApplied(\PDO $db, string $id): void
    {
        self::$appliedMigrations[$id] = true;
        try {
            $stmt = $db->prepare('INSERT IGNORE INTO schema_migrations (id) VALUES (:id)');
            $stmt->execute(['id' => $id]);
        } catch (\Throwable $e) {
            // Belgilab bo'lmasa — keyingi so'rovda yana (zararsiz) bajariladi.
        }
    }

    /**
     * Eski (2 pog'onali: user/admin/gl-admin) rol tizimidan yangi, bo'limlarga
     * ajratilgan rol tizimiga o'zini o'zi bir marta ko'chiradi (Database'da
     * runOnce orqali — bazada faqat bir marta tekshiriladi).
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
     * "hr-admin", "hr", "rahbariyat" va "xarid" rollari olib tashlangan —
     * ularning barcha vakolati anticor-adminga o'tkazilgan. Eski
     * o'rnatishlarda bu rollardagi mavjud xodimlarni bir martalik
     * anticor-adminga ko'chiradi, so'ng ENUM'ni qisqartiradi va endi
     * ishlatilmaydigan "kelishuv (approval)" jadvallarini o'chiradi (Database'da
     * runOnce orqali — bazada faqat bir marta).
     */
    public static function ensureRoleCleanup(\PDO $db): void
    {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'rol'");
            $col = $stmt->fetch();
            if ($col && !str_contains((string) $col['Type'], "'hr-admin'")) {
                return;
            }
            $db->exec(
                "UPDATE users SET rol = 'anticor-admin' WHERE rol IN ('hr-admin','hr','rahbariyat','xarid')"
            );
            $db->exec(
                "ALTER TABLE users MODIFY COLUMN rol " .
                "ENUM('user','anticor-admin','anticor','super-admin') " .
                "NOT NULL DEFAULT 'user'"
            );
            $db->exec('DROP TABLE IF EXISTS employee_pending_approvals');
            $db->exec('DROP TABLE IF EXISTS employee_pending_requests');
        } catch (\Throwable $e) {
            // Best-effort — bajarilmasa, eski rollar bilan bog'liq amal
            // pastda o'zining aniq DB xatoligini beradi.
        }
    }
}
