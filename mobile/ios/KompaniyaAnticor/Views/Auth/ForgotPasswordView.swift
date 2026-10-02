import SwiftUI

/// "Parolni tiklash so'rovi" — haqiqiy avtomatik tiklash emas: login/telefon
/// raqamini Yordam navbatiga murojaat sifatida yozib qo'yadi (backend:
/// AuthController::requestPasswordReset). Boshqaruv paneli xodimi buni
/// ko'rib, parolni qo'lda tiklaydi. Bu ekran hali login qilinmagan holatda
/// ham ochiladigan bo'lgani uchun o'zining alohida APIClient nusxasidan
/// foydalanadi (sessiya talab qilinmaydi).
struct ForgotPasswordView: View {
    @Environment(\.dismiss) private var dismiss

    @State private var loginText = ""
    @State private var telefon = ""
    @State private var submitting = false
    @State private var error: String?
    @State private var sent = false

    private let api = APIClient()

    var body: some View {
        NavigationStack {
            VStack(spacing: 16) {
                if sent {
                    Image(systemName: "checkmark.circle").font(.system(size: 48)).foregroundColor(AppColors.teal)
                    Text("So'rovingiz yuborildi").font(.headline)
                    Text("Boshqaruv xodimi tez orada siz bilan bog'lanadi.")
                        .font(.footnote)
                        .foregroundColor(AppColors.textDim)
                        .multilineTextAlignment(.center)
                    Button("Yopish") { dismiss() }
                        .buttonStyle(.borderedProminent)
                        .tint(AppColors.azure)
                } else {
                    Text("Login va telefon raqamingizni kiriting — so'rovingiz boshqaruv xodimiga yuboriladi.")
                        .font(.footnote)
                        .foregroundColor(AppColors.textDim)
                    TextField("Login", text: $loginText)
                        .textFieldStyle(.roundedBorder)
                        .textInputAutocapitalization(.never)
                    TextField("Telefon raqami", text: $telefon)
                        .textFieldStyle(.roundedBorder)
                        .keyboardType(.phonePad)
                    if let error {
                        Text(error).font(.footnote).foregroundColor(AppColors.coral)
                    }
                    Button {
                        submit()
                    } label: {
                        if submitting {
                            ProgressView().tint(.white)
                        } else {
                            Text("Yuborish").frame(maxWidth: .infinity)
                        }
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(AppColors.azure)
                    .controlSize(.large)
                    .disabled(submitting || loginText.isEmpty || telefon.isEmpty)
                }
            }
            .padding(20)
            .navigationTitle("Parolni tiklash")
            .navigationBarTitleDisplayMode(.inline)
        }
        .presentationDetents([.medium])
    }

    private func submit() {
        error = nil
        submitting = true
        Task {
            do {
                try await api.call("requestPasswordReset", ["login": loginText, "telefon": telefon])
                sent = true
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Kutilmagan xatolik"
            }
            submitting = false
        }
    }
}
