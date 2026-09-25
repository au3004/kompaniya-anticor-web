import SwiftUI
import UniformTypeIdentifiers

struct PurchaseAddView: View {
    @EnvironmentObject var session: SessionStore
    @Environment(\.dismiss) private var dismiss
    let onSaved: () -> Void

    @State private var sana = Date()
    @State private var raqami = ""
    @State private var kontragent = ""
    @State private var predmeti = ""
    @State private var summasi = ""
    @State private var turi = ""
    @State private var izoh = ""
    @State private var showPicker = false
    @State private var pickedURL: URL?
    @State private var busy = false
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    DatePicker("Shartnoma sanasi *", selection: $sana, displayedComponents: .date)
                    TextField("Shartnoma raqami *", text: $raqami)
                    TextField("Kontragent *", text: $kontragent)
                    TextField("Shartnoma predmeti *", text: $predmeti, axis: .vertical)
                    TextField("Shartnoma summasi *", text: $summasi).keyboardType(.decimalPad)
                    TextField("Xarid turi *", text: $turi)
                    TextField("Izoh", text: $izoh, axis: .vertical)
                }
                Section {
                    Button {
                        showPicker = true
                    } label: {
                        HStack {
                            Image(systemName: "doc.richtext").foregroundColor(pickedURL == nil ? AppColors.textDim : AppColors.coral)
                            Text(pickedURL?.lastPathComponent ?? "Shartnoma fayli (PDF) tanlash *")
                        }
                    }
                    .foregroundColor(AppColors.text)
                }
                if let error {
                    Text(error).foregroundColor(AppColors.coral)
                }
            }
            .navigationTitle("Reyestrga kiritish")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Bekor qilish") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    if busy { ProgressView() } else { Button("Saqlash", action: submit) }
                }
            }
            .fileImporter(isPresented: $showPicker, allowedContentTypes: [.pdf]) { result in
                if case .success(let url) = result { pickedURL = url }
            }
        }
    }

    private func submit() {
        guard !raqami.isEmpty, !kontragent.isEmpty, !predmeti.isEmpty, !summasi.isEmpty, !turi.isEmpty else {
            error = "Barcha majburiy maydonlarni to'ldiring"
            return
        }
        guard let url = pickedURL else {
            error = "Shartnoma fayli (PDF) yuklanishi shart"
            return
        }
        busy = true
        error = nil
        Task {
            do {
                guard url.startAccessingSecurityScopedResource() else {
                    error = "Faylga kirish imkonsiz"
                    busy = false
                    return
                }
                defer { url.stopAccessingSecurityScopedResource() }
                let data = try Data(contentsOf: url)
                let dataURL = APIClient.toDataURL(data, mimeType: "application/pdf")

                let df = DateFormatter()
                df.dateFormat = "yyyy-MM-dd"

                _ = try await session.api.call("addPurchase", [
                    "shartnomaSana": df.string(from: sana),
                    "shartnomaRaqami": raqami,
                    "kontragent": kontragent,
                    "shartnomaPredmeti": predmeti,
                    "shartnomaSummasi": summasi,
                    "xaridTuri": turi,
                    "izoh": izoh,
                    "file": dataURL,
                    "fileName": url.lastPathComponent,
                ])
                onSaved()
                dismiss()
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Faylni o'qib bo'lmadi"
            }
            busy = false
        }
    }
}
