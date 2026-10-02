<?php
declare(strict_types=1);

namespace App;

final class Cors
{
    public static function handle(): void
    {
        // MUHIM: standart holat — hech qanday tashqi domenga ruxsat berilmaydi
        // ("bo'sh" = xavfsiz standart). Frontend va backend odatda bitta domenda
        // xizmat qiladi (shu sabab CORS umuman kerak emas — brauzer bir xil
        // manba so'rovlarida CORS'ni tekshirmaydi ham). Faqat frontend va backend
        // ATAYLAB turli domenlarda joylashtirilganda, .env'da ALLOWED_ORIGINS
        // orqali aniq domen(lar)ni ko'rsating. "*" ATAYLAB qo'llab-quvvatlanmaydi —
        // aks holda har qanday tashqi sayt API'ga so'rov yubora oladigan bo'lib
        // qolardi (masalan kelajakda avtorizatsiya tekshiruvi unutilgan biror
        // amal bo'lsa, uni istalgan sayt darhol tashqi hujum uchun ishlata olardi).
        $allowed = Config::get('ALLOWED_ORIGINS', '');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($allowed !== '' && $allowed !== '*') {
            $list = array_map('trim', explode(',', $allowed));
            if ($origin !== '' && in_array($origin, $list, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Vary: Origin');
            }
        }

        header('Access-Control-Allow-Methods: POST, OPTIONS');
        // "Authorization" — mobil (Flutter web target va h.k.) Bearer token
        // orqali so'rov yuborganda kerak bo'ladi; native iOS/Android
        // ilovalarga bu umuman taalluqli emas (CORS faqat brauzerga tegishli).
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
