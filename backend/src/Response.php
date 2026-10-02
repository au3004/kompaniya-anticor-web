<?php
declare(strict_types=1);

namespace App;

final class Response
{
    public static function json(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        // Qo'shimcha xavfsizlik sarlavhalari (barcha javoblarga, jumladan xatoliklarga ham).
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        // Bu javob doim faqat JSON — hech qanday skript/uslub/rasm yuklamaydi,
        // shuning uchun eng qattiq CSP xavfsiz (brauzer bu javobni HTML sifatida
        // render qilishga urinib qolgan taqdirda ham qo'shimcha himoya qatlami).
        header("Content-Security-Policy: default-src 'none'");
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
