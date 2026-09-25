import SwiftUI
import UniformTypeIdentifiers

/// Xodim o'zining shaxsiy hujjatini (ariza, ma'lumotnoma va h.k.) Inson
/// resurslari bo'limiga yuboradi. Faqat PDF, maksimal 25MB (backend:
/// HrDocumentController::MAX_BYTES bilan bir xil chegara).
struct HrDocumentsView: View {
    @EnvironmentObject var session: SessionStore
    private static let maxBytes = 25 * 1024 * 1024

    @State private var showPicker = false
    @State private var pickedURL: URL?
    @State private var submitting = false
    @State private var error: String?
    @State private var done = false

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            if done {
                VStack(spacing: 10) {
                    Image(systemName: "checkmark.circle").font(.system(size: 48)).foregroundColor(AppColors.teal)
                    Text("Hujjat yuborildi").font(.headline)
                    Button("Yana hujjat yuborish") {
                        done = false
                        pickedURL = nil
                    }
                    .buttonStyle(.borderedProminent).tint(AppColors.azure)
                }
                .frame(maxWidth: .infinity)
            } else {
                Text("Shaxsiy hujjatingizni (ariza, ma'lumotnoma va h.k.) PDF formatida yuboring.")
                    .font(.footnote).foregroundColor(AppColors.textDim)

                Button {
                    showPicker = true
                } label: {
                    VStack(spacing: 8) {
                        Image(systemName: pickedURL == nil ? "arrow.up.doc" : "doc.richtext")
                            .font(.system(size: 32))
                            .foregroundColor(pickedURL == nil ? AppColors.textDim : AppColors.coral)
                        Text(pickedURL?.lastPathComponent ?? "PDF fayl tanlash uchun bosing")
                            .font(.footnote)
                            .multilineTextAlignment(.center)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(20)
                    .overlay(RoundedRectangle(cornerRadius: 14).stroke(AppColors.cardBorder))
                }
                .foregroundColor(AppColors.text)

                if let error {
                    Text(error).font(.footnote).foregroundColor(AppColors.coral)
                }

                Button {
                    submit()
                } label: {
                    if submitting {
                        ProgressView().tint(.white).frame(maxWidth: .infinity)
                    } else {
                        Text("Yuborish").frame(maxWidth: .infinity)
                    }
                }
                .buttonStyle(.borderedProminent).tint(AppColors.azure)
                .disabled(submitting || pickedURL == nil)
            }
        }
        .padding(20)
        .navigationTitle("HR hujjatlari")
        .navigationBarTitleDisplayMode(.inline)
        .fileImporter(isPresented: $showPicker, allowedContentTypes: [.pdf]) { result in
            if case .success(let url) = result {
                pickedURL = url
                error = nil
            }
        }
    }

    private func submit() {
        guard let url = pickedURL else { return }
        submitting = true
        error = nil
        Task {
            do {
                guard url.startAccessingSecurityScopedResource() else {
                    error = "Faylga kirish imkonsiz"
                    submitting = false
                    return
                }
                defer { url.stopAccessingSecurityScopedResource() }
                let data = try Data(contentsOf: url)
                if data.count > Self.maxBytes {
                    error = "Fayl hajmi juda katta (maksimal 25MB)"
                    submitting = false
                    return
                }
                let dataURL = APIClient.toDataURL(data, mimeType: "application/pdf")
                _ = try await session.api.call("submitHrDocument", ["file": dataURL, "fileName": url.lastPathComponent])
                done = true
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Faylni o'qib bo'lmadi"
            }
            submitting = false
        }
    }
}
