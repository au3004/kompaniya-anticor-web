# Kompaniya Anticor — iOS (Swift/SwiftUI) ilova

"Korrupsiyaga qarshi kurashish" portalining native iOS (SwiftUI) ilovasi.
Backend bilan `backend/public/index.php` orqali (Bearer-token
autentifikatsiya bilan) gaplashadi — batafsil:
`../../backend/src/Auth.php`, `AuthController::mobileLogin`/
`mobileVerifyTotpLogin`/`mobileLoginViaRememberToken`.

## MUHIM: bu papkada Xcode loyihasi (.xcodeproj) yo'q

Bu yerda faqat Swift manba kodi bor (`KompaniyaAnticor/` papkasi ichida,
guruh-guruh). `.xcodeproj`/`.pbxproj` fayli qo'lda yaratilmadi — bu format
juda nozik (bitta noto'g'ri UUID yoki qavs butun loyihani Xcode'da
ochilmaydigan qilib qo'yishi mumkin) va bu muhitda (Linux, Xcode yo'q)
uni haqiqiy Xcode bilan sinab ko'rish imkonsiz edi. Xato pbxproj
generatsiya qilishdan ko'ra, sizga ishonchli, standart yo'lni taklif
qilish afzalroq deb topildi:

1. **Xcode'da**: File → New → Project → iOS → App
   - Product Name: `KompaniyaAnticor`
   - Interface: **SwiftUI**, Language: **Swift**
   - Minimum Deployment: **iOS 16.0** (PhotosPicker, NavigationStack
     kabi API'lar shuni talab qiladi)
2. Xcode avtomatik yaratgan `KompaniyaAnticorApp.swift` va
   `ContentView.swift` fayllarini o'chiring (yoki loyihadan olib
   tashlang).
3. Shu papkadagi `KompaniyaAnticor/` ichidagi barcha guruh/fayllarni
   (Models, Networking, Services, Utilities, Views) Finder'dan Xcode
   loyihasi navigatoriga **sudrab olib kiring** ("Copy items if needed"
   belgilangan holda, "Create groups" tanlangan holda).
4. `Info.plist`ga (yoki loyiha sozlamalaridagi "App Transport
   Security" bo'limiga) mahalliy (HTTP, HTTPS emas) backend bilan
   sinash uchun quyidagini qo'shing — **faqat rivojlantirish uchun**,
   ishlab chiqarish (production) muhitida backend albatta HTTPS bo'lishi
   shart:
   ```xml
   <key>NSAppTransportSecurity</key>
   <dict>
       <key>NSAllowsArbitraryLoads</key>
       <true/>
   </dict>
   ```
5. `Networking/APIClient.swift`dagi `APIClient.defaultBaseURL`ni real
   (yoki mahalliy) backend manzilingizga o'zgartiring:
   ```swift
   static let defaultBaseURL = "https://your-domain.uz/backend/public"
   ```
   Mahalliy rivojlantirishda (backend `php -S` bilan kompyuteringizda
   ishlab turganda):
   - **iOS simulyatori**: `http://localhost:8080/backend/public`
     ishlaydi (simulyator xost kompyuterning localhost'ini
     to'g'ridan-to'g'ri ko'radi).
   - **Haqiqiy qurilma**: kompyuteringizning lokal tarmoq IP manzilini
     yozing, masalan `http://192.168.1.50:8080/backend/public`.
6. Build & Run (⌘R).

Bu kod hech qachon shu (Xcode'siz) muhitda kompilyatsiya qilinmagan —
birinchi build'da kichik xatoliklar (masalan bitta import yetishmasligi)
chiqishi mumkin. Shunday holat bo'lsa, xatolik matnini menga yuboring —
darhol tuzataman.

## Arxitektura qisqacha

- `Networking/APIClient.swift` — backendning yagona action-dispatch
  endpoint'iga POST so'rov yuboradi (`{"action": "...", ...}`), tokenni
  `Networking/KeychainStore.swift` (iOS Keychain, uchinchi tomon
  kutubxonasiz) orqali saqlaydi va har so'rovga
  `Authorization: Bearer <token>` qo'shadi.
- `Services/SessionStore.swift` — `ObservableObject`: login/TOTP/
  remember-me/logout oqimlari va ilova qayta ochilganda sessiyani
  tiklash. `@EnvironmentObject` sifatida butun view daraxtiga beriladi
  (`Views/App/KompaniyaAnticorApp.swift`).
- `Views/` — bo'lim bo'yicha ekranlar: `Auth/`, `Hub/`, `Profile/`,
  `Docs/`, `Test/`, `Survey/`, `Support/`, `HrDocuments/`,
  `Notifications/`, `Declaration/` (8 bosqichli wizard, modal sheet
  sifatida), `Admin/` (boshqaruv paneli — rolga qarab ko'rinadi).
- `Utilities/FileViewer.swift` — Bearer-token bilan himoyalangan PDF
  fayllarni (hujjatlar, HR hujjatlari, xarid shartnomalari, zaxira
  nusxalar) yuklab, vaqtincha faylga saqlab, QuickLook orqali ko'rsatadi.

## v1 qamrovi

Flutter ilovasi bilan bir xil (`../flutter/README.md`ga qarang):
qamrab olingan/olinmagan ro'yxati bir xil — ikkala ilova ham bir xil
backend amallaridan, bir xil matn/atama va savol matnlaridan
foydalanadi (veb versiya — main.html/admin.html — bilan mos).
