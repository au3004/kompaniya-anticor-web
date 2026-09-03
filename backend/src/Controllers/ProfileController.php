<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\Response;
use App\Util;
use App\Validate;

final class ProfileController
{
    public static function checkStatus(array $input): void
    {
        $user = Auth::requireUser($input);
        $telefon = Validate::requiredStr($input, 'telefon', 20);

        $normalize = static fn (string $v): string => preg_replace('/\D+/', '', $v) ?? '';
        if ($normalize($telefon) === '' || $normalize($telefon) !== $normalize((string) $user['telefon'])) {
            Response::error('Bu raqam sizga tegishli emas', 'NOT_YOUR_NUMBER');
        }

        $db = Database::connection();

        $docStmt = $db->prepare(
            'SELECT read_at FROM doc_reads WHERE user_id = :id ORDER BY read_at DESC LIMIT 1'
        );
        $docStmt->execute(['id' => $user['id']]);
        $docRow = $docStmt->fetch();
        $hujjatSana = $docRow ? date('Y-m-d', strtotime((string) $docRow['read_at'])) : null;

        $testStmt = $db->prepare(
            'SELECT points, max_points, percent, passed FROM test_attempts
             WHERE user_id = :id ORDER BY attempted_at DESC LIMIT 1'
        );
        $testStmt->execute(['id' => $user['id']]);
        $testRow = $testStmt->fetch();

        Response::success([
            'hujjatSana' => $hujjatSana,
            'testPoints' => $testRow ? (int) $testRow['points'] : null,
            'testPercent' => $testRow ? (int) $testRow['percent'] : null,
            'passed' => $testRow ? (bool) $testRow['passed'] : null,
        ]);
    }

    public static function updateProfilePhoto(array $input): void
    {
        $user = Auth::requireUser($input);
        $dataUrl = (string) ($input['rasm'] ?? '');

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $dataUrl, $m)) {
            Response::error("Rasm formati noto'g'ri", 'INVALID_PHOTO', 422);
        }

        $ext = strtolower($m[1]) === 'jpg' ? 'jpeg' : strtolower($m[1]);
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            Response::error("Rasmni o'qib bo'lmadi", 'INVALID_PHOTO', 422);
        }

        $maxBytes = Config::int('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);
        if (strlen($binary) > $maxBytes) {
            Response::error('Rasm hajmi juda katta', 'PHOTO_TOO_LARGE', 422);
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/photos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = $user['id'] . '_' . time() . '.' . $ext;
        $fullPath = $uploadDir . '/' . $filename;
        if (file_put_contents($fullPath, $binary) === false) {
            Response::error('Rasmni saqlab bo\'lmadi', 'SAVE_FAILED', 500);
        }

        // Eski rasmni tozalash (bor bo'lsa)
        if (!empty($user['rasm_url']) && str_starts_with((string) $user['rasm_url'], '/uploads/photos/')) {
            $oldPath = dirname(__DIR__, 2) . '/public' . $user['rasm_url'];
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $relativeUrl = '/uploads/photos/' . $filename;
        $db = Database::connection();
        $upd = $db->prepare('UPDATE users SET rasm_url = :url WHERE id = :id');
        $upd->execute(['url' => $relativeUrl, 'id' => $user['id']]);

        Response::success(['url' => Util::photoUrl($relativeUrl)]);
    }

    /**
     * Foydalanuvchining o'zi kirgan barcha faol sessiyalari (qurilmalar) ro'yxati.
     * Haqiqiy token hech qachon mijozga yuborilmaydi — faqat uni aniqlash uchun
     * bir tomonlama hash (idHash) beriladi, shu bilan bitta sessiyani nishonlab
     * bekor qilish (revokeSession) mumkin bo'ladi.
     */
    public static function getMySessions(array $input): void
    {
        $user = Auth::requireUser($input);
        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT token, created_at, expires_at FROM sessions WHERE user_id = :id ORDER BY created_at DESC'
        );
        $stmt->execute(['id' => $user['id']]);

        $list = array_map(static fn (array $r) => [
            'idHash' => substr(hash('sha256', $r['token']), 0, 16),
            'createdAt' => date('d.m.Y G:i', strtotime((string) $r['created_at'])),
            'expiresAt' => date('d.m.Y G:i', strtotime((string) $r['expires_at'])),
            'isCurrent' => hash_equals($r['token'], (string) $user['token']),
        ], $stmt->fetchAll());

        Response::success(['sessions' => $list]);
    }

    public static function revokeSession(array $input): void
    {
        $user = Auth::requireUser($input);
        $idHash = Validate::requiredStr($input, 'idHash', 16);

        $db = Database::connection();
        $stmt = $db->prepare('SELECT token FROM sessions WHERE user_id = :id');
        $stmt->execute(['id' => $user['id']]);

        foreach ($stmt->fetchAll() as $row) {
            if (hash_equals(substr(hash('sha256', $row['token']), 0, 16), $idHash)) {
                $del = $db->prepare('DELETE FROM sessions WHERE token = :token');
                $del->execute(['token' => $row['token']]);
                break;
            }
        }

        Response::success();
    }

    /**
     * Joriy sessiyadan tashqari, shu foydalanuvchining barcha boshqa faol
     * sessiyalarini bekor qiladi ("boshqa qurilmalardan chiqish").
     */
    public static function revokeOtherSessions(array $input): void
    {
        $user = Auth::requireUser($input);
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM sessions WHERE user_id = :id AND token != :token');
        $stmt->execute(['id' => $user['id'], 'token' => $user['token']]);

        Response::success();
    }
}
