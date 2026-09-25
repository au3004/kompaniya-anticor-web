import SwiftUI

struct DocsView: View {
    @EnvironmentObject var session: SessionStore
    @StateObject private var fileLoader = RemoteFileLoader()

    @State private var docs: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if docs.isEmpty {
                Text("Hozircha hujjatlar yo'q").foregroundColor(AppColors.textDim)
            } else {
                List(Array(docs.enumerated()), id: \.offset) { _, d in
                    Button {
                        open(d)
                    } label: {
                        HStack {
                            Image(systemName: "doc.richtext").foregroundColor(AppColors.coral)
                            Text(d.str("uz") ?? "").foregroundColor(AppColors.text)
                            Spacer()
                            if fileLoader.isLoading {
                                ProgressView()
                            } else {
                                Image(systemName: "chevron.right").font(.caption).foregroundColor(AppColors.textDim)
                            }
                        }
                    }
                }
                .listStyle(.plain)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Hujjatlar")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .sheet(item: $fileLoader.previewFile) { file in
            QuickLookPreview(url: file.url)
        }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getDocuments")
            docs = data.arr("docs")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }

    private func open(_ doc: [String: Any]) {
        Task {
            _ = try? await session.api.call("markDocRead")
            let id = doc.int("id") ?? 0
            await fileLoader.load(api: session.api, pathAndQuery: "document-download.php?id=\(id)", suggestedFileName: "hujjat_\(id).pdf")
        }
    }
}
