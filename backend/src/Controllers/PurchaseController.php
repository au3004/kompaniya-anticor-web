<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Roles;
use App\Util;
use App\Validate;

/**
 * "Xaridlar reyestri" — "xarid" roli (+ anticor-admin/super-admin faqat
 * ko'rish uchun) tomonidan kiritiladigan shartnoma yozuvlari. Shartnoma
 * fayli DocsController/HrDocumentController'dagi kabi to'g'ridan-to'g'ri
 * serverda (backend/purchase_contracts/, public/ papkadan tashqarida)
 * saqlanadi.
 */
final class PurchaseController
{
    private const DDL = "CREATE TABLE IF NOT EXISTS purchases (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        shartnoma_sana      DATE NOT NULL,
        shartnoma_raqami    VARCHAR(100) NOT NULL,
        kontragent          VARCHAR(255) NOT NULL,
        shartnoma_predmeti  VARCHAR(500) NOT NULL,
        shartnoma_summasi   DECIMAL(18,2) NOT NULL,
        xarid_turi          VARCHAR(150) NOT NULL,
        izoh                TEXT NULL,
        file_name           VARCHAR(255) NOT NULL,
        original_name       VARCHAR(255) NULL,
        created_by          INT NOT NULL,
        created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_shartnoma_raqami (shartnoma_raqami),
        INDEX idx_kontragent (kontragent)
    ) ENGINE=InnoDB";

    private const MAX_BYTES = 25 * 1024 * 1024;

    public static function contractsDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/purchase_contracts';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /**
     * "data:application/pdf;base64,...." satrini tekshiradi (hajm va haqiqiy
     * PDF ekanligi — magic bytes orqali) va diskka saqlaydi.
     */
    private static function saveUploadedContract(string $dataUrl): string
    {
        if (strlen($dataUrl) > (int) (self::MAX_BYTES * 1.4) + 100) {
            Response::error('Fayl hajmi juda katta (maksimal 25MB)', 'FILE_TOO_LARGE', 422);
        }

        if (!preg_match('/^data:application\/pdf;base64,(.+)$/i', $dataUrl, $m)) {
            Response::error("Fayl formati noto'g'ri, faqat PDF qabul qilinadi", 'INVALID_FILE', 422);
        }

        $binary = base64_decode($m[1], true);
        if ($binary === false) {
            Response::error("Faylni o'qib bo'lmadi", 'INVALID_FILE', 422);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            Response::error('Fayl hajmi juda katta (maksimal 25MB)', 'FILE_TOO_LARGE', 422);
        }

        if (!str_starts_with($binary, '%PDF-')) {
            Response::error("Fayl haqiqiy PDF emas", 'INVALID_FILE', 422);
        }

        $filename = bin2hex(random_bytes(16)) . '.pdf';
        file_put_contents(self::contractsDir() . '/' . $filename, $binary);

        return $filename;
    }

    /** "Reyestrga kiritish" — yangi xarid yozuvini shartnoma fayli bilan birga kiritadi. */
    public static function addPurchase(array $input): void
    {
        $me = Auth::requireRole($input, Roles::PURCHASE_ENTRY);
        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $shartnomaSana = Validate::requiredStr($input, 'shartnomaSana', 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shartnomaSana)) {
            Response::error("Shartnoma sanasi noto'g'ri", 'VALIDATION_ERROR', 422);
        }
        $shartnomaRaqami = Validate::requiredStr($input, 'shartnomaRaqami', 100);
        $kontragent = Validate::requiredStr($input, 'kontragent', 255);
        $shartnomaPredmeti = Validate::requiredStr($input, 'shartnomaPredmeti', 500);
        $xaridTuri = Validate::requiredStr($input, 'xaridTuri', 150);
        $izoh = Validate::str($input, 'izoh', 2000);

        $summaRaw = (string) ($input['shartnomaSummasi'] ?? '');
        $summa = (float) str_replace([' ', ','], ['', '.'], $summaRaw);
        if (!is_numeric(str_replace([' ', ','], ['', '.'], $summaRaw)) || $summa <= 0) {
            Response::error("Shartnoma summasi noto'g'ri", 'VALIDATION_ERROR', 422);
        }

        $fileDataUrl = (string) ($input['file'] ?? '');
        if ($fileDataUrl === '') {
            Response::error('Shartnoma fayli (PDF) yuklanishi shart', 'VALIDATION_ERROR', 422);
        }
        $originalName = mb_substr((string) ($input['fileName'] ?? ''), 0, 255);
        $filename = self::saveUploadedContract($fileDataUrl);

        try {
            $stmt = $db->prepare(
                'INSERT INTO purchases
                    (shartnoma_sana, shartnoma_raqami, kontragent, shartnoma_predmeti, shartnoma_summasi,
                     xarid_turi, izoh, file_name, original_name, created_by)
                 VALUES
                    (:sana, :raqami, :kontragent, :predmeti, :summasi, :turi, :izoh, :file_name, :orig, :created_by)'
            );
            $stmt->execute([
                'sana' => $shartnomaSana,
                'raqami' => $shartnomaRaqami,
                'kontragent' => $kontragent,
                'predmeti' => $shartnomaPredmeti,
                'summasi' => $summa,
                'turi' => $xaridTuri,
                'izoh' => $izoh !== '' ? $izoh : null,
                'file_name' => $filename,
                'orig' => $originalName !== '' ? $originalName : null,
                'created_by' => $me['id'],
            ]);
        } catch (\Throwable $e) {
            @unlink(self::contractsDir() . '/' . $filename);
            throw $e;
        }

        Response::success(['id' => (int) $db->lastInsertId()]);
    }

    /**
     * "Xaridlar reyestri" — barcha yozuvlar. Qidiruv (shartnoma raqami YOKI
     * kontragent nomi bo'yicha) frontendda, xuddi loyihadagi boshqa
     * ro'yxatlar (xodimlar, statistika) kabi, allaqachon yuklangan
     * ro'yxat ustida amalga oshiriladi.
     */
    public static function getPurchases(array $input): void
    {
        Auth::requireRole($input, Roles::PURCHASE_VIEW);
        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $stmt = $db->query(
            'SELECT id, shartnoma_sana, shartnoma_raqami, kontragent, shartnoma_predmeti, shartnoma_summasi,
                    xarid_turi, izoh
             FROM purchases
             ORDER BY id ASC'
        );

        $rows = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'shartnomaSana' => $r['shartnoma_sana'],
            'shartnomaRaqami' => $r['shartnoma_raqami'],
            'kontragent' => $r['kontragent'],
            'shartnomaPredmeti' => $r['shartnoma_predmeti'],
            'shartnomaSummasi' => (float) $r['shartnoma_summasi'],
            'xaridTuri' => $r['xarid_turi'],
            'izoh' => $r['izoh'],
        ], $stmt->fetchAll());

        Response::success(['purchases' => $rows]);
    }
}
