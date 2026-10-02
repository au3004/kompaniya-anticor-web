import SwiftUI

struct BackupsView: View {
    @EnvironmentObject var session: SessionStore
    @StateObject private var fileLoader = RemoteFileLoader()

    @State private var items: [[String: Any]] = []
    @State private var loading = true
    @State private var creating = false
    @State private var error: String?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else {
                List {
                    Section {
                        Button {
                            create()
                        } label: {
                            if creating {
                                ProgressView()
                            } else {
                                Label("Yangi zaxira nusxa yaratish", systemImage: "plus")
                            }
                        }
                        .disabled(creating)
                    }
                    if items.isEmpty {
                        Text("Zaxira nusxalar yo'q").foregroundColor(AppColors.textDim)
                    }
                    ForEach(Array(items.enumerated()), id: \.offset) { _, b in
                        Button {
                            let name = b.str("name") ?? ""
                            Task {
                                await fileLoader.load(api: session.api, pathAndQuery: "backup-download.php?name=\(name)", suggestedFileName: "backup_\(name).zip")
                            }
                        } label: {
                            HStack {
                                Image(systemName: "archivebox").foregroundColor(AppColors.azure)
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(b.str("name") ?? "").font(.subheadline)
                                    Text("\(b.str("createdAt") ?? "") · \(formatSize(b["sizeBytes"]))")
                                        .font(.caption).foregroundColor(AppColors.textDim)
                                }
                                Spacer()
                                Image(systemName: "arrow.down.circle").foregroundColor(AppColors.textDim)
                            }
                        }
                        .foregroundColor(AppColors.text)
                    }
                }
                .listStyle(.insetGrouped)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Zaxira nusxalar")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .sheet(item: $fileLoader.previewFile) { file in
            QuickLookPreview(url: file.url)
        }
    }

    private func formatSize(_ bytes: Any?) -> String {
        let b = (bytes as? NSNumber)?.doubleValue ?? (bytes as? Double) ?? Double((bytes as? Int) ?? 0)
        if b > 1024 * 1024 { return String(format: "%.1f MB", b / (1024 * 1024)) }
        if b > 1024 { return String(format: "%.1f KB", b / 1024) }
        return "\(Int(b)) B"
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("listBackups")
            items = data.arr("backups")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }

    private func create() {
        creating = true
        Task {
            _ = try? await session.api.call("createBackup")
            await load()
            creating = false
        }
    }
}
