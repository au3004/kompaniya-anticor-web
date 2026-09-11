import SwiftUI

struct PendingApprovalsView: View {
    @EnvironmentObject var session: SessionStore

    @State private var requests: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if requests.isEmpty {
                Text("Hozircha so'rovlar yo'q").foregroundColor(AppColors.textDim)
            } else {
                List(Array(requests.enumerated()), id: \.offset) { _, r in
                    let approvals = r.arr("approvals")
                    let isPending = r.str("status") == "pending"
                    VStack(alignment: .leading, spacing: 6) {
                        HStack {
                            Text((r.str("fish")?.isEmpty ?? true) ? "Noma'lum" : r.str("fish")!)
                                .font(.subheadline.weight(.bold))
                            Spacer()
                            Text(r.str("type") == "add" ? "Yangi" : "Tahrirlash").font(.caption2).foregroundColor(AppColors.textDim)
                        }
                        Text("Rol: \(r.str("rol") ?? "—") · Yuboruvchi: \(r.str("requestedByFish") ?? "—")")
                            .font(.caption).foregroundColor(AppColors.textDim)
                        Text("Sana: \(r.str("sana") ?? "—")").font(.caption2).foregroundColor(AppColors.textDim)

                        if !approvals.isEmpty {
                            HStack {
                                ForEach(Array(approvals.enumerated()), id: \.offset) { _, a in
                                    let approved = a.str("decision") == "approved"
                                    Text("\(a.str("fish") ?? ""): \(approved ? "tasdiqladi" : "rad etdi")")
                                        .font(.caption2.weight(.semibold))
                                        .padding(.horizontal, 8).padding(.vertical, 3)
                                        .background((approved ? AppColors.teal : AppColors.coral).opacity(0.12))
                                        .foregroundColor(approved ? AppColors.teal : AppColors.coral)
                                        .clipShape(Capsule())
                                }
                            }
                        }

                        if isPending {
                            HStack {
                                Button("Rad etish") { decide(r, "rejected") }
                                    .buttonStyle(.bordered).tint(AppColors.coral)
                                Button("Tasdiqlash") { decide(r, "approved") }
                                    .buttonStyle(.borderedProminent).tint(AppColors.azure)
                            }
                            .padding(.top, 4)
                        } else {
                            Text(r.str("status") == "approved" ? "Tasdiqlangan" : "Rad etilgan")
                                .font(.caption.weight(.bold))
                                .foregroundColor(r.str("status") == "approved" ? AppColors.teal : AppColors.coral)
                        }
                    }
                    .padding(.vertical, 4)
                }
                .listStyle(.plain)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Tasdiqlash so'rovlari")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getPendingEmployeeRequests")
            requests = data.arr("requests")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }

    private func decide(_ req: [String: Any], _ decision: String) {
        Task {
            guard let id = req["id"] else { return }
            _ = try? await session.api.call("decidePendingEmployeeRequest", ["requestId": id, "decision": decision])
            await load()
        }
    }
}
