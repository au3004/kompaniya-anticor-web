import SwiftUI

struct SessionsView: View {
    @EnvironmentObject var session: SessionStore
    @State private var sessions: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else {
                List {
                    if sessions.contains(where: { ($0["isCurrent"] as? Bool) != true }) {
                        Button("Boshqa qurilmalardan chiqish") {
                            revokeOthers()
                        }
                    }
                    ForEach(Array(sessions.enumerated()), id: \.offset) { _, s in
                        let isCurrent = (s["isCurrent"] as? Bool) == true
                        HStack {
                            Image(systemName: isCurrent ? "iphone" : "desktopcomputer")
                                .foregroundColor(isCurrent ? AppColors.teal : AppColors.textDim)
                            VStack(alignment: .leading, spacing: 2) {
                                Text(isCurrent ? "Joriy qurilma" : "Boshqa qurilma").font(.subheadline.weight(.semibold))
                                Text("Kirilgan: \(s.str("createdAt") ?? "")").font(.caption2).foregroundColor(AppColors.textDim)
                                Text("Tugaydi: \(s.str("expiresAt") ?? "")").font(.caption2).foregroundColor(AppColors.textDim)
                            }
                            Spacer()
                            if !isCurrent, let idHash = s.str("idHash") {
                                Button {
                                    revoke(idHash: idHash)
                                } label: {
                                    Image(systemName: "xmark.circle.fill").foregroundColor(AppColors.coral)
                                }
                            }
                        }
                    }
                }
                .listStyle(.plain)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Faol sessiyalar")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getMySessions")
            sessions = data.arr("sessions")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }

    private func revoke(idHash: String) {
        Task {
            _ = try? await session.api.call("revokeSession", ["idHash": idHash])
            await load()
        }
    }

    private func revokeOthers() {
        Task {
            _ = try? await session.api.call("revokeOtherSessions")
            await load()
        }
    }
}
