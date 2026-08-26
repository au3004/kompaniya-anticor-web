<?php
declare(strict_types=1);

namespace App;

final class Util
{
    public static function photoUrl(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }
        if (preg_match('#^https?://#i', $relativePath)) {
            return $relativePath;
        }
        $base = rtrim((string) Config::get('PUBLIC_BASE_URL', ''), '/');
        return $base . '/' . ltrim($relativePath, '/');
    }

    public static function fullName(array $user): string
    {
        return trim(implode(' ', array_filter([
            $user['familiya'] ?? '',
            $user['ism'] ?? '',
            $user['otasining_ismi'] ?? '',
        ])));
    }
}
