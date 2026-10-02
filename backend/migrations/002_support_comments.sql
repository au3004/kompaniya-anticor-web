-- =====================================================================
-- QO'SHIMCHA MIGRATSIYA — faqat oldin schema.sql orqali bazasi allaqachon
-- yaratilgan (support_comments jadvali hali yo'q) o'rnatishlar uchun.
--
-- Yangi o'rnatish qilayotgan bo'lsangiz bu faylni ishlatishingiz shart
-- emas — schema.sql'ning o'zida bu jadval allaqachon bor.
--
-- Ishlatish (XAMPP/phpMyAdmin): kompaniya_anticor bazasini tanlang →
-- "SQL" bo'limiga o'ting → shu faylning mazmunini joylashtirib "Bajarish"ni bosing.
-- =====================================================================

USE kompaniya_anticor;

CREATE TABLE IF NOT EXISTS support_comments (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  support_request_id  INT NOT NULL,
  user_id             INT NOT NULL,
  izoh                TEXT NOT NULL,
  created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (support_request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_request (support_request_id)
) ENGINE=InnoDB;
