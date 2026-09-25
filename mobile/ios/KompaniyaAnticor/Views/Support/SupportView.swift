import SwiftUI

struct SupportView: View {
    @EnvironmentObject var session: SessionStore

    @State private var text = ""
    @State private var submitting = false
    @State private var error: String?
    @State private var done = false

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            if done {
                VStack(spacing: 10) {
                    Image(systemName: "checkmark.circle").font(.system(size: 48)).foregroundColor(AppColors.teal)
                    Text("Murojaatingiz yuborildi").font(.headline)
                    Button("Yana murojaat yuborish") {
                        done = false
                        text = ""
                    }
                    .buttonStyle(.borderedProminent).tint(AppColors.azure)
                }
                .frame(maxWidth: .infinity)
            } else {
                Text("Savol yoki murojaatingizni quyida yozing — boshqaruv bo'limi xodimi tez orada javob beradi.")
                    .font(.footnote).foregroundColor(AppColors.textDim)
                TextEditor(text: $text)
                    .frame(height: 180)
                    .padding(8)
                    .background(Color.white)
                    .cornerRadius(10)
                    .overlay(RoundedRectangle(cornerRadius: 10).stroke(AppColors.cardBorder))
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
                .disabled(submitting || text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
            }
        }
        .padding(20)
        .navigationTitle("Yordam")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func submit() {
        error = nil
        submitting = true
        Task {
            do {
                _ = try await session.api.call("submitSupport", ["murojaat": text.trimmingCharacters(in: .whitespacesAndNewlines)])
                done = true
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Xatolik"
            }
            submitting = false
        }
    }
}
