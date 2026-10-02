import Foundation

/// Backendning yagona action-dispatch endpoint'i bilan gaplashadigan past
/// darajadagi klient. Har bir chaqiruv `POST {baseURL}/index.php`ga
/// `{"action": "...", ...}` JSON tanasi bilan boradi.
///
/// Autentifikatsiya: backend HttpOnly cookie'dan tashqari
/// "Authorization: Bearer <token>" sarlavhasini ham qabul qiladi (qarang:
/// backend/src/Auth.php::tokenFromRequest()). Token doim Keychain orqali
/// saqlanadi — hech qachon UserDefaults yoki oddiy xotirada emas.
final class APIClient {
    /// Backend'ning ochiq manzili — server joylashuviga qarab o'zgartiring.
    /// Mahalliy rivojlantirishda: iOS simulyatorida "http://localhost:8080/backend/public"
    /// ishlaydi (simulyator xost kompyuterning localhost'ini to'g'ridan-to'g'ri ko'radi);
    /// haqiqiy qurilmada kompyuteringizning lokal tarmoq IP manzilini yozing.
    static let defaultBaseURL = "https://your-domain.uz/backend/public"

    let baseURL: String
    private let session: URLSession
    private static let tokenKey = "session_token"
    private static let rememberTokenKey = "remember_token"

    init(baseURL: String = APIClient.defaultBaseURL, session: URLSession = .shared) {
        self.baseURL = baseURL
        self.session = session
    }

    var storedToken: String? { KeychainStore.get(Self.tokenKey) }
    func saveToken(_ token: String) { KeychainStore.set(token, forKey: Self.tokenKey) }
    func clearToken() { KeychainStore.delete(Self.tokenKey) }

    /// "Meni eslab qol" tokeni — sessiya harakatsizlikdan (10 daqiqa) tugab
    /// qolganda parolsiz jim qayta kirish uchun (mobileLoginViaRememberToken).
    /// Mutlaq 1 soatlik umr chegarasiga ega, hech qachon uzaytirilmaydi.
    var storedRememberToken: String? { KeychainStore.get(Self.rememberTokenKey) }
    func saveRememberToken(_ token: String) { KeychainStore.set(token, forKey: Self.rememberTokenKey) }
    func clearRememberToken() { KeychainStore.delete(Self.rememberTokenKey) }

    /// Asosiy chaqiruv usuli. `params` ichiga `action`ni qo'shmang — u
    /// alohida beriladi. Muvaffaqiyatli javobning to'liq xaritasini
    /// qaytaradi; xatolikda APIError otadi.
    @discardableResult
    func call(_ action: String, _ params: [String: Any] = [:]) async throws -> [String: Any] {
        guard let url = URL(string: "\(baseURL)/index.php") else {
            throw APIError(code: "BAD_URL", message: "Noto'g'ri server manzili", statusCode: 0)
        }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if let token = storedToken, !token.isEmpty {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        var body = params
        body["action"] = action
        request.httpBody = try? JSONSerialization.data(withJSONObject: body)

        let data: Data
        let response: URLResponse
        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw APIError(code: "NETWORK_ERROR", message: "Tarmoq xatoligi: so'rov yuborilmadi", statusCode: 0)
        }

        let statusCode = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            throw APIError(code: "BAD_RESPONSE", message: "Server javobini o'qib bo'lmadi", statusCode: statusCode)
        }

        if (json["success"] as? Bool) != true {
            throw APIError(
                code: json["code"] as? String ?? "ERROR",
                message: json["message"] as? String ?? "Noma'lum xatolik",
                statusCode: statusCode
            )
        }
        return json
    }

    /// document-download.php / hr-document-download.php / purchase-download.php
    /// / backup-download.php kabi JSON action-dispatch'dan tashqaridagi GET
    /// skriptlarni Bearer sarlavhasi bilan yuklab oladi. [pathAndQuery]
    /// `baseURL`dan keyingi qism, masalan "document-download.php?id=5".
    func downloadFile(_ pathAndQuery: String) async throws -> Data {
        guard let url = URL(string: "\(baseURL)/\(pathAndQuery)") else {
            throw APIError(code: "BAD_URL", message: "Noto'g'ri server manzili", statusCode: 0)
        }
        var request = URLRequest(url: url)
        if let token = storedToken, !token.isEmpty {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        let data: Data
        let response: URLResponse
        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw APIError(code: "NETWORK_ERROR", message: "Tarmoq xatoligi: fayl yuklanmadi", statusCode: 0)
        }
        let statusCode = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard statusCode == 200 else {
            throw APIError(code: "DOWNLOAD_FAILED", message: "Faylni yuklab bo'lmadi", statusCode: statusCode)
        }
        return data
    }

    /// Binary faylni backend kutgan "data:<mime>;base64,..." shakliga o'giradi
    /// (hujjat/rasm yuklash amallari — addDocument, submitHrDocument,
    /// addPurchase, updateProfilePhoto — shu formatni JSON tana ichida kutadi).
    static func toDataURL(_ data: Data, mimeType: String) -> String {
        "data:\(mimeType);base64,\(data.base64EncodedString())"
    }
}

/// `[String: Any]` javob xaritasidan qulay tipdagi qiymat olish uchun kichik yordamchilar.
extension Dictionary where Key == String, Value == Any {
    func str(_ key: String) -> String? { self[key] as? String }
    func int(_ key: String) -> Int? {
        if let v = self[key] as? Int { return v }
        if let v = self[key] as? NSNumber { return v.intValue }
        return nil
    }
    func bool(_ key: String) -> Bool? { self[key] as? Bool }
    func double(_ key: String) -> Double? {
        if let v = self[key] as? Double { return v }
        if let v = self[key] as? NSNumber { return v.doubleValue }
        if let v = self[key] as? String { return Double(v) }
        return nil
    }
    func arr(_ key: String) -> [[String: Any]] {
        (self[key] as? [[String: Any]]) ?? []
    }
}
