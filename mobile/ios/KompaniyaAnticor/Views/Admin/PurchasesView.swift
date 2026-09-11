import SwiftUI

struct PurchasesView: View {
    @EnvironmentObject var session: SessionStore
    @StateObject private var fileLoader = RemoteFileLoader()

    @State private var items: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?
    @State private var showAdd = false

    private var currencyFormatter: NumberFormatter {
        let f = NumberFormatter()
        f.numberStyle = .decimal
        f.groupingSeparator = " "
        f.maximumFractionDigits = 0
        return f
    }

    var body: some View {
        let canEntry = Roles.purchaseEntry.contains(session.user?.rol ?? "")

        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if items.isEmpty {
                Text("Hozircha yozuvlar yo'q").foregroundColor(AppColors.textDim)
            } else {
                List(Array(items.enumerated()), id: \.offset) { _, p in
                    Button {
                        let id = p.int("id") ?? 0
                        Task {
                            await fileLoader.load(api: session.api, pathAndQuery: "purchase-download.php?id=\(id)", suggestedFileName: "shartnoma_\(id).pdf")
                        }
                    } label: {
                        HStack {
                            VStack(alignment: .leading, spacing: 3) {
                                Text(p.str("kontragent") ?? "").font(.subheadline.weight(.semibold))
                                Text("\(p.str("shartnomaRaqami") ?? "") · \(p.str("shartnomaSana") ?? "")")
                                    .font(.caption).foregroundColor(AppColors.textDim)
                                Text("\(currencyFormatter.string(from: NSNumber(value: p.double("shartnomaSummasi") ?? 0)) ?? "0") so'm")
                                    .font(.caption).foregroundColor(AppColors.textDim)
                            }
                            Spacer()
                            Image(systemName: "doc.richtext").foregroundColor(AppColors.coral)
                        }
                    }
                    .foregroundColor(AppColors.text)
                }
                .listStyle(.plain)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Xaridlar reyestri")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if canEntry {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button { showAdd = true } label: { Image(systemName: "plus") }
                }
            }
        }
        .task { await load() }
        .sheet(isPresented: $showAdd) {
            PurchaseAddView(onSaved: { Task { await load() } })
                .environmentObject(session)
        }
        .sheet(item: $fileLoader.previewFile) { file in
            QuickLookPreview(url: file.url)
        }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getPurchases")
            items = data.arr("purchases")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }
}
