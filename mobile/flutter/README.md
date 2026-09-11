# Kompaniya Anticor — Flutter ilova

"Korrupsiyaga qarshi kurashish" portalining Flutter (Android/iOS) mobil ilovasi.
Backend bilan `backend/public/index.php` orqali (Bearer-token autentifikatsiya
bilan) gaplashadi — batafsil: `../../backend/src/Auth.php`,
`AuthController::mobileLogin`/`mobileVerifyTotpLogin`/`mobileLoginViaRememberToken`.

## MUHIM: bu loyiha hali "flutter create" qilinmagan

Bu papkada faqat Dart manba kodi (`lib/`), `pubspec.yaml` va
`analysis_options.yaml` bor — Android/iOS platforma papkalari
(`android/`, `ios/` va h.k.) hali yo'q, chunki ular avtomatik generatsiya
qilinadigan fayllar va ushbu (Flutter SDK o'rnatilmagan) muhitda
yaratib bo'lmadi. Birinchi marta ishga tushirishdan oldin:

```bash
cd mobile/flutter
flutter create --org uz.uztelecom --project-name kompaniya_anticor .
```

Bu mavjud `lib/`, `pubspec.yaml` fayllarni SAQLAB QOLADI (ularning ustiga
yozmaydi) va faqat yetishmayotgan `android/`, `ios/` va h.k. papkalarni
qo'shadi. Shundan keyin:

```bash
flutter pub get
flutter analyze   # kod hech qachon shu muhitda kompilyatsiya qilinmagan —
                   # avval shu buyruq bilan tekshiring
flutter run
```

## Backend manzilini sozlash

`lib/api/api_client.dart` faylidagi `ApiClient.defaultBaseUrl`ni real
backend manzilingizga o'zgartiring:

```dart
static const String defaultBaseUrl = 'https://your-domain.uz/backend/public';
```

Mahalliy rivojlantirishda (backend `php -S` bilan kompyuteringizda
ishlab turganda):
- **Android emulyatori**: `http://10.0.2.2:8080/backend/public`
  (`10.0.2.2` — emulyatordan kompyuterning o'zi shu manzil bilan ko'rinadi)
- **iOS simulyatori**: `http://localhost:8080/backend/public`
- **Haqiqiy qurilma**: kompyuteringizning lokal tarmoq IP manzili, masalan
  `http://192.168.1.50:8080/backend/public`

## Arxitektura qisqacha

- `lib/api/api_client.dart` — backendning yagona action-dispatch
  endpoint'iga POST so'rov yuboradi (`{"action": "...", ...}`), tokenni
  `flutter_secure_storage` (iOS Keychain / Android Keystore) orqali
  saqlaydi va har so'rovga `Authorization: Bearer <token>` qo'shadi.
- `lib/services/session_provider.dart` — `ChangeNotifier`: login/TOTP/
  remember-me/logout oqimlari va ilova qayta ochilganda sessiyani
  tiklash.
- `lib/screens/` — bo'lim bo'yicha ekranlar: `auth/`, `hub/`, `profile/`,
  `docs/`, `test/`, `survey/`, `support/`, `hr_documents/`,
  `notifications/`, `declaration/` (8 bosqichli wizard), `admin/`
  (boshqaruv paneli — rolga qarab ko'rinadi).
- `lib/utils/file_viewer.dart` — Bearer-token bilan himoyalangan PDF
  fayllarni (hujjatlar, HR hujjatlari, xarid shartnomalari, zaxira
  nusxalar) yuklab, vaqtincha faylga saqlab, tizim ko'ruvchisida ochadi.

## v1 qamrovi

Qamrab olingan: login (+ TOTP 2FA, meni eslab qol), profil (rasm,
sessiyalar, parol, 2FA), hujjatlar, test, so'rovnoma, deklaratsiya
wizard'i, yordam, HR hujjatlari, xabarnomalar, va boshqaruv paneli
(xodimlar, tasdiqlash so'rovlari, statistika, xaridlar reyestri,
deklaratsiyalar, tizim jurnali, zaxira nusxa).

Hali qamrab olinmagan (kerak bo'lsa keyingi bosqichda qo'shiladi):
- Hisobotlar bo'limidagi 8 ta XLSX eksport (veb versiyada mavjud) —
  o'rniga soddalashtirilgan "Statistika" ekrani berilgan.
- Push-bildirishnomalar (APNs/FCM) — xabarnomalar hozircha faqat ilova
  ochilganda so'rov orqali yuklanadi, fon rejimida kelmaydi.
- Test/hujjat/so'rovnoma TARKIBINI boshqarish (savol qo'shish/tahrirlash) —
  bu ADMIN uchun kamdan-kam ishlatiladigan amal, veb boshqaruv panelida qoldirildi.
