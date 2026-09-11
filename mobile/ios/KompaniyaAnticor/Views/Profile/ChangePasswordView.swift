import SwiftUI

struct ChangePasswordView: View {
    @EnvironmentObject var session: SessionStore
    @Environment(\.dismiss) private var dismiss

    @State private var oldPass = ""
    @State private var newPass = ""
    @State private var busy = false
    @State private var error: String?
    @State private var done = false

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 14) {
                if done {
                    VStack(spacing: 10) {
                        Image(systemName: "checkmark.circle").font(.system(size: 44)).foregroundColor(AppColors.teal)
                        Text("Parol muvaffaqiyatli o'zgartirildi").font(.headline)
                        Button("Yopish") { dismiss() }
                            .buttonStyle(.borderedProminent).tint(AppColors.azure)
                    }
                    .frame(maxWidth: .infinity)
                } else {
                    SecureField("Eski parol", text: $oldPass).textFieldStyle(.roundedBorder)
                    SecureField("Yangi parol", text: $newPass).textFieldStyle(.roundedBorder)
                    Text("Kamida 8 belgi, katta/kichik harf, raqam va maxsus belgi")
                        .font(.caption2).foregroundColor(AppColors.textDim)
                    if let error {
                        Text(error).font(.footnote).foregroundColor(AppColors.coral)
                    }
                    Button {
                        submit()
                    } label: {
                        if busy {
                            ProgressView().tint(.white).frame(maxWidth: .infinity)
                        } else {
                            Text("Saqlash").frame(maxWidth: .infinity)
                        }
                    }
                    .buttonStyle(.borderedProminent).tint(AppColors.azure)
                    .disabled(busy || oldPass.isEmpty || !isStrong(newPass))
                }
            }
            .padding(20)
        }
        .navigationTitle("Parolni almashtirish")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func isStrong(_ v: String) -> Bool {
        v.count >= 8
            && v.range(of: "[A-Z]", options: .regularExpression) != nil
            && v.range(of: "[a-z]", options: .regularExpression) != nil
            && v.range(of: "[0-9]", options: .regularExpression) != nil
            && v.range(of: "[^A-Za-z0-9]", options: .regularExpression) != nil
    }

    private func submit() {
        busy = true
        error = nil
        Task {
            do {
                _ = try await session.api.call("changePassword", ["eskiParol": oldPass, "yangiParol": newPass])
                done = true
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Xatolik"
            }
            busy = false
        }
    }
}
