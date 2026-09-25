import SwiftUI

/// [String: Any] o'zi Hashable bo'la olmagani uchun (Any qiymatlar tufayli),
/// navigationDestination(item:) talab qiladigan Hashable'ni faqat id (UUID)
/// asosida qo'lda ta'minlaymiz — data maydoni tenglik/hash'ga kirmaydi.
private struct DeclarationDetailItem: Identifiable, Hashable {
    let id = UUID()
    let data: [String: Any]

    static func == (lhs: DeclarationDetailItem, rhs: DeclarationDetailItem) -> Bool { lhs.id == rhs.id }
    func hash(into hasher: inout Hasher) { hasher.combine(id) }
}

struct DeclarationsAdminView: View {
    @EnvironmentObject var session: SessionStore

    @State private var items: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?
    @State private var detailItem: DeclarationDetailItem?

    var body: some View {
        let canDelete = Roles.anticorManage.contains(session.user?.rol ?? "")

        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if items.isEmpty {
                Text("Hozircha deklaratsiyalar yo'q").foregroundColor(AppColors.textDim)
            } else {
                List(Array(items.enumerated()), id: \.offset) { _, d in
                    let hasConflict = (d["hasConflict"] as? Bool) == true
                    HStack {
                        Circle().fill((hasConflict ? AppColors.coral : AppColors.teal).opacity(0.15)).frame(width: 34, height: 34)
                            .overlay(Image(systemName: hasConflict ? "exclamationmark.triangle" : "checkmark").font(.caption).foregroundColor(hasConflict ? AppColors.coral : AppColors.teal))
                        VStack(alignment: .leading, spacing: 2) {
                            Text(d.str("fullName") ?? "").font(.subheadline.weight(.semibold))
                            Text("\(d.str("lavozim") ?? "—") · \(d.str("submittedAt") ?? "")").font(.caption).foregroundColor(AppColors.textDim)
                        }
                        Spacer()
                        if canDelete {
                            Button {
                                delete(d)
                            } label: {
                                Image(systemName: "trash").foregroundColor(AppColors.coral)
                            }
                        }
                    }
                    .contentShape(Rectangle())
                    .onTapGesture { open(d) }
                }
                .listStyle(.plain)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Deklaratsiyalar")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .navigationDestination(item: $detailItem) { item in
            DeclarationDetailView(declaration: item.data)
        }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getDeclarations")
            items = data.arr("declarations")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }

    private func open(_ d: [String: Any]) {
        Task {
            guard let id = d["id"] else { return }
            if let data = try? await session.api.call("getDeclaration", ["id": id]),
               let decl = data["declaration"] as? [String: Any] {
                detailItem = DeclarationDetailItem(data: decl)
            }
        }
    }

    private func delete(_ d: [String: Any]) {
        Task {
            guard let id = d["id"] else { return }
            _ = try? await session.api.call("deleteDeclaration", ["id": id])
            await load()
        }
    }
}
