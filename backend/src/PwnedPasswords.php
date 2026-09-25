<?php
declare(strict_types=1);

namespace App;

/**
 * Parolni "Have I Been Pwned" sizib chiqqan parollar bazasiga qarshi
 * tekshiradi — k-anonymity usuli bilan: parolning to'liq SHA-1 xeshi
 * HECH QACHON tashqariga yuborilmaydi, faqat uning birinchi 5 ta belgisi
 * (prefiksi) yuboriladi, javobda esa shu prefiksga mos yuzlab-minglab
 * suffikslar qaytadi va moslik lokal (server ichida) tekshiriladi.
 *
 * Xizmat ishlamasa yoki tarmoq xatosi bo'lsa — "fail-open": tekshiruv
 * jim o'tkazib yuboriladi (foydalanuvchi parol o'zgartira olmay
 * qolmasligi kerak), lekin xatolik "Tizim jurnali"ga yoziladi.
 */
final class PwnedPasswords
{
    private const API_URL = 'https://api.pwnedpasswords.com/range/';
    private const TIMEOUT_SECONDS = 3;

    public static function isBreached(string $password): bool
    {
        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        $body = self::fetchRange($prefix);
        if ($body === null) {
            return false;
        }

        foreach (explode("\r\n", trim($body)) as $line) {
            [$lineSuffix] = explode(':', $line, 2) + [null, null];
            if ($lineSuffix !== null && strcasecmp(trim($lineSuffix), $suffix) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function fetchRange(string $prefix): ?string
    {
        if (!function_exists('curl_init')) {
            Logger::error('pwnedPasswords', 'curl kengaytmasi topilmadi');
            return null;
        }

        $ch = curl_init(self::API_URL . $prefix);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => ['Add-Padding: true'],
            CURLOPT_USERAGENT => 'kompaniya-anticor-web',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            Logger::error('pwnedPasswords', "HIBP so'rovi muvaffaqiyatsiz: HTTP {$status} {$error}");
            return null;
        }

        return $body;
    }
}
