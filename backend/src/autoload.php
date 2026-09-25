<?php
declare(strict_types=1);

// PHP'ning vaqt zonasini aniq belgilaymiz — aks holda server sozlamalariga
// (php.ini) qarab turlicha bo'lishi mumkin. Bu ayniqsa MySQL bilan solishtirib
// bo'lmaydigan farq keltirib chiqarardi: masalan XAMPP'da PHP odatda UTC bo'lib,
// MySQL esa mahalliy (Asia/Tashkent, +5) vaqtda ishlaydi — shu tafovut tufayli
// sessiya muddatini PHP hisoblab yozgan vaqt bilan MySQL'ning o'zi NOW() orqali
// solishtirgan vaqti mos kelmay, faol sessiyalar "muddati o'tgan" deb noto'g'ri
// o'chirilib turardi (masalan "Faol sessiyalar" ro'yxatida joriy sessiya ham
// ko'rinmay qolishi, keyin uni tasodifan o'chirib qo'yish va hisobdan chiqib
// ketish kabi holatlarga olib kelgan).
date_default_timezone_set('Asia/Tashkent');

// Composer'siz sodda PSR-4 avtoyuklovchi: App\Foo\Bar -> src/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
