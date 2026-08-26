<?php
declare(strict_types=1);

namespace App;

final class Validate
{
    public static function str(array $input, string $key, int $maxLen = 1000): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if (mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }
        return $value;
    }

    public static function requiredStr(array $input, string $key, int $maxLen = 1000): string
    {
        $value = self::str($input, $key, $maxLen);
        if ($value === '') {
            Response::error("Maydon to'ldirilishi shart: {$key}", 'VALIDATION_ERROR', 422);
        }
        return $value;
    }

    public static function int(array $input, string $key, ?int $default = null): ?int
    {
        if (!isset($input[$key]) || $input[$key] === '') {
            return $default;
        }
        return (int) $input[$key];
    }

    public static function bool(array $input, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $input)) {
            return $default;
        }
        $value = $input[$key];
        if (is_bool($value)) {
            return $value;
        }
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    public static function array(array $input, string $key): array
    {
        $value = $input[$key] ?? [];
        return is_array($value) ? $value : [];
    }
}
