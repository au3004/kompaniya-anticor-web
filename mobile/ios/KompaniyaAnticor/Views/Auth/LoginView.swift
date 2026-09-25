import SwiftUI

struct LoginView: View {
    @EnvironmentObject var session: SessionStore

    @State private var loginText = ""
    @State private var parol = ""
    @State private var showPassword = false
    @State private var rememberMe = false
    @State private var submitting = false
    @State private var error: String?
    @State private var showForgotPassword = false

    var body: some View {
        ScrollView {
            VStack(spacing: 18) {
                Spacer().frame(height: 24)

                RoundedRectangle(cornerRadius: 20)
                    .fill(AppColors.azure)
                    .frame(width: 72, height: 72)
                    .overlay(Image(systemName: "shield").font(.system(size: 30)).foregroundColor(.white))

                VStack(spacing: 4) {
                    Text("Korrupsiyaga qarshi kurashish")
                        .font(.title2.weight(.heavy))
                        .foregroundColor(AppColors.text)
                        .multilineTextAlignment(.center)
                    Text("Tizimga kirish")
                        .font(.subheadline)
                        .foregroundColor(AppColors.textDim)
                }

                VStack(spacing: 14) {
                    TextField("Login", text: $loginText)
                        .textFieldStyle(.roundedBorder)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()

                    HStack {
                        Group {
                            if showPassword {
                                TextField("Parol", text: $parol)
                            } else {
                                SecureField("Parol", text: $parol)
                            }
                        }
                        Button {
                            showPassword.toggle()
                        } label: {
                            Image(systemName: showPassword ? "eye.slash" : "eye")
                                .foregroundColor(AppColors.textDim)
                        }
                    }
                    .textFieldStyle(.roundedBorder)

                    HStack {
                        Button {
                            rememberMe.toggle()
                        } label: {
                            HStack(spacing: 6) {
                                Image(systemName: rememberMe ? "checkmark.square.fill" : "square")
                                    .foregroundColor(rememberMe ? AppColors.azure : AppColors.textDim)
                                Text("Meni eslab qol").font(.footnote).foregroundColor(AppColors.textDim)
                            }
                        }
                        .buttonStyle(.plain)
                        Spacer()
                        Button("Parolni unutdingizmi?") { showForgotPassword = true }
                            .font(.footnote)
                    }

                    if let error {
                        Text(error)
                            .font(.footnote)
                            .foregroundColor(AppColors.coral)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .padding(10)
                            .background(AppColors.coral.opacity(0.1))
                            .cornerRadius(10)
                    }

                    Button {
                        submit()
                    } label: {
                        if submitting {
                            ProgressView().tint(.white)
                        } else {
                            Text("Kirish").frame(maxWidth: .infinity)
                        }
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(AppColors.azure)
                    .controlSize(.large)
                    .disabled(submitting || loginText.isEmpty || parol.isEmpty)
                }
                .padding(.top, 12)
            }
            .padding(.horizontal, 28)
            .frame(maxWidth: 440)
        }
        .background(AppColors.bg.ignoresSafeArea())
        .sheet(isPresented: $showForgotPassword) {
            ForgotPasswordView()
        }
    }

    private func submit() {
        error = nil
        submitting = true
        Task {
            do {
                try await session.login(login: loginText, parol: parol, rememberMe: rememberMe)
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Kutilmagan xatolik yuz berdi"
            }
            submitting = false
        }
    }
}
