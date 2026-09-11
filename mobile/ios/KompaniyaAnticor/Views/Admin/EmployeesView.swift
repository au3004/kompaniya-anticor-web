import SwiftUI

private struct EmployeeSheetItem: Identifiable {
    let id = UUID()
    let existing: [String: Any]?
}

struct EmployeesView: View {
    @EnvironmentObject var session: SessionStore

    @State private var users: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?
    @State private var query = ""
    @State private var sheetItem: EmployeeSheetItem?
    @State private var pendingToast = false

    var body: some View {
        let rol = session.user?.rol ?? ""
        let canManage = Roles.hrManage.contains(rol)
        let canEdit = Roles.hrEdit.contains(rol)

        let filtered = query.isEmpty ? users : users.filter {
            let name = "\($0.str("familiya") ?? "") \($0.str("ism") ?? "")".lowercased()
            return name.contains(query.lowercased()) || ($0.str("login") ?? "").lowercased().contains(query.lowercased())
        }

        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else {
                List(Array(filtered.enumerated()), id: \.offset) { _, u in
                    let locked = (u["locked"] as? Bool) == true
                    HStack {
                        Circle().fill((locked ? AppColors.coral : AppColors.azure).opacity(0.12)).frame(width: 36, height: 36)
                            .overlay(Image(systemName: locked ? "lock" : "person").font(.caption).foregroundColor(locked ? AppColors.coral : AppColors.azure))
                        VStack(alignment: .leading, spacing: 2) {
                            Text("\(u.str("familiya") ?? "") \(u.str("ism") ?? "")").font(.subheadline.weight(.semibold))
                            Text("\(u.str("lavozim") ?? "—") · \(Roles.label(u.str("rol")))").font(.caption).foregroundColor(AppColors.textDim)
                        }
                        Spacer()
                        Menu {
                            if canEdit { Button("Tahrirlash") { sheetItem = EmployeeSheetItem(existing: u) } }
                            if locked && canManage { Button("Blokdan chiqarish") { unlock(u) } }
                            if canManage { Button("O'chirish", role: .destructive) { delete(u) } }
                        } label: {
                            Image(systemName: "ellipsis.circle").foregroundColor(AppColors.textDim)
                        }
                    }
                    .contentShape(Rectangle())
                    .onTapGesture { if canEdit { sheetItem = EmployeeSheetItem(existing: u) } }
                }
                .listStyle(.plain)
                .searchable(text: $query, prompt: "Qidirish...")
                .refreshable { await load() }
            }
        }
        .navigationTitle("Xodimlar")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if canManage {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button { sheetItem = EmployeeSheetItem(existing: nil) } label: { Image(systemName: "plus") }
                }
            }
        }
        .task { await load() }
        .sheet(item: $sheetItem) { item in
            EmployeeEditView(existing: item.existing) { pending in
                if pending { pendingToast = true }
                Task { await load() }
            }
            .environmentObject(session)
        }
        .alert("Kelishuv (tasdiqlash) uchun yuborildi", isPresented: $pendingToast) {
            Button("OK", role: .cancel) {}
        }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getUsersList")
            users = data.arr("users")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }

    private func delete(_ u: [String: Any]) {
        Task {
            guard let id = u["id"] else { return }
            _ = try? await session.api.call("deleteEmployee", ["id": id])
            await load()
        }
    }

    private func unlock(_ u: [String: Any]) {
        Task {
            guard let login = u.str("login") else { return }
            _ = try? await session.api.call("unlockLogin", ["login": login])
            await load()
        }
    }
}
