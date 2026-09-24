<?php
declare(strict_types=1);

namespace App;

/**
 * Xodim biriktirilishi mumkin bo'lgan filiallar ro'yxati (belgilangan,
 * yopiq to'plam — erkin matn emas, tanlov orqali belgilanadi). Ko'rsatish
 * uchun uz/ru nomlar admin.html'dagi `filial_*` i18n kalitlarida.
 */
final class Filials
{
    public const IJROIYA_APPARATI = 'ijroiya_apparati';
    public const MARKAZIY = 'markaziy';
    public const SHIMOLIY = 'shimoliy';
    public const SHARQIY = 'sharqiy';
    public const JANUBIY = 'janubiy';
    public const GARBIY = 'garbiy';
    public const JANUBI_GARBIY = 'janubi_garbiy';
    public const TEXNIK = 'texnik';
    public const TMS_HUB = 'tms_hub';

    public const ALL = [
        self::IJROIYA_APPARATI,
        self::MARKAZIY,
        self::SHIMOLIY,
        self::SHARQIY,
        self::JANUBIY,
        self::GARBIY,
        self::JANUBI_GARBIY,
        self::TEXNIK,
        self::TMS_HUB,
    ];

    public static function isValid(string $filial): bool
    {
        return in_array($filial, self::ALL, true);
    }
}
