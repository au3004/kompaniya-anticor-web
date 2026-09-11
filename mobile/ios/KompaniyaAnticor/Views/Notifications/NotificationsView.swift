import SwiftUI

struct NotificationsView: View {
    @EnvironmentObject var session: SessionStore

    @State private var items: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if items.isEmpty {
                Text("Xabarnomalar yo'q").foregroundColor(AppColors.textDim)
            } else {
                List(Array(items.enumerated()), id: \.offset) { _, n in
                    let unread = (n["read"] as? Bool) != true
                    HStack(alignment: .top) {
                        Image(systemName: "bell")
                            .foregroundColor(unread ? AppColors.azure : AppColors.textDim)
                        Text(n.str("text") ?? "")
                            .font(unread ? .subheadline.weight(.semibold) : .subheadline)
                    }
                    .listRowBackground(unread ? AppColors.azure.opacity(0.05) : Color.white)
                }
                .listStyle(.plain)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Xabarnomalar")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getMyNotifications")
            items = data.arr("notifications")
            for n in items where (n["read"] as? Bool) != true {
                if let id = n["id"] {
                    _ = try? await session.api.call("markNotificationRead", ["notifId": id])
                }
            }
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }
}
