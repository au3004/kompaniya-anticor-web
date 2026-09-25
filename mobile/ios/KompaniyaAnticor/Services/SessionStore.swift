import Foundation

/// Ilova bo'ylab sessiya holatini (joriy foydalanuvchi, TOTP 2FA
/// kutilayotgan bosqich) boshqaradi. @main App struct'da @StateObject
/// sifatida yaratiladi va .environmentObject orqali butun view daraxtiga
/// beriladi.
@MainActor
final class SessionStore: ObservableObject {
    let api: APIClient

    @Published var user: AppUser?
    @Published var isLoading = true
    @Published var pendingTotpToken: String?

    private var rememberMeRequested = false

    var isAuthenticated: Bool { user != nil }
    var needsTotp: Bool { pendingTotpToken != nil }

    init(api: APIClient = APIClient()) {
        self.api = api
        Task { await restore() }
    }

    private func restore() async {
        if let token = api.storedToken, !token.isEmpty {
            do {
                let data = try await api.call("me")
                user = AppUser(json: data)
                isLoading = false
                return
            } catch {
                // Sessiya (10 daqiqa harakatsizlikdan) tugagan bo'lishi mumkin —
                // pastda remember-token orqali jim qayta kirishga urinib ko'ramiz.
                api.clearToken()
            }
        }

        if let rememberToken = api.storedRememberToken, !rememberToken.isEmpty {
            do {
                let data = try await api.call("mobileLoginViaRememberToken", ["rememberToken": rememberToken])
                isLoading = false
                completeLogin(data)
                return
            } catch {
                // Remember-token ham (masalan 1 soatlik mutlaq muddati o'tib)
                // yaroqsiz bo'lsa, foydalanuvchi parolni qayta kiritishi kerak.
                api.clearRememberToken()
            }
        }

        user = nil
        isLoading = false
    }

    /// Login qadam 1. Agar foydalanuvchida 2FA yoqilgan bo'lsa, [needsTotp]
    /// true bo'lib qoladi va chaqiruvchi TOTP kod ekraniga o'tishi kerak.
    func login(login: String, parol: String, rememberMe: Bool = false) async throws {
        rememberMeRequested = rememberMe
        var params: [String: Any] = ["login": login, "parol": parol]
        if rememberMe { params["rememberMe"] = true }
        let data = try await api.call("mobileLogin", params)
        if (data["needsTotp"] as? Bool) == true {
            pendingTotpToken = data["pendingToken"] as? String
            return
        }
        completeLogin(data)
    }

    /// Login qadam 2 (faqat 2FA yoqilgan hisoblar uchun).
    func verifyTotp(code: String) async throws {
        var params: [String: Any] = ["pendingToken": pendingTotpToken ?? "", "code": code]
        if rememberMeRequested { params["rememberMe"] = true }
        let data = try await api.call("mobileVerifyTotpLogin", params)
        pendingTotpToken = nil
        completeLogin(data)
    }

    func cancelTotp() {
        pendingTotpToken = nil
    }

    private func completeLogin(_ data: [String: Any]) {
        if let token = data["sessionToken"] as? String {
            api.saveToken(token)
        }
        if let rememberToken = data["rememberToken"] as? String, !rememberToken.isEmpty {
            api.saveRememberToken(rememberToken)
        }
        user = AppUser(json: data)
    }

    func logout() async {
        _ = try? await api.call("logout")
        api.clearToken()
        api.clearRememberToken()
        user = nil
    }

    /// Profil ma'lumotlari o'zgargandan keyin (masalan rasm yuklangach) qayta yuklaydi.
    func refreshProfile() async {
        if let data = try? await api.call("me") {
            user = AppUser(json: data)
        }
    }
}
