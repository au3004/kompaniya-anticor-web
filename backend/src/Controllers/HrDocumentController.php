<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Roles;
use App\Util;

/**
 * "Inson resurslarini boshqarish xizmati" bo'limi orqali xodimlar o'zlariga
 * tegishli hujjatlarni (masalan ariza, ma'lumotnoma) yuboradi — fayllar
 * DocsController'dagi kabi to'g'ridan-to'g'ri serverda (backend/hr_documents/,
 * public/ papkadan tashqarida) saqlanadi va faqat hr-admin/hr (+super-admin)
 * ko'ra oladi.
 */
final class HrDocumentController
{
    private const DDL = 'CREATE TABLE IF NOT EXISTS hr_documents (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        user_id        INT NOT NULL,
        file_name      VARCHAR(255) NOT NULL,
        original_name  VARCHAR(255) NULL,
        uploaded_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB';

    private const MAX_BYTES = 25 * 1024 * 1024;

    public static function documentsDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/hr_documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /**
     * "data:application/pdf;base64,...." satrini tekshiradi (hajm va haqiqiy
     * PDF ekanligi — magic bytes orqali) va diskka saqlaydi.
     */
    private static function saveUploadedPdf(string $dataUrl): string
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
        file_put_contents(self::documentsDir() . '/' . $filename, $binary);

        return $filename;
    }

    /** Har qanday tizimga kirgan xodim (rolidan qat'i nazar) o'z hujjatini yuboradi. */
    public static function submit(array $input): void
    {
        $user = Auth::requireUser($input);
        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $fileDataUrl = (string) ($input['file'] ?? '');
        if ($fileDataUrl === '') {
            Response::error('PDF fayl tanlanishi shart', 'VALIDATION_ERROR', 422);
        }
        $originalName = mb_substr((string) ($input['fileName'] ?? ''), 0, 255);
        $filename = self::saveUploadedPdf($fileDataUrl);

        try {
            $stmt = $db->prepare(
                'INSERT INTO hr_documents (user_id, file_name, original_name) VALUES (:uid, :file_name, :orig)'
            );
            $stmt->execute([
                'uid' => $user['id'],
                'file_name' => $filename,
                'orig' => $originalName !== '' ? $originalName : null,
            ]);
        } catch (\Throwable $e) {
            @unlink(self::documentsDir() . '/' . $filename);
            throw $e;
        }

        Response::success(['id' => (int) $db->lastInsertId()]);
    }

    /** hr-admin/hr (+super-admin) — barcha xodimlardan kelgan hujjatlar ro'yxati. */
    public static function list(array $input): void
    {
        Auth::requireRole($input, Roles::HR_DOCS);
        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $stmt = $db->prepare(
            'SELECT hd.id, hd.original_name, hd.uploaded_at,
                    u.familiya, u.ism, u.otasining_ismi, u.telefon, u.lavozim, u.bolinma
             FROM hr_documents hd
             JOIN users u ON u.id = hd.user_id
             WHERE u.rol != :superAdmin
             ORDER BY hd.uploaded_at DESC'
        );
        // super-admin hech qaysi ro'yxatda ko'rinmasligi shart — o'zi yuborgan
        // hujjat ham shu qoidadan mustasno emas.
        $stmt->execute(['superAdmin' => Roles::SUPER_ADMIN]);

        $list = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'fish' => Util::fullName($r),
            'telefon' => $r['telefon'],
            'lavozim' => $r['lavozim'],
            'bolinma' => $r['bolinma'],
            'sana' => date('d.m.Y G:i', strtotime((string) $r['uploaded_at'])),
        ], $stmt->fetchAll());

        Response::success(['documents' => $list]);
    }

    public static function delete(array $input): void
    {
        Auth::requireRole($input, Roles::HR_DOCS);
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT file_name FROM hr_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        $del = $db->prepare('DELETE FROM hr_documents WHERE id = :id');
        $del->execute(['id' => $id]);

        if ($row && !empty($row['file_name'])) {
            @unlink(self::documentsDir() . '/' . $row['file_name']);
        }

        Response::success();
    }
}
