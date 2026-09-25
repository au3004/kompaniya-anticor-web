<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Roles;
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
    // folder_file — hujjat loyiha ildizidagi Hujjatlar/ papkasidan olinganda
    // undagi fayl nomi (bunday hujjatning file_name'i bo'sh bo'ladi).
    private const DDL = 'ALTER TABLE documents
        ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) NULL AFTER url,
        ADD COLUMN IF NOT EXISTS folder_file VARCHAR(255) NULL UNIQUE AFTER file_name,
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

    /** Loyiha ildizidagi Hujjatlar/ papkasi (masalan htdocs/anticor/Hujjatlar). */
    public static function folderDir(): string
    {
        return dirname(__DIR__, 3) . '/Hujjatlar';
    }

    /**
     * Hujjatlar/ papkasidagi PDF fayllarni hujjatlar ro'yxati bilan
     * moslashtiradi: yangi fayl — yangi hujjat (nomi fayl nomidan olinadi,
     * keyin admin panelidan o'zgartirish mumkin), papkadan o'chirilgan fayl —
     * ro'yxatdan ham o'chadi. Admin panelidan yuklangan hujjatlarga tegilmaydi.
     */
    private static function syncFolder(\PDO $db): void
    {
        // Hujjatlar ro'yxati har ochilganda chaqiriladi — ALTER TABLE'ni faqat
        // ustun hali yo'q bo'lgandagina ishga tushiramiz.
        if (!$db->query("SHOW COLUMNS FROM documents LIKE 'folder_file'")->fetch()) {
            Util::ensureSchema($db, self::DDL);
        }
        $dir = self::folderDir();
        if (!is_dir($dir)) {
            return;
        }

        $files = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name[0] !== '.' && strcasecmp(pathinfo($name, PATHINFO_EXTENSION), 'pdf') === 0 && is_file($dir . '/' . $name)) {
                $files[$name] = true;
            }
        }

        $known = [];
        foreach ($db->query('SELECT id, folder_file FROM documents WHERE folder_file IS NOT NULL')->fetchAll() as $row) {
            if (isset($files[$row['folder_file']])) {
                $known[$row['folder_file']] = true;
            } else {
                $del = $db->prepare('DELETE FROM documents WHERE id = :id');
                $del->execute(['id' => $row['id']]);
            }
        }

        $new = array_diff_key($files, $known);
        if (!$new) {
            return;
        }
        $names = array_keys($new);
        natcasesort($names);
        $ins = $db->prepare('INSERT IGNORE INTO documents (nomi_uz, nomi_ru, folder_file) VALUES (:uz, :ru, :file)');
        foreach ($names as $name) {
            $title = mb_substr(trim(pathinfo($name, PATHINFO_FILENAME)), 0, 500);
            $ins->execute(['uz' => $title, 'ru' => $title, 'file' => $name]);
        }
    }

    public static function getDocuments(array $input): void
    {
        Auth::requireUser($input);
        $db = Database::connection();
        self::syncFolder($db);
        $rows = $db->query('SELECT id, nomi_uz, nomi_ru, folder_file FROM documents ORDER BY id ASC')->fetchAll();

        $docs = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'uz' => $r['nomi_uz'],
            'ru' => $r['nomi_ru'],
            'fromFolder' => $r['folder_file'] !== null,
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
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);

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
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }
        $uz = Validate::requiredStr($input, 'uz', 500);
        $ru = Validate::str($input, 'ru', 500);
        $fileDataUrl = (string) ($input['file'] ?? '');

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);

        $existingStmt = $db->prepare('SELECT file_name, folder_file FROM documents WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            Response::error('Hujjat topilmadi', 'NOT_FOUND', 404);
        }

        if ($existing['folder_file'] !== null) {
            // Fayl Hujjatlar/ papkasida boshqariladi — bu yerda faqat nomi o'zgaradi.
            if ($fileDataUrl !== '') {
                Response::error(
                    "Bu hujjat Hujjatlar papkasidan olinadi — faylni o'sha papkada almashtiring",
                    'FOLDER_DOCUMENT',
                    422
                );
            }
            $stmt = $db->prepare('UPDATE documents SET nomi_uz = :uz, nomi_ru = :ru WHERE id = :id');
            $stmt->execute(['uz' => $uz, 'ru' => $ru !== '' ? $ru : null, 'id' => $id]);
            Response::success();
            return;
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
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        Util::ensureSchema($db, self::DDL);
        $stmt = $db->prepare('SELECT file_name, folder_file FROM documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row && $row['folder_file'] !== null) {
            // O'chirilsa ham keyingi ochilishda papkadan qayta qo'shilib qolardi.
            Response::error(
                "Bu hujjat Hujjatlar papkasidan olinadi — uni ro'yxatdan olib tashlash uchun faylni o'sha papkadan o'chiring",
                'FOLDER_DOCUMENT',
                422
            );
        }

        $del = $db->prepare('DELETE FROM documents WHERE id = :id');
        $del->execute(['id' => $id]);

        if ($row && !empty($row['file_name'])) {
            @unlink(self::documentsDir() . '/' . $row['file_name']);
        }

        Response::success();
    }
}
