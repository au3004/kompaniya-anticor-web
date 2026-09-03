<?php
declare(strict_types=1);

namespace App;

/**
 * Server xatoliklarini (backend/public/index.php'ning umumiy catch bloki
 * ushlagan istisnolar) bazaga yozadi — shu bilan gl-admin serverning o'zidagi
 * fayl tizimiga (php_error_log) kirmasdan, admin panelidan "Tizim jurnali"
 * bo'limida so'nggi xatoliklarni ko'ra oladi.
 */
final class Logger
{
    private const DDL = 'CREATE TABLE IF NOT EXISTS error_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        action      VARCHAR(100),
        message     TEXT NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB';

    public static function error(string $action, string $message): void
    {
        // Bazaning o'zi ishlamasa ham (masalan DB_ERROR) butun so'rovni
        // yiqitmasligi kerak — shuning uchun bu yerda hamma narsa jim yutiladi.
        try {
            $db = Database::connection();
            Util::ensureSchema($db, self::DDL);
            $stmt = $db->prepare('INSERT INTO error_log (action, message) VALUES (:action, :message)');
            $stmt->execute(['action' => mb_substr($action, 0, 100), 'message' => mb_substr($message, 0, 4000)]);

            // Jurnal cheksiz o'sib ketmasligi uchun 90 kundan eski yozuvlarni tozalaymiz.
            $db->exec('DELETE FROM error_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
        } catch (\Throwable $e) {
            // indamaymiz — jurnalga yoza olmaslik o'zi yana bir xatolik hosil qilmasligi kerak.
        }
    }
}
