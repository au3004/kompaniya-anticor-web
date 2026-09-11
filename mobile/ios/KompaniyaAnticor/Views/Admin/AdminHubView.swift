import SwiftUI

struct AdminHubView: View {
    @EnvironmentObject var session: SessionStore

    private let columns = [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]

    var body: some View {
        let rol = session.user?.rol ?? ""

        ScrollView {
            LazyVGrid(columns: columns, spacing: 12) {
                if Roles.hrView.contains(rol) {
                    NavigationLink(destination: EmployeesView()) {
                        HubCardView(data: .init(icon: "person.2", title: "Xodimlar", subtitle: "Xodimlar ro'yxati va boshqaruvi", color: AppColors.azure))
                    }
                }
                if Roles.requestApprove.contains(rol) || rol == Roles.hrAdmin {
                    NavigationLink(destination: PendingApprovalsView()) {
                        HubCardView(data: .init(icon: "checkmark.seal", title: "Tasdiqlash so'rovlari", subtitle: "Yangi rol tayinlash so'rovlari", color: AppColors.teal))
                    }
                }
                if Roles.anticorView.contains(rol) {
                    NavigationLink(destination: StatsView()) {
                        HubCardView(data: .init(icon: "chart.bar", title: "Statistika", subtitle: "Test/hujjat bo'yicha umumiy ko'rsatkichlar", color: AppColors.coral))
                    }
                }
                if Roles.purchaseView.contains(rol) {
                    NavigationLink(destination: PurchasesView()) {
                        HubCardView(data: .init(icon: "doc.plaintext", title: "Xaridlar reyestri", subtitle: "Shartnomalar ro'yxati", color: AppColors.azure))
                    }
                }
                if Roles.anticorView.contains(rol) {
                    NavigationLink(destination: DeclarationsAdminView()) {
                        HubCardView(data: .init(icon: "doc.text.magnifyingglass", title: "Deklaratsiyalar", subtitle: "Manfaatlar to'qnashuvi ro'yxati", color: AppColors.teal))
                    }
                }
                if Roles.anticorManage.contains(rol) {
                    NavigationLink(destination: ErrorLogView()) {
                        HubCardView(data: .init(icon: "exclamationmark.triangle", title: "Tizim jurnali", subtitle: "Server xatoliklari", color: AppColors.text))
                    }
                    NavigationLink(destination: BackupsView()) {
                        HubCardView(data: .init(icon: "externaldrive", title: "Zaxira nusxa", subtitle: "Baza va fayllar zaxirasi", color: AppColors.coral))
                    }
                }
            }
            .padding(16)
        }
        .background(AppColors.bg.ignoresSafeArea())
        .navigationTitle("Boshqaruv paneli")
        .navigationBarTitleDisplayMode(.inline)
    }
}
