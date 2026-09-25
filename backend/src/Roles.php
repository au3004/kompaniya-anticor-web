<?php
declare(strict_types=1);

namespace App;

/**
 * Rol tizimi. "user" boshqaruv paneliga umuman kirmaydigan oddiy xodim;
 * "anticor-admin"/"anticor" — "Korrupsiyaga qarshi kurashish" bo'limi
 * (ikki pog'onali: to'liq boshqaruv / faqat ko'rish). Ilgari alohida
 * bo'lgan "Xodimlar", "Xaridlar reyestri" va "Inson resurslari hujjatlari"
 * bo'limlarini boshqargan rollar (hr-admin, hr, rahbariyat, xarid) olib
 * tashlangan — ularning barcha vakolati endi anticor-adminga o'tkazilgan
 * (anticor-admin bu bo'limlarni ham to'liq boshqaradi):
 *
 *   user           — boshqaruv paneliga kirmaydi
 *   anticor-admin  — "Korrupsiyaga qarshi kurashish", "Xodimlar",
 *                    "Xaridlar reyestri" va "Inson resurslari hujjatlari"
 *                    bo'limlarini to'liq boshqaradi
 *   anticor        — "Korrupsiyaga qarshi kurashish" bo'limini ko'radi va
 *                    amallarni bajaradi (xabarnoma yuborish, yordam
 *                    so'roviga javob), lekin tarkibni (test savoli,
 *                    hujjat, so'rovnoma savoli) tahrirlay olmaydi
 *   super-admin    — barcha bo'limlarga to'liq kirish huquqiga ega, tizimda
 *                    doim FAQAT bitta shu roldagi xodim bo'lishi shart
 *                    (AdminController::assertSuperAdminSingleton() orqali
 *                    ta'minlanadi) va hech qaysi hisobot/ro'yxatda
 *                    ko'rinmaydi (AdminController::excludeSuperAdmin() orqali)
 */
final class Roles
{
    public const USER = 'user';
    public const ANTICOR_ADMIN = 'anticor-admin';
    public const ANTICOR = 'anticor';
    public const SUPER_ADMIN = 'super-admin';

    /** Xodim qo'shish/tahrirlashda tanlash mumkin bo'lgan barcha rollar (super-admin bundan mustasno — u alohida, cheklangan yo'l bilan beriladi). */
    public const ASSIGNABLE = [self::USER, self::ANTICOR_ADMIN, self::ANTICOR];

    /** "Korrupsiyaga qarshi kurashish" bo'limini ko'radi (stats, test/hujjat/so'rovnoma ro'yxati, xabarnoma, yordam so'rovlari, hisobotlar, tizim jurnali). */
    public const ANTICOR_VIEW = [self::ANTICOR_ADMIN, self::ANTICOR, self::SUPER_ADMIN];

    /** "Korrupsiyaga qarshi kurashish" tarkibini (test savoli/hujjat/so'rovnoma savoli, statistika yozuvlari) qo'sha/tahrirlay/o'chira oladi. */
    public const ANTICOR_MANAGE = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];

    /** Xodimlar ro'yxatini ko'radi (o'zi tahrirlay olmaydi). */
    public const HR_VIEW = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];

    /** Xodimlarni qo'sha/o'chira oladi (rolni ham istalgancha o'zgartira oladi). */
    public const HR_MANAGE = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];

    /** Xodim ma'lumotlarini tahrirlay oladi (F.I.Sh, telefon va h.k.). */
    public const HR_EDIT = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];

    /** Xabarnoma yubora oladi — boshqaruv paneliga kiruvchi barcha rollar (oddiy "user"dan tashqari). */
    public const NOTIFY_SEND = [self::ANTICOR_ADMIN, self::ANTICOR, self::SUPER_ADMIN];

    /** Boshqaruv paneliga umuman kirish huquqi bor rollar ro'yxati (hub sahifasida "kirish yo'q" xabarini ko'rsatish/kirmaslik uchun). */
    public const ANY_PANEL_ACCESS = [self::ANTICOR_ADMIN, self::ANTICOR, self::SUPER_ADMIN];

    /** "Xaridlar reyestri"ni ko'radi — ro'yxat, qidiruv, Excel eksport, shartnoma faylini yuklab olish. */
    public const PURCHASE_VIEW = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];

    /** "Reyestrga kiritish" — yangi xarid yozuvini (shartnoma ma'lumotlari + fayl) kirita oladi. */
    public const PURCHASE_ENTRY = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];

    /** Xodimlar yuborgan shaxsiy hujjatlarni (Inson resurslari bo'limi orqali) ko'radi/yuklab oladi/o'chiradi. */
    public const HR_DOCS = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];
}
