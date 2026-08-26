<?php
declare(strict_types=1);

namespace App;

final class Response
{
    public static function json(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = []): void
    {
        self::json(array_merge(['success' => true], $data), 200);
    }

    public static function error(string $message, string $code = 'ERROR', int $httpCode = 400): void
    {
        self::json([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], $httpCode);
    }
}
