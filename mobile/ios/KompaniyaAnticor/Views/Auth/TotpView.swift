import SwiftUI

struct TotpView: View {
    @EnvironmentObject var session: SessionStore

    @State private var code = ""
    @State private var submitting = false
    @State private var error: String?

    var body: some View {
        NavigationStack {
            VStack(spacing: 20) {
                RoundedRectangle(cornerRadius: 18)
                    .fill(AppColors.teal)
                    .frame(width: 64, height: 64)
                    .overlay(Image(systemName: "lock.iphone").font(.system(size: 28)).foregroundColor(.white))

                VStack(spacing: 4) {
                    Text("Ikki bosqichli tasdiqlash").font(.title3.weight(.heavy)).foregroundColor(AppColors.text)
                    Text("Autentifikator ilovangizdagi 6 xonali kodni kiriting")
                        .font(.footnote)
                        .foregroundColor(AppColors.textDim)
                        .multilineTextAlignment(.center)
                }

                TextField("••••••", text: $code)
                    .keyboardType(.numberPad)
                    .multilineTextAlignment(.center)
                    .font(.system(size: 28, weight: .bold, design: .monospaced))
                    .textFieldStyle(.roundedBorder)
                    .onChange(of: code) { newValue in
                        code = String(newValue.filter(\.isNumber).prefix(6))
                    }

                if let error {
                    Text(error).font(.footnote).foregroundColor(AppColors.coral)
                }

                Button {
                    submit()
                } label: {
                    if submitting {
                        ProgressView().tint(.white)
                    } else {
                        Text("Tasdiqlash").frame(maxWidth: .infinity)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(AppColors.azure)
                .controlSize(.large)
                .disabled(submitting || code.count != 6)
            }
            .padding(.horizontal, 28)
            .frame(maxWidth: 440)
            .navigationTitle("")
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button {
                        session.cancelTotp()
                    } label: {
                        Image(systemName: "arrow.left")
                    }
                }
            }
        }
    }

    private func submit() {
        error = nil
        submitting = true
        Task {
            do {
                try await session.verifyTotp(code: code)
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Kutilmagan xatolik yuz berdi"
            }
            submitting = false
        }
    }
}
