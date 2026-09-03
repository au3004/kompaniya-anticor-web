<?php
declare(strict_types=1);

namespace App;

final class Cors
{
    public static function handle(): void
    {
        $allowed = Config::get('ALLOWED_ORIGINS', '*');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($allowed === '*') {
            header('Access-Control-Allow-Origin: *');
        } else {
            $list = array_map('trim', explode(',', $allowed));
            if ($origin !== '' && in_array($origin, $list, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Vary: Origin');
            }
        }

        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
