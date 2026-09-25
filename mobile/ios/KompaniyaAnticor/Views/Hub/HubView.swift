import SwiftUI

struct HubCardData: Identifiable {
    let id = UUID()
    let icon: String
    let title: String
    let subtitle: String
    let color: Color
}

struct HubCardView: View {
    let data: HubCardData
    var badgeCount: Int = 0

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                ZStack {
                    RoundedRectangle(cornerRadius: 12).fill(data.color.opacity(0.12)).frame(width: 42, height: 42)
                    Image(systemName: data.icon).foregroundColor(data.color)
                }
                Spacer()
                if badgeCount > 0 {
                    Text(badgeCount > 99 ? "99+" : "\(badgeCount)")
                        .font(.caption2.weight(.bold))
                        .foregroundColor(.white)
                        .padding(.horizontal, 7)
                        .padding(.vertical, 3)
                        .background(AppColors.coral)
                        .clipShape(Capsule())
                }
            }
            Text(data.title).font(.subheadline.weight(.bold)).foregroundColor(AppColors.text)
            Text(data.subtitle).font(.caption).foregroundColor(AppColors.textDim).lineLimit(2)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .cornerRadius(16)
        .overlay(RoundedRectangle(cornerRadius: 16).stroke(AppColors.cardBorder, lineWidth: 1))
    }
}

struct HubView: View {
    @EnvironmentObject var session: SessionStore
    @State private var unreadCount = 0
    @State private var showDeclarationWizard = false

    private let columns = [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]

    var body: some View {
        NavigationStack {
            ScrollView {
                LazyVGrid(columns: columns, spacing: 12) {
                    NavigationLink(destination: DocsView()) {
                        HubCardView(data: .init(icon: "book", title: "Hujjatlar", subtitle: "Korrupsiyaga qarshi kurash bo'yicha hujjatlar", color: AppColors.azure))
                    }
                    NavigationLink(destination: TestView()) {
                        HubCardView(data: .init(icon: "checkmark.seal", title: "Test", subtitle: "Bilim darajasini tekshirish testi", color: AppColors.teal))
                    }
                    NavigationLink(destination: SurveyView()) {
                        HubCardView(data: .init(icon: "chart.bar.doc.horizontal", title: "So'rovnoma", subtitle: "Anonim so'rovnomada ishtirok eting", color: AppColors.coral))
                    }
                    // Deklaratsiya ko'p bosqichli wizard bo'lgani uchun (o'z
                    // ichida yana bitta NavigationStack'ga muhtoj) NavigationLink
                    // push emas, modal sheet sifatida ochiladi — shu bilan
                    // "Yopish" bitta amal bilan butun oqimni yopa oladi.
                    Button {
                        showDeclarationWizard = true
                    } label: {
                        HubCardView(data: .init(icon: "doc.text.magnifyingglass", title: "Deklaratsiya", subtitle: "Manfaatlar to'qnashuvi deklaratsiyasi", color: AppColors.azure))
                    }
                    NavigationLink(destination: SupportView()) {
                        HubCardView(data: .init(icon: "questionmark.bubble", title: "Yordam", subtitle: "Savol yoki murojaatingizni yuboring", color: AppColors.teal))
                    }
                    NavigationLink(destination: HrDocumentsView()) {
                        HubCardView(data: .init(icon: "doc.badge.plus", title: "HR hujjatlari", subtitle: "Shaxsiy hujjat yuboring", color: AppColors.coral))
                    }
                    if Roles.anyPanelAccess.contains(session.user?.rol ?? "") {
                        NavigationLink(destination: AdminHubView()) {
                            HubCardView(data: .init(icon: "gearshape.2", title: "Boshqaruv paneli", subtitle: "Xodimlar, hisobotlar va boshqalar", color: AppColors.text))
                        }
                    }
                }
                .padding(16)
            }
            .background(AppColors.bg.ignoresSafeArea())
            .navigationTitle("Assalomu alaykum, \(session.user?.ism ?? "")")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    NavigationLink(destination: NotificationsView()) {
                        ZStack(alignment: .topTrailing) {
                            Image(systemName: "bell")
                            if unreadCount > 0 {
                                Circle().fill(AppColors.coral).frame(width: 8, height: 8).offset(x: 4, y: -4)
                            }
                        }
                    }
                }
                ToolbarItem(placement: .navigationBarTrailing) {
                    NavigationLink(destination: ProfileView()) {
                        Circle().fill(AppColors.azure).frame(width: 26, height: 26)
                            .overlay(Image(systemName: "person.fill").font(.caption).foregroundColor(.white))
                    }
                }
            }
            .onAppear(perform: loadUnread)
            .sheet(isPresented: $showDeclarationWizard) {
                DeclarationWizardView()
                    .environmentObject(session)
            }
        }
    }

    private func loadUnread() {
        Task {
            if let data = try? await session.api.call("getMyNotifications") {
                let list = data.arr("notifications")
                unreadCount = list.filter { ($0["read"] as? Bool) != true }.count
            }
        }
    }
}
