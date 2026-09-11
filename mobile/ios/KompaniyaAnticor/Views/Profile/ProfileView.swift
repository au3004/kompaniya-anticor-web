import SwiftUI
import PhotosUI

struct ProfileView: View {
    @EnvironmentObject var session: SessionStore
    @State private var photoItem: PhotosPickerItem?
    @State private var uploadingPhoto = false
    @State private var uploadError: String?
    @State private var showLogoutConfirm = false

    var body: some View {
        let user = session.user

        ScrollView {
            VStack(spacing: 20) {
                ZStack(alignment: .bottomTrailing) {
                    AsyncImage(url: URL(string: user?.rasm ?? "")) { phase in
                        if let image = phase.image {
                            image.resizable().scaledToFill()
                        } else {
                            Circle().fill(AppColors.bgDeep).overlay(
                                Text(String((user?.ism.first).map(String.init) ?? "?"))
                                    .font(.title.weight(.bold))
                                    .foregroundColor(AppColors.azure)
                            )
                        }
                    }
                    .frame(width: 96, height: 96)
                    .clipShape(Circle())

                    PhotosPicker(selection: $photoItem, matching: .images) {
                        ZStack {
                            Circle().fill(AppColors.azure).frame(width: 32, height: 32)
                            if uploadingPhoto {
                                ProgressView().tint(.white).scaleEffect(0.6)
                            } else {
                                Image(systemName: "camera.fill").font(.caption).foregroundColor(.white)
                            }
                        }
                    }
                }
                .onChange(of: photoItem) { newItem in
                    uploadPhoto(item: newItem)
                }

                VStack(spacing: 2) {
                    Text(user?.fullName ?? "").font(.title3.weight(.bold))
                    if let lavozim = user?.lavozim, !lavozim.isEmpty {
                        Text(lavozim).font(.footnote).foregroundColor(AppColors.textDim)
                    }
                }

                if let uploadError {
                    Text(uploadError).font(.footnote).foregroundColor(AppColors.coral)
                }

                VStack(spacing: 0) {
                    infoRow("Bo'linma", user?.bolinma)
                    Divider()
                    infoRow("Telefon", user?.telefon)
                    Divider()
                    infoRow("Tug'ilgan sana", user?.tugilganSana)
                }
                .background(Color.white)
                .cornerRadius(14)

                VStack(spacing: 0) {
                    NavigationLink(destination: SessionsView()) {
                        settingsRow(icon: "iphone.and.arrow.forward", title: "Faol sessiyalar")
                    }
                    Divider()
                    NavigationLink(destination: TotpSetupView()) {
                        settingsRow(icon: "shield", title: "Ikki bosqichli tasdiqlash")
                    }
                    Divider()
                    NavigationLink(destination: ChangePasswordView()) {
                        settingsRow(icon: "key", title: "Parolni almashtirish")
                    }
                }
                .background(Color.white)
                .cornerRadius(14)

                Button(role: .destructive) {
                    showLogoutConfirm = true
                } label: {
                    Label("Chiqish", systemImage: "rectangle.portrait.and.arrow.right")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
                .tint(AppColors.coral)
            }
            .padding(20)
        }
        .background(AppColors.bg.ignoresSafeArea())
        .navigationTitle("Mening ma'lumotlarim")
        .navigationBarTitleDisplayMode(.inline)
        .confirmationDialog("Tizimdan chiqishni tasdiqlaysizmi?", isPresented: $showLogoutConfirm, titleVisibility: .visible) {
            Button("Chiqish", role: .destructive) {
                Task { await session.logout() }
            }
            Button("Bekor qilish", role: .cancel) {}
        }
    }

    private func infoRow(_ label: String, _ value: String?) -> some View {
        HStack {
            Text(label).font(.footnote).foregroundColor(AppColors.textDim)
            Spacer()
            Text((value?.isEmpty ?? true) ? "—" : value!).font(.footnote.weight(.semibold))
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
    }

    private func settingsRow(icon: String, title: String) -> some View {
        HStack {
            Image(systemName: icon).foregroundColor(AppColors.azure).frame(width: 24)
            Text(title).foregroundColor(AppColors.text)
            Spacer()
            Image(systemName: "chevron.right").font(.caption).foregroundColor(AppColors.textDim)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 14)
    }

    private func uploadPhoto(item: PhotosPickerItem?) {
        guard let item else { return }
        uploadingPhoto = true
        uploadError = nil
        Task {
            do {
                guard let data = try await item.loadTransferable(type: Data.self) else {
                    uploadingPhoto = false
                    return
                }
                let dataURL = APIClient.toDataURL(data, mimeType: "image/jpeg")
                _ = try await session.api.call("updateProfilePhoto", ["rasm": dataURL])
                await session.refreshProfile()
            } catch let apiError as APIError {
                uploadError = apiError.message
            } catch {
                uploadError = "Rasmni yuklab bo'lmadi"
            }
            uploadingPhoto = false
        }
    }
}
