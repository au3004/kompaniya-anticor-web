import SwiftUI
import QuickLook

/// Bearer-token bilan himoyalangan server faylini (masalan
/// `document-download.php?id=5`) yuklab, vaqtincha papkaga saqlaydi va
/// QuickLook orqali ko'rsatadi. Oddiy Link/URL bilan ochib bo'lmaydi,
/// chunki bu fayllarga faqat "Authorization: Bearer" sarlavhasi bilan
/// kirish mumkin.
struct PreviewFile: Identifiable {
    let id = UUID()
    let url: URL
}

@MainActor
final class RemoteFileLoader: ObservableObject {
    @Published var previewFile: PreviewFile?
    @Published var isLoading = false
    @Published var errorMessage: String?

    func load(api: APIClient, pathAndQuery: String, suggestedFileName: String) async {
        isLoading = true
        errorMessage = nil
        do {
            let data = try await api.downloadFile(pathAndQuery)
            let dir = FileManager.default.temporaryDirectory
            let fileURL = dir.appendingPathComponent(suggestedFileName)
            try data.write(to: fileURL, options: .atomic)
            previewFile = PreviewFile(url: fileURL)
        } catch {
            errorMessage = (error as? APIError)?.message ?? "Faylni ochib bo'lmadi"
        }
        isLoading = false
    }
}

struct QuickLookPreview: UIViewControllerRepresentable {
    let url: URL

    func makeUIViewController(context: Context) -> QLPreviewController {
        let controller = QLPreviewController()
        controller.dataSource = context.coordinator
        return controller
    }

    func updateUIViewController(_ uiViewController: QLPreviewController, context: Context) {}

    func makeCoordinator() -> Coordinator { Coordinator(url: url) }

    final class Coordinator: NSObject, QLPreviewControllerDataSource {
        let url: URL
        init(url: URL) { self.url = url }
        func numberOfPreviewItems(in controller: QLPreviewController) -> Int { 1 }
        func previewController(_ controller: QLPreviewController, previewItemAt index: Int) -> QLPreviewItem {
            url as NSURL
        }
    }
}
