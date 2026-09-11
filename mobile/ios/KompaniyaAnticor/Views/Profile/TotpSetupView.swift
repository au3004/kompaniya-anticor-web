import SwiftUI

private enum TotpStep {
    case loading, disabled, settingUp, enabled
}

struct TotpSetupView: View {
    @EnvironmentObject var session: SessionStore

    @State private var step: TotpStep = .loading
    @State private var secret: String?
    @State private var account: String?
    @State private var code = ""
    @State private var parol = ""
    @State private var busy = false
    @State private var error: String?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                switch step {
                case .loading:
                    ProgressView().frame(maxWidth: .infinity)
                case .disabled:
                    disabledSection
                case .settingUp:
                    settingUpSection
                case .enabled:
                    enabledSection
                }
            }
            .padding(20)
        }
        .navigationTitle("Ikki bosqichli tasdiqlash")
        .navigationBarTitleDisplayMode(.inline)
        .task { await loadStatus() }
    }

    private var disabledSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            Image(systemName: "shield").font(.system(size: 40)).foregroundColor(AppColors.textDim).frame(maxWidth: .infinity)
            Text("2FA hozircha o'chirilgan").font(.headline)
            Text("Yoqilsa, har kirishda parol ustiga autentifikator ilovasidagi 6 xonali kod ham so'raladi.")
                .font(.footnote).foregroundColor(AppColors.textDim)
            if let error {
                Text(error).font(.footnote).foregroundColor(AppColors.coral)
            }
            Button("Yoqish") { startSetup() }
                .buttonStyle(.borderedProminent).tint(AppColors.azure)
                .frame(maxWidth: .infinity)
                .disabled(busy)
        }
    }

    private var settingUpSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("1-qadam").font(.headline)
            Text("Quyidagi kalitni autentifikator ilovangizga (Google Authenticator, Authy va h.k.) qo'lda kiriting (\"Kalitni qo'lda kiritish\" variantidan foydalaning):")
                .font(.footnote).foregroundColor(AppColors.textDim)
            Button {
                UIPasteboard.general.string = secret
            } label: {
                HStack {
                    Text(secret ?? "").font(.system(.body, design: .monospaced))
                    Spacer()
                    Image(systemName: "doc.on.doc")
                }
                .padding(14)
                .background(AppColors.bgDeep)
                .cornerRadius(10)
            }
            .foregroundColor(AppColors.text)
            Text("Hisob: \(account ?? "")").font(.caption2).foregroundColor(AppColors.textDim)

            Text("2-qadam").font(.headline).padding(.top, 8)
            Text("Ilova ko'rsatgan 6 xonali kodni kiriting:").font(.footnote).foregroundColor(AppColors.textDim)
            TextField("••••••", text: $code)
                .keyboardType(.numberPad)
                .multilineTextAlignment(.center)
                .font(.system(size: 22, weight: .bold, design: .monospaced))
                .textFieldStyle(.roundedBorder)
                .onChange(of: code) { newValue in
                    code = String(newValue.filter(\.isNumber).prefix(6))
                }

            if let error {
                Text(error).font(.footnote).foregroundColor(AppColors.coral)
            }
            Button("Tasdiqlash va yoqish") { confirmSetup() }
                .buttonStyle(.borderedProminent).tint(AppColors.azure)
                .frame(maxWidth: .infinity)
                .disabled(busy || code.count != 6)
        }
    }

    private var enabledSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            Image(systemName: "checkmark.shield").font(.system(size: 40)).foregroundColor(AppColors.teal).frame(maxWidth: .infinity)
            Text("2FA yoqilgan").font(.headline).frame(maxWidth: .infinity, alignment: .center)
            Text("O'chirish uchun parolingizni tasdiqlang:").font(.footnote).foregroundColor(AppColors.textDim)
            SecureField("Parol", text: $parol).textFieldStyle(.roundedBorder)
            if let error {
                Text(error).font(.footnote).foregroundColor(AppColors.coral)
            }
            Button("2FA'ni o'chirish") { disable() }
                .buttonStyle(.bordered).tint(AppColors.coral)
                .frame(maxWidth: .infinity)
                .disabled(busy)
        }
    }

    private func loadStatus() async {
        do {
            let data = try await session.api.call("totpStatus")
            step = (data["enabled"] as? Bool) == true ? .enabled : .disabled
        } catch {
            step = .disabled
        }
    }

    private func startSetup() {
        busy = true
        error = nil
        Task {
            do {
                let data = try await session.api.call("totpSetupStart")
                secret = data.str("secret")
                account = data.str("account")
                step = .settingUp
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Xatolik"
            }
            busy = false
        }
    }

    private func confirmSetup() {
        busy = true
        error = nil
        Task {
            do {
                _ = try await session.api.call("totpSetupConfirm", ["secret": secret ?? "", "code": code])
                step = .enabled
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Xatolik"
            }
            busy = false
        }
    }

    private func disable() {
        busy = true
        error = nil
        Task {
            do {
                _ = try await session.api.call("totpDisable", ["parol": parol])
                parol = ""
                step = .disabled
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Xatolik"
            }
            busy = false
        }
    }
}
