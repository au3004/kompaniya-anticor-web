-- =====================================================================
-- QO'SHIMCHA MIGRATSIYA — faqat oldin schema.sql orqali bazasi allaqachon
-- yaratilgan (users.tugilgan_sana ustuni hali yo'q) o'rnatishlar uchun.
--
-- Yangi o'rnatish qilayotgan bo'lsangiz bu faylni ishlatishingiz shart
-- emas — schema.sql'ning o'zida bu ustun allaqachon bor.
--
-- Ishlatish (XAMPP/phpMyAdmin): kompaniya_anticor bazasini tanlang →
-- "SQL" bo'limiga o'ting → shu faylning mazmunini joylashtirib "Bajarish"ni bosing.
-- =====================================================================

USE kompaniya_anticor;

ALTER TABLE users ADD COLUMN IF NOT EXISTS tugilgan_sana DATE AFTER otasining_ismi;
