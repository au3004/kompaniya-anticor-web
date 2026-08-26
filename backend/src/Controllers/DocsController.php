<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Validate;

final class DocsController
{
    public static function getDocuments(array $input): void
    {
        $db = Database::connection();
        $rows = $db->query('SELECT id, nomi_uz, nomi_ru, url FROM documents ORDER BY id ASC')->fetchAll();

        $docs = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'uz' => $r['nomi_uz'],
            'ru' => $r['nomi_ru'],
            'url' => $r['url'],
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

    public static function add(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $uz = Validate::requiredStr($input, 'uz', 500);
        $ru = Validate::str($input, 'ru', 500);
        $url = Validate::requiredStr($input, 'url', 1000);

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO documents (nomi_uz, nomi_ru, url) VALUES (:uz, :ru, :url)'
        );
        $stmt->execute(['uz' => $uz, 'ru' => $ru !== '' ? $ru : null, 'url' => $url]);

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
        $url = Validate::requiredStr($input, 'url', 1000);

        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE documents SET nomi_uz = :uz, nomi_ru = :ru, url = :url WHERE id = :id'
        );
        $stmt->execute(['uz' => $uz, 'ru' => $ru !== '' ? $ru : null, 'url' => $url, 'id' => $id]);

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
        $stmt = $db->prepare('DELETE FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success();
    }
}
