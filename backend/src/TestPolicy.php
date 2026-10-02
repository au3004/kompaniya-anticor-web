<?php
declare(strict_types=1);

namespace App;

/**
 * Test muddatlari: topshirishning oxirgi muddati va sertifikatning amal
 * qilish muddati (yillik qayta attestatsiya). Ikkalasi ham admin panelidan
 * (Test savollari → Test sozlamalari) istalgan paytda o'zgartiriladi va
 * app_settings jadvalida saqlanadi.
 *
 * Tsikl: sertifikat (o'tgan urinish) muddati tugagach, xodim uchun yangi
 * tsikl boshlanadi — undan oldingi urinishlar va qayta topshirish ruxsatlari
 * hisobga olinmaydi, xodim yana 1 ta urinish bilan testni qayta topshiradi.
 * Tsikl boshi = muddati tugagan eng so'nggi o'tgan urinishning tugash vaqti.
 * Muddat o'zgartirilsa, hammasi shu zahoti yangi qiymat bo'yicha hisoblanadi.
 */
final class TestPolicy
{
    /** Standart: sertifikat 12 oy amal qiladi (0 — cheksiz). */
    public const DEFAULT_VALIDITY_MONTHS = 12;
    public const MAX_VALIDITY_MONTHS = 120;

    private static ?array $settings = null;

    private static function settings(\PDO $db): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }
        $values = [];
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM app_settings
                                WHERE setting_key IN ('test_deadline', 'cert_validity_months')");
            foreach ($stmt->fetchAll() as $r) {
                $values[$r['setting_key']] = (string) $r['setting_value'];
            }
        } catch (\Throwable $e) {
            // Jadval bo'lmasa — standart qiymatlar.
        }
        $months = isset($values['cert_validity_months']) && is_numeric($values['cert_validity_months'])
            ? (int) $values['cert_validity_months'] : self::DEFAULT_VALIDITY_MONTHS;
        $deadline = $values['test_deadline'] ?? '';
        return self::$settings = [
            'validityMonths' => max(0, min(self::MAX_VALIDITY_MONTHS, $months)),
            'deadline' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) ? $deadline : null,
        ];
    }

    /** Sertifikat amal qilish muddati, oyda (0 — cheksiz). */
    public static function validityMonths(\PDO $db): int
    {
        return self::settings($db)['validityMonths'];
    }

    /** Testni topshirishning oxirgi muddati (Y-m-d) yoki null. */
    public static function deadline(\PDO $db): ?string
    {
        return self::settings($db)['deadline'];
    }

    /**
     * SQL sharti: shu urinish hali amal qiladi (muddati tugamagan). $alias —
     * test_attempts jadvali taxallusi. Muddat cheksiz bo'lsa — doim rost.
     */
    public static function notExpiredSql(\PDO $db, string $alias = ''): string
    {
        $m = self::validityMonths($db);
        $col = ($alias !== '' ? $alias . '.' : '') . 'attempted_at';
        return $m > 0 ? "DATE_ADD({$col}, INTERVAL {$m} MONTH) > NOW()" : '1=1';
    }

    /** O'tgan urinish sanasidan sertifikat tugash sanasi (Y-m-d) yoki null (cheksiz). */
    public static function validUntil(\PDO $db, string $attemptedAt): ?string
    {
        $m = self::validityMonths($db);
        return $m > 0 ? date('Y-m-d', strtotime("+{$m} months", strtotime($attemptedAt))) : null;
    }

    /** Xodimning joriy tsikli boshlanishi (DATETIME) yoki null — birinchi tsikl. */
    public static function cycleStart(\PDO $db, int $userId): ?string
    {
        $m = self::validityMonths($db);
        if ($m === 0) {
            return null;
        }
        $stmt = $db->prepare(
            "SELECT MAX(DATE_ADD(attempted_at, INTERVAL {$m} MONTH)) FROM test_attempts
             WHERE user_id = :uid AND passed = 1 AND DATE_ADD(attempted_at, INTERVAL {$m} MONTH) <= NOW()"
        );
        $stmt->execute(['uid' => $userId]);
        $v = $stmt->fetchColumn();
        return $v ? (string) $v : null;
    }

    /** Barcha xodimlar bo'yicha tsikl boshi — [user_id => DATETIME] (faqat yangi tsiklga o'tganlar). */
    public static function cycleStarts(\PDO $db): array
    {
        $m = self::validityMonths($db);
        if ($m === 0) {
            return [];
        }
        $rows = $db->query(
            "SELECT user_id, MAX(DATE_ADD(attempted_at, INTERVAL {$m} MONTH)) AS s FROM test_attempts
             WHERE passed = 1 AND DATE_ADD(attempted_at, INTERVAL {$m} MONTH) <= NOW()
             GROUP BY user_id"
        )->fetchAll();
        $result = [];
        foreach ($rows as $r) {
            $result[(int) $r['user_id']] = (string) $r['s'];
        }
        return $result;
    }

    /** Sozlamalarni o'qish (admin paneli va xodim sahifalari uchun). */
    public static function getSettings(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_VIEW);
        $db = Database::connection();
        Response::success([
            'deadline' => self::deadline($db),
            'validityMonths' => self::validityMonths($db),
        ]);
    }

    public static function saveSettings(array $input): void
    {
        $me = Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $deadline = Validate::str($input, 'deadline', 10);
        if ($deadline !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $deadline, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
            Response::error("Muddat sanasi noto'g'ri", 'VALIDATION_ERROR', 422);
        }
        $months = Validate::int($input, 'validityMonths', self::DEFAULT_VALIDITY_MONTHS);
        if ($months === null || $months < 0 || $months > self::MAX_VALIDITY_MONTHS) {
            Response::error('Amal qilish muddati 0–' . self::MAX_VALIDITY_MONTHS . ' oy oralig\'ida bo\'lishi kerak', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $before = ['deadline' => self::deadline($db), 'validityMonths' => self::validityMonths($db)];
        $stmt = $db->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        foreach (['test_deadline' => $deadline, 'cert_validity_months' => (string) $months] as $k => $v) {
            $stmt->execute(['k' => $k, 'v' => $v, 'v2' => $v]);
        }
        self::$settings = null;

        $changes = [];
        if ($before['deadline'] !== ($deadline ?: null)) {
            $changes[] = 'topshirish muddati: ' . ($before['deadline'] ? date('d.m.Y', strtotime($before['deadline'])) : '—')
                . ' → ' . ($deadline ? date('d.m.Y', strtotime($deadline)) : '—');
        }
        if ($before['validityMonths'] !== $months) {
            $fmt = static fn (int $n): string => $n === 0 ? 'cheksiz' : "{$n} oy";
            $changes[] = 'sertifikat amal qilish muddati: ' . $fmt($before['validityMonths']) . ' → ' . $fmt($months);
        }
        if ($changes) {
            Audit::log($me, 'test_settings', implode('; ', $changes));
        }

        Response::success(['deadline' => self::deadline($db), 'validityMonths' => self::validityMonths($db)]);
    }
}
