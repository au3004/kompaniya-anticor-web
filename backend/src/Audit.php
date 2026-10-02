<?php
declare(strict_types=1);

namespace App;

/**
 * Admin amallari jurnali: kim, qachon, qaysi ma'lumotni o'zgartirgani
 * (xodim qo'shish/tahrirlash/o'chirish, natijalarni o'chirish, sertifikatni
 * bekor qilish, sozlamalar va h.k.). Faqat anticor-admin/super-admin ko'radi
 * (Tizim jurnali bo'limi).
 *
 * super-admin hech qayerda ko'rinmasligi shart — shuning uchun uning
 * amallari jurnalga yozilmaydi. Bajaruvchining F.I.Sh'i yozuv paytidagi
 * holatda saqlanadi (xodim keyin o'chirilsa ham jurnal o'qiladigan qoladi).
 * Jurnalga yozib bo'lmasa ham asosiy amal to'xtamaydi.
 */
final class Audit
{
    public const DDL = 'CREATE TABLE IF NOT EXISTS admin_audit_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        actor_id    INT NULL,
        actor_fish  VARCHAR(500) NOT NULL,
        action      VARCHAR(60) NOT NULL,
        details     TEXT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_action (action)
    ) ENGINE=InnoDB';

    /**
     * @param array  $actor   Auth::requireRole() qaytargan foydalanuvchi qatori
     * @param string $action  Amal kodi (masalan "employee_edit") — nomi admin.html'dagi audit_* tarjimalarida
     * @param string $details Inson o'qiydigan tafsilot (kimga/nimaga nisbatan)
     */
    public static function log(array $actor, string $action, string $details = ''): void
    {
        if (($actor['rol'] ?? '') === Roles::SUPER_ADMIN) {
            return;
        }
        try {
            $db = Database::connection();
            Util::ensureSchema($db, self::DDL);
            $stmt = $db->prepare(
                'INSERT INTO admin_audit_log (actor_id, actor_fish, action, details)
                 VALUES (:actor_id, :actor_fish, :action, :details)'
            );
            $stmt->execute([
                'actor_id' => (int) ($actor['id'] ?? 0) ?: null,
                'actor_fish' => mb_substr(Util::fullName($actor) ?: (string) ($actor['login'] ?? '—'), 0, 500),
                'action' => mb_substr($action, 0, 60),
                'details' => mb_substr($details, 0, 4000),
            ]);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $action . ': ' . $e->getMessage());
        }
    }

    /** Xodim identifikatsiyasi jurnal uchun: "Familiya Ism Otasining ismi (login)". */
    public static function userLabel(array $user): string
    {
        $fish = Util::fullName($user);
        $login = (string) ($user['login'] ?? '');
        return trim($fish . ($login !== '' ? " ({$login})" : ''));
    }

    /** Jurnalni o'qish (so'nggi 2000 ta yozuv). */
    public static function getLog(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);
        $rows = $db->query(
            'SELECT id, actor_fish, action, details, created_at FROM admin_audit_log
             ORDER BY id DESC LIMIT 2000'
        )->fetchAll();

        Response::success(['entries' => array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'sana' => date('d.m.Y H:i', strtotime((string) $r['created_at'])),
            'kim' => $r['actor_fish'],
            'amal' => $r['action'],
            'tafsilot' => (string) $r['details'],
        ], $rows)]);
    }
}
