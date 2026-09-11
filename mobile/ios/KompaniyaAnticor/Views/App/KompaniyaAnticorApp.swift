import SwiftUI

@main
struct KompaniyaAnticorApp: App {
    @StateObject private var session = SessionStore()

    var body: some Scene {
        WindowGroup {
            RootRouterView()
                .environmentObject(session)
        }
    }
}

/// Sessiya holatiga qarab tegishli ildiz ekranni ko'rsatadi: yuklanmoqda ->
/// splash, 2FA kutilmoqda -> TOTP ekrani, kirilmagan -> login, kirilgan -> Hub.
struct RootRouterView: View {
    @EnvironmentObject var session: SessionStore

    var body: some View {
        Group {
            if session.isLoading {
                SplashView()
            } else if session.needsTotp {
                TotpView()
            } else if session.isAuthenticated {
                HubView()
            } else {
                LoginView()
            }
        }
    }
}

struct SplashView: View {
    var body: some View {
        ZStack {
            AppColors.azure.ignoresSafeArea()
            ProgressView().tint(.white).scaleEffect(1.3)
        }
    }
}
