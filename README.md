# Korrupsiyaga qarshi kurashish — ichki portal

"Korrupsiyaga qarshi kurashish" mavzusidagi ichki ta'lim/hisobot portali. Xodimlar normativ hujjatlar bilan tanishadi, test topshiradi, sertifikat oladi, anonim so'rovnomada ishtirok etadi; adminlar xodimlar, hisobotlar, statistika va sozlamalarni boshqaradi.

Backend — PHP 8 + MySQL (Composer'siz, sodda PSR-4 avtoyuklovchi). Frontend — oddiy statik HTML/CSS/JS (build vositasi va tashqi CDN shart emas — ichki tarmoqda internetsiz ishlaydi).

## Talablar

- PHP 8.0+ (`pdo_mysql`, `zip` kengaytmalari yoqilgan bo'lishi kerak; `gd` — ixtiyoriy)
- MySQL / MariaDB
- Apache (yoki `.htaccess`ni qo'llab-quvvatlaydigan boshqa server) — XAMPP mahalliy test uchun eng qulay

## O'rnatish (XAMPP misolida)

1. Loyihani `htdocs` ichiga joylashtiring, masalan `htdocs/anticor/`.
2. MySQL'da bazani yarating: `schema.sql` faylini phpMyAdmin yoki buyruq qatori orqali ishga tushiring:
   ```
   mysql -u root < schema.sql
   ```
3. `backend/.env.example` faylidan nusxa olib `backend/.env` deb saqlang, DB ma'lumotlarini (host, user, parol) to'ldiring.
4. Yagona **super-admin** hisobini yaratish uchun:
   ```
   php backend/migrations/seed_admin.php <login> <parol> <familiya> <ism>
   ```
5. Brauzerda oching: `http://localhost/anticor/login.html`

Barcha sahifalardagi API manzili sahifa qayerdan ochilgan bo'lsa (localhost, mahalliy tarmoq IP'si, tunnel yoki haqiqiy domen) o'sha joyning o'ziga nisbatan **avtomatik** hisoblanadi — qo'lda hech narsa sozlash shart emas.

Keyinroq qo'shilgan jadval/ustunlar birinchi so'rovda avtomatik yaratiladi (`schema_migrations` jadvalida belgilanadi va qayta bajarilmaydi) — yangilanishdan keyin `schema.sql`ni qo'lda qayta ishga tushirish shart emas.

## Boshqa qurilma/odamga sinash uchun ko'rsatish

- **Bir xil Wi-Fi tarmog'ida**: kompyuterning lokal IP manzilini toping (`ipconfig`), Windows Firewall'da 80-portga ruxsat bering, boshqa qurilmada `http://<lokal-IP>/anticor/login.html` oching.
- **Tezkor, istalgan joydan**: [ngrok](https://ngrok.com) yoki shunga o'xshash tunnel xizmati orqali (`ngrok http 80`) vaqtinchalik ochiq havola oling.
- **Doimiy (production)**: haqiqiy PHP+MySQL hosting/VPS'ga joylashtiring, `FORCE_HTTPS=true` qiling va domenga SSL sertifikat o'rnating.

## Serverga joylashtirishdan oldin (majburiy tekshiruv ro'yxati)

Mahalliy XAMPP sozlamalari haqiqiy serverda xavfli — ishga tushirishdan oldin har bir bandni bajaring:

1. **MySQL foydalanuvchisi.** `backend/.env`da `DB_USER=root` va bo'sh `DB_PASS=` qoldirilmasin. Faqat shu bazaga huquqi bor alohida foydalanuvchi yarating va `.env`ga yozing:
   ```sql
   CREATE USER 'anticor_user'@'localhost' IDENTIFIED BY '<kuchli-parol>';
   GRANT ALL PRIVILEGES ON kompaniya_anticor.* TO 'anticor_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. **HTTPS.** Domenga SSL sertifikat o'rnating va `.env`da `FORCE_HTTPS=true` qiling.
3. **Manzil.** `.env`da `PUBLIC_BASE_URL` haqiqiy domenga o'zgartirilsin (sertifikatlardagi QR-kodlar ham shu manzilga olib boradi — o'zgargach sertifikatlar avtomatik yangilanadi); `ALLOWED_ORIGINS` bo'sh qolsin (`*` yozilmasin).
4. **`.htaccess` ishlashi.** Apache'da `AllowOverride All` yoqilgan bo'lsin — `backend/` (kod va `.env`), `Hujjatlar/` hamda loyiha ildizidagi ichki fayllarni (`.git` tarixi, `schema.sql`, `*.md`, `mobile/`) veb orqali ochilishdan aynan shu fayllar himoya qiladi; ildizdagi `.htaccess` qo'shimcha ravishda clickjacking'ga qarshi sarlavhalarni qo'yadi (`mod_headers`). Nginx ishlatilsa, xuddi shu taqiqlarni server sozlamasida qo'lda yozing. Tekshirish: brauzerda `https://<domen>/anticor/.git/config` va `.../schema.sql` ochilmasligi (404/403) kerak.
5. **Yoziladigan papkalar.** PHP quyidagilarga yoza olishi kerak: `backend/documents`, `backend/hr_documents`, `backend/purchase_contracts`, `backend/certificates`, `backend/backups`, `backend/public/uploads/photos` (ixtiyoriy: `backend/fonts/unifont`).
6. **Zaxira nusxa.** `MYSQLDUMP_PATH`ni tekshiring va `backend/scripts/backup.php`ni kunlik rejalashtiring (qarang: "Zaxira nusxa").
7. **Test sozlamalari.** `.env`dagi `TEST_PASS_THRESHOLD`, `TEST_MAX_ATTEMPTS` hamda admin panelidagi muddatlar (Test savollari → Muddatlar) kerakligiga ishonch hosil qiling.
8. **Avtomatik testlar.** Yangilanishdan oldin mahalliy kompyuterda `php backend/tests/run.php` ishga tushirib, hammasi o'tganini tekshiring (qarang: "Avtomatik testlar").

## Rollar

| Rol | Nimalar qila oladi |
|---|---|
| `user` (xodim) | Hujjatlar bilan tanishish, test topshirish, sertifikat olish, so'rovnoma, deklaratsiya, Inson resurslariga hujjat yuborish, o'z profili va sessiyalari |
| `anticor` | + Korrupsiyaga qarshi kurashish statistikasi, hisobotlarni ko'rish/yuklab olish, xabarnoma va eslatma yuborish, yordam so'rovlariga javob, deklaratsiyalarni ko'rish |
| `anticor-admin` | + Xodimlarni qo'shish/tahrirlash/o'chirish (super-admin'dan tashqari hammani), test/so'rovnoma savollari, hujjatlar, test muddatlari, sertifikat sozlamalari, qayta topshirishga ruxsat, yozuvlarni o'chirish, sertifikatni bekor qilish, xaridlar, Inson resurslariga yuborilgan hujjatlar, zaxira nusxa, Tizim jurnali (amallar jurnali va server xatoliklari) |
| `super-admin` | Hamma narsa; tizimda **faqat bitta** bo'ladi va hech qaysi ro'yxat, hisobot yoki jurnalda ko'rinmaydi (ID-000) |

## Xavfsizlik

- Parollar bcrypt bilan xeshlanadi, hech qachon ochiq matnda saqlanmaydi yoki eksport qilinmaydi.
- **Login formati**: faqat harf, raqam va `. _ - @` belgilari (3–100) — HTML/JS'ga tushib XSS'ga yo'l ochmasligi uchun.
- **Parol siyosati**: kamida 8 belgi, katta harf, kichik harf, raqam va maxsus belgi talab qilinadi; ma'lumotlar sizib chiqishlarida uchragan parollar rad etiladi.
- **Login bloklash**: mavjud bo'lmagan login bilan 3 marta, mavjud login uchun noto'g'ri parol bilan 5 marta xato urinishdan keyin `LOGIN_LOCK_MINUTES` (standart 15) daqiqaga bloklanadi. anticor-admin xodimlar ro'yxatidan istalgan vaqtda qo'lda blokdan chiqara oladi.
- **Sessiya**: haqiqiy sessiya tokeni faqat HttpOnly + SameSite=Strict cookie orqali saqlanadi — JavaScript orqali umuman o'qib bo'lmaydi. `SESSION_IDLE_MINUTES` (standart 10) daqiqa harakatsizlikdan keyin yoki `SESSION_ABSOLUTE_TTL_HOURS` (standart 168 soat = 7 kun) o'tgach avtomatik tugaydi.
- **Ikki bosqichli tasdiqlash (2FA)**: har bir xodim Sozlamalar orqali TOTP (Google Authenticator va h.k.) asosidagi 2FA'ni ixtiyoriy yoqishi mumkin.
- **Faol sessiyalar**: foydalanuvchi Sozlamalar → "Faol sessiyalar" orqali qaysi qurilmalarda kirganini ko'rib, kerak bo'lsa bekor qila oladi.
- **Amallar jurnali**: adminlarning barcha o'zgartiruvchi amallari (kim, qachon, nima) yozib boriladi — qarang: "Tizim jurnali".

## Statistika, filiallar va eslatmalar

Korrupsiyaga qarshi kurashish → Statistika sahifasida:

- **Filial filtri** — ro'yxatdan filial tanlansa, kartochkalar, jadval va "Bajarilmaganlar hisoboti" faqat shu filial bo'yicha hisoblanadi.
- **Filiallar kesimida** tugmasi — har bir filial bo'yicha jamlanma (xodimlar, hujjat bilan tanishgan, testdan o'tgan/o'tmagan, boshlamagan, bajarilish %). Qator bosilsa — shu filialga filtr. Bu jamlanma Excel hisobotida alohida varaq sifatida ham bor.
- **O'tmagan** kartochkasi bosilsa — faqat testdan o'ta olmaganlar; ular qatorida qayta topshirishga ruxsat tugmasi.
- **Eslatma yuborish** — bitta tugma bilan testni topshirmagan va/yoki hujjat bilan tanishmagan xodimlarga (tanlangan filial bo'yicha) xabarnoma yuboriladi; yuborishdan oldin nechta xodimga borishi ko'rsatiladi. Topshirish muddati belgilangan bo'lsa, eslatmada ham yoziladi.

## Test, muddatlar va qayta attestatsiya

- Har bir xodim testni **1 marta** topshiradi (`.env`da `TEST_MAX_ATTEMPTS`); testdan o'tgan xodim qayta topshirmaydi.
- **O'ta olmaganlarga** admin statistika jadvalidagi tugma bilan **qayta topshirishga ruxsat** beradi — xodimga avtomatik xabarnoma boradi, har bir ruxsat yana 1 ta urinish beradi. Ruxsatlar (kim, qachon) va qayta topshirilgan natijalar **Progress** hisobotida belgilanadi.
- **Muddatlar** (Test savollari → Muddatlar, istalgan paytda o'zgartiriladi):
  - *Topshirishning oxirgi muddati* — xodimlarga test sahifasida, adminga statistikada ("N kun qoldi" / "o'tib ketgan") va eslatmalarda ko'rsatiladi. Muddat o'tgach ham test yopilmaydi.
  - *Sertifikat amal qilish muddati* (oyda, standart 12; 0 — muddatsiz) — **yillik qayta attestatsiya**: muddati tugagan sertifikat amal qilmaydi (QR ham shuni ko'rsatadi), xodim statistikada "Qayta attestatsiya" belgisi bilan chiqadi va yana 1 ta urinish bilan testni qaytadan topshiradi. Muddat o'zgartirilsa, hammasi shu zahoti yangi qiymat bo'yicha hisoblanadi.
- Test natijalarini (Hisobotlar → Test natijalari) o'chirish ham sertifikatni olib qo'yadi; xodimning barcha natijalari o'chirilsa, ruxsatlari ham tozalanib, u yana 1 ta urinishdan boshlaydi.

## Sertifikat

Testdan o'tgan xodimga avtomatik PDF sertifikat yaratiladi: test natijasi sahifasidan va "Holatingizni tekshiring" kartasidan yuklab olinadi, admin esa statistika jadvalidan istalgan xodimnikini ochadi.

- **Sertifikatdagi ma'lumotlar:** raqam (`AK-<yil>-<urinish raqami>`), F.I.Sh, filial, bo'linma, to'plangan ball, sana, amal qilish muddati (belgilangan bo'lsa) va pastda ikki imzo: xodim filialining rahbari hamda komplaens departamenti direktori (QR-kod bilan).
- **Imzo qo'yuvchilar** admin panelidagi **Sertifikat** bo'limida kiritiladi (har bir filial uchun lavozim va F.I.Sh, komplaens direktori). Shu yerda har bir filial bo'yicha namunani ko'rish mumkin. Sertifikatga chiqadigan har qanday ma'lumot o'zgarsa (imzo qo'yuvchilar, F.I.Sh, filial, bo'linma, muddat), sertifikat keyingi ochilishda avtomatik yangilanadi.
- **Sertifikat olganlar ro'yxati** Hisobotlar → **Sertifikatlar** qatorida: ko'rish (PDF), Excel'ga yuklab olish va bekor qilish. Bekor qilingan sertifikat xodimda ko'rinmaydi, QR "amal qilmaydi" deb ko'rsatadi, test natijasi esa saqlanadi; sertifikat xodim testni qaytadan muvaffaqiyatli topshirgandagina yana beriladi.
- **QR-kod** `backend/public/certificate-verify.php` sahifasiga olib boradi — u login talab qilmaydi va faqat taxmin qilib bo'lmaydigan havola orqali sertifikat haqiqiyligini (raqam, F.I.Sh, filial, sana, amal qilish muddati, imzolagan shaxs) ko'rsatadi.
- **Dizayn** `backend/src/CertificatePdf.php` faylida (dizayner maketi `sertifikat_shablon.html` asosida), rasmlari `backend/certificate_assets/` papkasida. Yakuniy dizayn bilan almashtirilganda faqat shu joylar o'zgaradi; `CertificatePdf::VERSION` oshirilsa, barcha sertifikatlar yangi dizaynda qayta yaratiladi.
- PDF'lar `backend/certificates/` papkasida saqlanadi — bu papka PHP uchun yoziladigan bo'lishi shart. Fayl yo'qolsa, keyingi yuklab olishda avtomatik qayta yaratiladi.
- Shriftlar: Poppins (SIL OFL) va kirill harflari uchun DejaVu Sans (`backend/fonts/unifont/`), PDF kutubxonasi — tFPDF (`backend/src/Vendor/tfpdf/`, LGPL). `backend/fonts/unifont/` yoziladigan bo'lsa, shrift o'lchamlari keshlanib, generatsiya tezlashadi (majburiy emas).

## Hujjatlar

Normativ hujjatlar ikki yo'l bilan qo'shiladi:

- **Hujjatlar/ papkasi orqali** — loyiha ildizidagi `Hujjatlar/` papkasiga (masalan `htdocs/anticor/Hujjatlar`) PDF fayl tashlansa, u keyingi safar hujjatlar ro'yxati ochilganda avtomatik paydo bo'ladi (nomi fayl nomidan olinadi, admin panelidan o'zgartirish mumkin). Papkadan o'chirilgan fayl ro'yxatdan ham yo'qoladi. Papkadagi `.htaccess` fayllarga to'g'ridan-to'g'ri havola orqali (login qilmasdan) kirishni taqiqlaydi — uni o'chirmang.
- **Admin panelidan** — "Hujjat qo'shish" bo'limida PDF yuklanadi (`backend/documents/`da saqlanadi).

## Hisobotlar (Excel)

Barcha hisobotlar haqiqiy `.xlsx` fayl sifatida yuklab olinadi (qalin sarlavha, ustun kengliklari, qotirilgan sarlavha qatori). Excel fayllarini loyihaning o'z `assets/xlsx-lite.js` moduli yaratadi — tashqi kutubxona yoki internet talab qilinmaydi.

## Zaxira nusxa (backup)

Admin panelida **Zaxira nusxa** bo'limida (anticor-admin) bitta tugma bosib darhol MySQL bazasi va profil rasmlarining nusxasini olish, mavjud nusxalarni yuklab olish mumkin.

Kunlik avtomatik zaxira uchun `backend/scripts/backup.php` skriptini rejalashtiring:

- **Windows (XAMPP)**: Task Scheduler'da yangi vazifa — dastur: `C:\xampp\php\php.exe`, argument: skriptning to'liq yo'li, kuniga bir marta (masalan 03:00).
- **Linux/cPanel**: crontab, masalan:
  ```
  0 3 * * * php /full/path/backend/scripts/backup.php >> /full/path/backend/backups/cron.log 2>&1
  ```

Nusxalar `backend/backups/` papkasida saqlanadi (web orqali to'g'ridan-to'g'ri ochilmaydi), `BACKUP_KEEP_DAYS` (standart 30 kun)dan eskilari avtomatik o'chiriladi.

## Tizim jurnali

Admin panelidagi **Tizim jurnali** bo'limi (faqat anticor-admin) ikki qismdan iborat:

- **Amallar jurnali** — adminlarning barcha o'zgartiruvchi amallari: xodim qo'shish/tahrirlash (qaysi maydonlar o'zgargani, rol/filial — eskisi → yangisi)/o'chirish, blokdan chiqarish, test va so'rovnoma savollari, hujjatlar, natijalar va boshqa yozuvlarni o'chirish, sertifikatni bekor qilish, imzo qo'yuvchilar, muddatlar, qayta topshirish ruxsatlari, xabarnoma va eslatmalar, zaxira nusxa va h.k. Qidirish va Excel'ga yuklab olish mumkin (so'nggi 2000 ta yozuv ko'rsatiladi, yozuvlar o'chirilmaydi). super-admin amallari yozilmaydi.
- **Server xatoliklari** — server tomonida yuzaga kelgan kutilmagan xatoliklar (`error_log` jadvali), so'nggi 200 tasi. 90 kundan eski yozuvlar avtomatik tozalanadi.

## Avtomatik testlar

`backend/tests/run.php` — asosiy oqimlarni (login, rollar va ruxsatlar, test topshirish va qayta topshirish ruxsati, sertifikat va QR tekshiruvi, muddatlar va qayta attestatsiya, eslatmalar, amallar jurnali, super-admin yashirinligi va h.k.) haqiqiy server va baza bilan tekshiradi. Composer yoki PHPUnit shart emas:

```
php backend/tests/run.php
```

Skript `backend/.env`dagi MySQL ulanishidan foydalanib **alohida vaqtinchalik baza** (`kompaniya_anticor_test`) yaratadi, `schema.sql`ni yuklaydi, o'rnatilgan PHP serverini vaqtincha ishga tushiradi, testlarni bajaradi va oxirida bazani o'chiradi — asosiy (ishchi) bazaga tegmaydi. MySQL foydalanuvchisida baza yaratish huquqi bo'lishi kerak (XAMPP'dagi `root` — yetarli). Windows'da: `C:\xampp\php\php.exe backend\tests\run.php`.

## Muammolarni bartaraf etish

- **"Serverga ulanishda xatolik"** — odatda sahifa eski (yangilanmagan) fayllar bilan ochilganda yuzaga keladi. GitHub'dan eng so'nggi kodni qayta yuklab, barcha fayllarni almashtiring va brauzerda Ctrl+F5 bosing.
- **Yordam so'rovlari/hisobotlar ko'rinmayapti** — backend o'z-o'zini davolaydi, lekin agar muammo davom etsa, MySQL foydalanuvchisi kerakli jadvallarga `ALTER TABLE`/`CREATE TABLE` huquqiga ega ekanini tekshiring.
- **Jadvalni qo'lda o'chirib yuborgandan keyin qayta yaratilmayapti** — avtomatik migratsiyalar `schema_migrations` jadvalida belgilanadi; shu jadvalni tozalasangiz (`TRUNCATE schema_migrations`), keyingi so'rovda hammasi qayta tekshiriladi.
- **mysqldump topilmadi (backup)** — `backend/.env`da `MYSQLDUMP_PATH` orqali to'liq yo'lni ko'rsating (masalan Windows'da `C:\xampp\mysql\bin\mysqldump.exe`).
