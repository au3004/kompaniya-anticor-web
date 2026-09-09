<?php
declare(strict_types=1);

namespace App;

/**
 * Yangi (2026) rol tizimi. Eskisi (user/admin/gl-admin) bilan solishtirganda:
 * "user" o'zgarishsiz qoladi (boshqaruv paneliga umuman kirmaydigan oddiy
 * xodim), "admin"/"gl-admin" esa ikkita mustaqil bo'lim (Korrupsiyaga qarshi
 * kurashish va Kadrlar) bo'yicha alohida-alohida, ikki pog'onali (to'liq
 * boshqaruv / faqat ko'rish) rollarga bo'lib chiqildi:
 *
 *   user           — boshqaruv paneliga kirmaydi (o'zgarmadi)
 *   anticor-admin  — "Korrupsiyaga qarshi kurashish" bo'limini to'liq
 *                    boshqaradi (test/hujjat/so'rovnoma qo'shish-o'chirish)
 *   anticor        — o'sha bo'limni ko'radi va amallarni bajaradi (xabarnoma
 *                    yuborish, yordam so'roviga javob), lekin tarkibni
 *                    (test savoli, hujjat, so'rovnoma savoli) tahrirlay olmaydi
 *   hr-admin       — "Xodimlar" bo'limini to'liq boshqaradi (qo'shish,
 *                    tahrirlash, o'chirish)
 *   hr             — xodimlar ro'yxatini faqat ko'radi
 *   rahbariyat     — faqat xodimlar ro'yxatini ko'radi + xabarnoma yuboradi,
 *                    boshqa hech narsaga kirmaydi
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
    public const HR_ADMIN = 'hr-admin';
    public const HR = 'hr';
    public const SUPER_ADMIN = 'super-admin';
    public const RAHBARIYAT = 'rahbariyat';

    /** Xodim qo'shish/tahrirlashda tanlash mumkin bo'lgan barcha rollar (super-admin bundan mustasno — u alohida, cheklangan yo'l bilan beriladi). */
    public const ASSIGNABLE = [self::USER, self::ANTICOR_ADMIN, self::ANTICOR, self::HR_ADMIN, self::HR, self::RAHBARIYAT];

    /** "Korrupsiyaga qarshi kurashish" bo'limini ko'radi (stats, test/hujjat/so'rovnoma ro'yxati, xabarnoma, yordam so'rovlari, hisobotlar, tizim jurnali). */
    public const ANTICOR_VIEW = [self::ANTICOR_ADMIN, self::ANTICOR, self::SUPER_ADMIN];

    /** "Korrupsiyaga qarshi kurashish" tarkibini (test savoli/hujjat/so'rovnoma savoli, statistika yozuvlari) qo'sha/tahrirlay/o'chira oladi. */
    public const ANTICOR_MANAGE = [self::ANTICOR_ADMIN, self::SUPER_ADMIN];

    /** Xodimlar ro'yxatini ko'radi (o'zi tahrirlay olmaydi). */
    public const HR_VIEW = [self::HR_ADMIN, self::HR, self::RAHBARIYAT, self::SUPER_ADMIN];

    /** Xodimlarni qo'sha/o'chira oladi (rolni ham istalgancha o'zgartira oladi). */
    public const HR_MANAGE = [self::HR_ADMIN, self::SUPER_ADMIN];

    /** Xodim ma'lumotlarini tahrirlay oladi (F.I.Sh, telefon va h.k.) — "hr" rol maydonini o'zgartira olmaydi (AdminController::editEmployee'da alohida tekshiriladi). */
    public const HR_EDIT = [self::HR_ADMIN, self::HR, self::SUPER_ADMIN];

    /** Xabarnoma yubora oladi — boshqaruv paneliga kiruvchi barcha rollar (oddiy "user"dan tashqari). */
    public const NOTIFY_SEND = [self::ANTICOR_ADMIN, self::ANTICOR, self::HR_ADMIN, self::HR, self::RAHBARIYAT, self::SUPER_ADMIN];

    /** Boshqaruv paneliga umuman kirish huquqi bor rollar ro'yxati (hub sahifasida "kirish yo'q" xabarini ko'rsatish/kirmaslik uchun). */
    public const ANY_PANEL_ACCESS = [self::ANTICOR_ADMIN, self::ANTICOR, self::HR_ADMIN, self::HR, self::RAHBARIYAT, self::SUPER_ADMIN];

    /** Xodimlar yuborgan shaxsiy hujjatlarni (Inson resurslari bo'limi orqali) ko'radi/yuklab oladi/o'chiradi — rahbariyat bunga kirmaydi. */
    public const HR_DOCS = [self::HR_ADMIN, self::HR, self::SUPER_ADMIN];

    /**
     * hr-admin xodimga "user"dan boshqa rol berayotganda so'ralgan kelishuv
     * (approval) so'rovini ko'rib chiqa/tasdiqlay/rad eta oladigan rollar.
     * Kvorum: kamida bitta rahbariyat VA kamida bitta anticor-admin
     * tasdiqlashi shart (ikki mustaqil nazorat nuqtasi); super-admin esa
     * yakka o'zi darhol yakuniy qaror (tasdiqlash yoki rad etish) bera oladi.
     */
    public const REQUEST_APPROVE = [self::ANTICOR_ADMIN, self::RAHBARIYAT, self::SUPER_ADMIN];
}
