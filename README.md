# Korrupsiyaga qarshi kurashish — ichki portal

"Korrupsiyaga qarshi kurashish" mavzusidagi ichki ta'lim/hisobot portali. Xodimlar normativ hujjatlar bilan tanishadi, test topshiradi, anonim so'rovnomada ishtirok etadi; admin va bosh admin (gl-admin) xodimlar, hisobotlar va statistikani boshqaradi.

Backend — PHP 8 + MySQL (Composer'siz, sodda PSR-4 avtoyuklovchi). Frontend — oddiy statik HTML/CSS/JS (build vositasi shart emas).

## Talablar

- PHP 8.0+ (`pdo_mysql`, `zip` kengaytmalari yoqilgan bo'lishi kerak)
- MySQL / MariaDB
- Apache (yoki `.htaccess`ni qo'llab-quvvatlaydigan boshqa server) — XAMPP mahalliy test uchun eng qulay

## O'rnatish (XAMPP misolida)

1. Loyihani `htdocs` ichiga joylashtiring, masalan `htdocs/anticor/`.
2. MySQL'da bazani yarating: `schema.sql` faylini phpMyAdmin yoki buyruq qatori orqali ishga tushiring:
   ```
   mysql -u root < schema.sql
   ```
3. `backend/.env.example` faylidan nusxa olib `backend/.env` deb saqlang, DB ma'lumotlarini (host, user, parol) to'ldiring.
4. Bitta gl-admin hisobini yaratish uchun:
   ```
   php backend/migrations/seed_admin.php
   ```
5. Brauzerda oching: `http://localhost/anticor/login.html`

Barcha sahifalardagi API manzili sahifa qayerdan ochilgan bo'lsa (localhost, mahalliy tarmoq IP'si, tunnel yoki haqiqiy domen) o'sha joyning o'ziga nisbatan **avtomatik** hisoblanadi — qo'lda hech narsa sozlash shart emas.

## Boshqa qurilma/odamga sinash uchun ko'rsatish

- **Bir xil Wi-Fi tarmog'ida**: kompyuterning lokal IP manzilini toping (`ipconfig`), Windows Firewall'da 80-portga ruxsat bering, boshqa qurilmada `http://<lokal-IP>/anticor/login.html` oching.
- **Tezkor, istalgan joydan**: [ngrok](https://ngrok.com) yoki shunga o'xshash tunnel xizmati orqali (`ngrok http 80`) vaqtinchalik ochiq havola oling.
- **Doimiy (production)**: haqiqiy PHP+MySQL hosting/VPS'ga joylashtiring, `FORCE_HTTPS=true` qiling va domenga SSL sertifikat o'rnating.

## Rollar

| Rol | Huquqlar |
|---|---|
| `user` | Hujjat bilan tanishish, test topshirish, so'rovnomada ishtirok etish, o'z profilini boshqarish |
| `admin` | + Xodimlar ro'yxati, Yordam so'rovlarini ko'rish/javob berish, bloklangan hisoblarni ochish |
| `gl-admin` (bosh admin) | + Xodim qo'shish/o'chirish, barcha hisobotlar, tizim sozlamalari, zaxira nusxa, tizim jurnali |

## Xavfsizlik

- Parollar bcrypt bilan xeshlanadi, hech qachon ochiq matnda saqlanmaydi yoki eksport qilinmaydi.
- **Parol siyosati**: kamida 8 belgi, katta harf, kichik harf, raqam va maxsus belgi talab qilinadi.
- **Login bloklash**: mavjud bo'lmagan login bilan 3 marta, mavjud login uchun noto'g'ri parol bilan 5 marta xato urinishdan keyin 15 daqiqaga bloklanadi. gl-admin/admin xodimlar ro'yxatidan istalgan vaqtda qo'lda blokdan chiqara oladi.
- **Sessiya**: haqiqiy sessiya tokeni faqat HttpOnly + SameSite=Strict cookie orqali saqlanadi — JavaScript orqali umuman o'qib bo'lmaydi (XSS orqali o'g'irlanishning oldini oladi). 30 daqiqa harakatsizlikdan keyin (yoki 7 kundan keyin mutlaqo) avtomatik tugaydi.
- **Ikki bosqichli tasdiqlash (2FA)**: har bir xodim Sozlamalar orqali TOTP (Google Authenticator va h.k.) asosidagi 2FA'ni ixtiyoriy yoqishi mumkin.
- **Faol sessiyalar**: foydalanuvchi Sozlamalar → "Faol sessiyalar" orqali qaysi qurilmalarda kirganini ko'rib, kerak bo'lsa bekor qila oladi.
- Schema o'z-o'zini davolaydi (`Util::ensureSchema`) — keyinroq qo'shilgan jadval/ustunlar avtomatik yaratiladi, admin `schema.sql`ni qo'lda qayta ishga tushirishi shart emas.

## Zaxira nusxa (backup)

Admin panelida **Zaxira nusxa** bo'limida (gl-admin) bitta tugma bosib darhol MySQL bazasi va profil rasmlarining nusxasini olish, mavjud nusxalarni yuklab olish mumkin.

Kunlik avtomatik zaxira uchun `backend/scripts/backup.php` skriptini rejalashtiring:

- **Windows (XAMPP)**: Task Scheduler'da yangi vazifa — dastur: `C:\xampp\php\php.exe`, argument: skriptning to'liq yo'li, kuniga bir marta (masalan 03:00).
- **Linux/cPanel**: crontab, masalan:
  ```
  0 3 * * * php /full/path/backend/scripts/backup.php >> /full/path/backend/backups/cron.log 2>&1
  ```

Nusxalar `backend/backups/` papkasida saqlanadi (web orqali to'g'ridan-to'g'ri ochilmaydi), `BACKUP_KEEP_DAYS` (standart 30 kun)dan eskilari avtomatik o'chiriladi.

## Tizim jurnali

Server tomonida yuzaga kelgan kutilmagan xatoliklar `error_log` jadvaliga yoziladi — gl-admin serverning fayl tizimiga kirmasdan, admin panelidan **Tizim jurnali** bo'limida so'nggi 200 ta xatolikni ko'rishi mumkin. 90 kundan eski yozuvlar avtomatik tozalanadi.

## Muammolarni bartaraf etish

- **"Serverga ulanishda xatolik"** — odatda sahifa eski (yangilanmagan) fayllar bilan ochilganda yuzaga keladi. GitHub'dan eng so'nggi kodni qayta yuklab, barcha `.html` fayllarni almashtiring.
- **Yordam so'rovlari/hisobotlar ko'rinmayapti** — backend o'z-o'zini davolaydi, lekin agar muammo davom etsa, MySQL foydalanuvchisi kerakli jadvallarga `ALTER TABLE`/`CREATE TABLE` huquqiga ega ekanini tekshiring.
- **mysqldump topilmadi (backup)** — `backend/.env`da `MYSQLDUMP_PATH` orqali to'liq yo'lni ko'rsating (masalan Windows'da `C:\xampp\mysql\bin\mysqldump.exe`).
