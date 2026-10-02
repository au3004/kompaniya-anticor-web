import SwiftUI

struct ErrorLogView: View {
    @EnvironmentObject var session: SessionStore

    @State private var entries: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if entries.isEmpty {
                Text("Xatoliklar yo'q").foregroundColor(AppColors.textDim)
            } else {
                List(Array(entries.enumerated()), id: \.offset) { _, e in
                    VStack(alignment: .leading, spacing: 6) {
                        HStack {
                            Text(e.str("action") ?? "—").font(.caption.weight(.bold)).foregroundColor(AppColors.coral)
                            Spacer()
                            Text(e.str("sana") ?? "").font(.caption2).foregroundColor(AppColors.textDim)
                        }
                        Text(e.str("message") ?? "").font(.caption.monospaced())
                    }
                    .padding(.vertical, 4)
                }
                .listStyle(.plain)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Tizim jurnali")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getErrorLog")
            entries = data.arr("entries")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }
}
