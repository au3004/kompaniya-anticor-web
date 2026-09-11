import SwiftUI

/// Yangi xodim qo'shish (existing == nil) yoki mavjudini tahrirlash.
/// backend: AdminController::addEmployee / editEmployee — agar hr-admin
/// "user"dan boshqa rol bersa, so'rov kelishuv (pending approval) holatiga
/// tushishi mumkin, shu holat alohida xabar bilan ko'rsatiladi.
struct EmployeeEditView: View {
    @EnvironmentObject var session: SessionStore
    @Environment(\.dismiss) private var dismiss

    let existing: [String: Any]?
    let onSaved: (_ pending: Bool) -> Void

    @State private var loginText = ""
    @State private var parol = ""
    @State private var familiya = ""
    @State private var ism = ""
    @State private var otasi = ""
    @State private var lavozim = ""
    @State private var bolinma = ""
    @State private var telefon = ""
    @State private var rol = Roles.user
    @State private var busy = false
    @State private var error: String?

    private var isEdit: Bool { existing != nil }

    var body: some View {
        NavigationStack {
            Form {
                if !isEdit {
                    Section {
                        TextField("Login *", text: $loginText).textInputAutocapitalization(.never)
                    }
                }
                Section {
                    SecureField(isEdit ? "Yangi parol (ixtiyoriy)" : "Parol *", text: $parol)
                }
                Section {
                    TextField("Familiya *", text: $familiya)
                    TextField("Ism *", text: $ism)
                    TextField("Otasining ismi", text: $otasi)
                    TextField("Lavozim", text: $lavozim)
                    TextField("Bo'linma", text: $bolinma)
                    TextField("Telefon", text: $telefon).keyboardType(.phonePad)
                }
                Section("Rol") {
                    Picker("Rol", selection: $rol) {
                        ForEach([Roles.user, Roles.anticorAdmin, Roles.anticor, Roles.hrAdmin, Roles.hr, Roles.rahbariyat, Roles.xarid], id: \.self) { r in
                            Text(Roles.label(r)).tag(r)
                        }
                    }
                    .pickerStyle(.menu)
                    .labelsHidden()
                }
                if let error {
                    Text(error).foregroundColor(AppColors.coral)
                }
            }
            .navigationTitle(isEdit ? "Xodimni tahrirlash" : "Yangi xodim qo'shish")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Bekor qilish") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    if busy {
                        ProgressView()
                    } else {
                        Button("Saqlash", action: submit)
                    }
                }
            }
            .onAppear(perform: populate)
        }
    }

    private func populate() {
        guard let e = existing else { return }
        loginText = e.str("login") ?? ""
        familiya = e.str("familiya") ?? ""
        ism = e.str("ism") ?? ""
        otasi = e.str("otasi") ?? ""
        lavozim = e.str("lavozim") ?? ""
        bolinma = e.str("bolinma") ?? ""
        telefon = e.str("telefon") ?? ""
        rol = e.str("rol") ?? Roles.user
    }

    private func submit() {
        busy = true
        error = nil
        Task {
            var params: [String: Any] = [
                "familiya": familiya, "ism": ism, "otasi": otasi,
                "lavozim": lavozim, "bolinma": bolinma, "telefon": telefon, "rol": rol,
            ]
            if !parol.isEmpty { params["parol"] = parol }
            do {
                let data: [String: Any]
                if isEdit, let id = existing?["id"] {
                    params["id"] = id
                    data = try await session.api.call("editEmployee", params)
                } else {
                    params["login"] = loginText
                    data = try await session.api.call("addEmployee", params)
                }
                onSaved((data["pending"] as? Bool) == true)
                dismiss()
            } catch let apiError as APIError {
                error = apiError.message
            } catch {
                error = "Xatolik"
            }
            busy = false
        }
    }
}
