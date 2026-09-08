<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Util;
use App\Validate;

/**
 * Hujjatlar endi tashqi havola (masalan Google Drive) orqali emas, balki
 * to'g'ridan-to'g'ri serverning o'zida (backend/documents/, public/ papkadan
 * tashqarida) saqlanadi va faqat tizimga kirgan foydalanuvchiga (Auth orqali)
 * beriladi — shu bilan "linkni bilgan har kim ochadi" muammosi yo'qoladi.
 */
final class DocsController
{
    // "url" ustuni eski (Google Drive kabi) havolalar davridan qolgan — endi
    // to'ldirilmaydi, shuning uchun NOT NULL cheklovini olib tashlaymiz.
    private const DDL = 'ALTER TABLE documents
        ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) NULL AFTER url,
        MODIFY COLUMN url VARCHAR(1000) NULL';

    private const MAX_BYTES = 25 * 1024 * 1024;

    public static function documentsDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public static function getDocuments(array $input): void
    {
        Auth::requireUser($input);
        $db = Database::connection();
        $rows = $db->query('SELECT id, nomi_uz, nomi_ru FROM documents ORDER BY id ASC')->fetchAll();

        $docs = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'uz' => $r['nomi_uz'],
            'ru' => $r['nomi_ru'],
        ], $rows);

        Response::success(['docs' => $docs]);
    }

    public static function markDocRead(array $input): void
    {
        $user = Auth::requireUser($input);

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO doc_reads (user_id) VALUES (:user_id)');
        $stmt->execute(['user_id' => $user['id']]);

        Response::success();
    }

    /**
     * "data:application/pdf;base64,...." satrini tekshiradi (hajm va haqiqiy
     * PDF ekanligi — magic bytes orqali, faqat kengaytma/MIME nomiga
     * ishonmasdan), diskka saqlaydi va yaratilgan fayl nomini qaytaradi.
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

    public static function add(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $uz = Validate::requiredStr($input, 'uz', 500);
        $ru = Validate::str($input, 'ru', 500);
        $fileDataUrl = (string) ($input['file'] ?? '');
        if ($fileDataUrl === '') {
            Response::error('PDF fayl tanlanishi shart', 'VALIDATION_ERROR', 422);
        }
        $filename = self::saveUploadedPdf($fileDataUrl);

        try {
            $stmt = $db->prepare(
                'INSERT INTO documents (nomi_uz, nomi_ru, file_name) VALUES (:uz, :ru, :file_name)'
            );
            $stmt->execute(['uz' => $uz, 'ru' => $ru !== '' ? $ru : null, 'file_name' => $filename]);
        } catch (\Throwable $e) {
            @unlink(self::documentsDir() . '/' . $filename);
            throw $e;
        }

        Response::success(['id' => (int) $db->lastInsertId()]);
    }

    public static function edit(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }
        $uz = Validate::requiredStr($input, 'uz', 500);
        $ru = Validate::str($input, 'ru', 500);
        $fileDataUrl = (string) ($input['file'] ?? '');

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $existingStmt = $db->prepare('SELECT file_name FROM documents WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            Response::error('Hujjat topilmadi', 'NOT_FOUND', 404);
        }

        $oldFileName = $existing['file_name'];
        $fileName = $oldFileName;
        $newFileName = null;
        if ($fileDataUrl !== '') {
            $newFileName = self::saveUploadedPdf($fileDataUrl);
            $fileName = $newFileName;
        }

        if (!$fileName) {
            Response::error('PDF fayl tanlanishi shart', 'VALIDATION_ERROR', 422);
        }

        try {
            $stmt = $db->prepare(
                'UPDATE documents SET nomi_uz = :uz, nomi_ru = :ru, file_name = :file_name WHERE id = :id'
            );
            $stmt->execute(['uz' => $uz, 'ru' => $ru !== '' ? $ru : null, 'file_name' => $fileName, 'id' => $id]);
        } catch (\Throwable $e) {
            if ($newFileName) {
                @unlink(self::documentsDir() . '/' . $newFileName);
            }
            throw $e;
        }

        // UPDATE muvaffaqiyatli bo'lgandan keyingina eski faylni o'chiramiz —
        // aks holda baza yozuvi muvaffaqiyatsiz bo'lsa, eski fayl ham yo'qolib qolardi.
        if ($newFileName && $oldFileName) {
            @unlink(self::documentsDir() . '/' . $oldFileName);
        }

        Response::success();
    }

    public static function delete(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT file_name FROM documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        $del = $db->prepare('DELETE FROM documents WHERE id = :id');
        $del->execute(['id' => $id]);

        if ($row && !empty($row['file_name'])) {
            @unlink(self::documentsDir() . '/' . $row['file_name']);
        }

        Response::success();
    }
}
