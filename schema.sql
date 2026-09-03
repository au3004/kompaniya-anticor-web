-- =====================================================================
-- Kompaniya anticor WEB — MySQL baza tuzilmasi
-- Google Sheets'dan haqiqiy bazaga ko'chirish uchun loyihalangan
-- =====================================================================

CREATE DATABASE IF NOT EXISTS kompaniya_anticor
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kompaniya_anticor;

-- ---------------------------------------------------------------------
-- 1) XODIMLAR (avvalgi "Users" varag'i)
-- MUHIM YAXSHILANISH: parol endi ochiq matnda emas, bcrypt hash
-- ko'rinishida saqlanadi (password_hash) — Sheets'da bu imkonsiz edi.
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  login             VARCHAR(100) NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NOT NULL,
  familiya          VARCHAR(150) NOT NULL,
  ism               VARCHAR(150) NOT NULL,
  otasining_ismi    VARCHAR(150),
  tugilgan_sana     DATE,
  lavozim           VARCHAR(200),
  lavozim_ru        VARCHAR(200),
  bolinma           VARCHAR(200),
  bolinma_ru        VARCHAR(200),
  telefon           VARCHAR(20),
  rasm_url          VARCHAR(500),        -- endi base64 emas, haqiqiy fayl yo'li (masalan /uploads/photos/12.jpg)
  rol               ENUM('user','admin','gl-admin') NOT NULL DEFAULT 'user',
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_telefon (telefon)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2) SESSIYALAR (avvalgi CacheService token'lari o'rniga)
-- ---------------------------------------------------------------------
CREATE TABLE sessions (
  token       CHAR(64) PRIMARY KEY,
  user_id     INT NOT NULL,
  expires_at  DATETIME NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Login urinishlari (bruteforce himoyasi uchun, avvalgi CacheService o'rniga)
CREATE TABLE login_attempts (
  login         VARCHAR(100) PRIMARY KEY,
  fail_count    INT NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) HUJJATLAR BILAN TANISHISH VA TEST TARIXI
-- MUHIM YAXSHILANISH: avvalgi Sheets'da "qayta tanishgan sanalar" bitta
-- katakchaga \n bilan ajratib yozilardi (juda noqulay). Endi har bir
-- voqea (o'qish, test topshirish) alohida QATOR — SQL bilan sanash,
-- filtrlash, saralash cheksiz oson va tez bo'ladi.
-- ---------------------------------------------------------------------
CREATE TABLE doc_reads (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id   INT NOT NULL,
  read_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE test_attempts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  attempted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  points        INT NOT NULL,
  max_points    INT NOT NULL,
  percent       INT NOT NULL,
  passed        BOOLEAN NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) TEST SAVOLLARI (avvalgi "Test" varag'i)
-- ---------------------------------------------------------------------
CREATE TABLE test_questions (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  savol           TEXT NOT NULL,
  variant_a       VARCHAR(500),
  variant_b       VARCHAR(500),
  variant_c       VARCHAR(500),
  variant_d       VARCHAR(500),
  togri_javob     VARCHAR(500),
  savol_ru        TEXT,
  variant_a_ru    VARCHAR(500),
  variant_b_ru    VARCHAR(500),
  variant_c_ru    VARCHAR(500),
  variant_d_ru    VARCHAR(500),
  togri_javob_ru  VARCHAR(500),
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5) HUJJATLAR (avvalgi "Documents" varag'i)
-- ---------------------------------------------------------------------
CREATE TABLE documents (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nomi_uz     VARCHAR(500) NOT NULL,
  nomi_ru     VARCHAR(500),
  url         VARCHAR(1000) NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6) ANONIM SO'ROVNOMA
-- MUHIM YAXSHILANISH: Sheets'da "ID-1, ID-2..." kabi sun'iy ustunlar
-- yaratishga majbur edik (chunki katakchalar cheklangan). Haqiqiy bazada
-- bunga hojat yo'q — har bir javob shunchaki alohida qator.
-- ---------------------------------------------------------------------
CREATE TABLE survey_questions (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  turi            ENUM('tanlov','yulduz','matn') NOT NULL DEFAULT 'tanlov',
  savol           TEXT NOT NULL,
  variant_a       VARCHAR(500),
  variant_b       VARCHAR(500),
  variant_c       VARCHAR(500),
  variant_d       VARCHAR(500),
  yulduzlar_soni  INT DEFAULT 5,
  savol_ru        TEXT,
  variant_a_ru    VARCHAR(500),
  variant_b_ru    VARCHAR(500),
  variant_c_ru    VARCHAR(500),
  variant_d_ru    VARCHAR(500),
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bitta "topshiriq" (bitta odamning bir martalik javoblar to'plami) —
-- HECH QANDAY foydalanuvchi ma'lumoti bilan bog'lanmaydi (anonimlik saqlanadi).
CREATE TABLE survey_submissions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  submitted_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE survey_answers (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  submission_id  INT NOT NULL,
  question_id    INT NOT NULL,
  answer_value   VARCHAR(1000) NOT NULL,   -- harf (A/B/C/D), yulduz soni ("4"), yoki erkin matn
  FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
  INDEX idx_question (question_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7) XABARNOMALAR
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  sender_id      INT NOT NULL,
  sent_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  matn           TEXT NOT NULL,
  target_type    ENUM('users','department') NOT NULL,
  target_value   VARCHAR(1000) NOT NULL,   -- bo'linma nomi, yoki vergul bilan ajratilgan login'lar
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notification_reads (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  notification_id  INT NOT NULL,
  user_id          INT NOT NULL,
  read_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_notif_user (notification_id, user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8) YORDAM SO'ROVLARI (avvalgi "Support" varag'i)
-- ---------------------------------------------------------------------
CREATE TABLE support_requests (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  murojaat    TEXT NOT NULL,
  izoh        TEXT,      -- eskirgan (endi ishlatilmaydi) — o'rniga support_comments jadvali
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Bitta murojaatga bir nechta xodim (gl-admin/admin) izoh qoldirishi mumkin —
-- har bir izoh kim yozgani va qachonligi bilan alohida qator sifatida saqlanadi.
CREATE TABLE support_comments (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  support_request_id  INT NOT NULL,
  user_id             INT NOT NULL,
  izoh                TEXT NOT NULL,
  created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (support_request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_request (support_request_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9) TIZIM SOZLAMALARI (avvalgi PropertiesService o'rniga — Test/
-- Anonim so'rovnoma aktiv/deaktiv holati va h.k.)
-- ---------------------------------------------------------------------
CREATE TABLE app_settings (
  setting_key    VARCHAR(100) PRIMARY KEY,
  setting_value  VARCHAR(500) NOT NULL
) ENGINE=InnoDB;

INSERT INTO app_settings (setting_key, setting_value) VALUES
  ('test_active', 'true'),
  ('survey_active', 'true');

-- ---------------------------------------------------------------------
-- 10) TIZIM JURNALI (server tomonida ushlangan xatoliklar) — gl-admin
-- serverning fayl tizimiga kirmasdan admin panelidan ko'rishi uchun.
-- ---------------------------------------------------------------------
CREATE TABLE error_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  action      VARCHAR(100),
  message     TEXT NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB;
