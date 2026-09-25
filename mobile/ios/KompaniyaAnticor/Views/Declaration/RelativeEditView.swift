import SwiftUI

/// declModalFieldsHtml() (main.html) bilan bir xil qoidalar: "Ma'lumot
/// kiritaman" tanlansa F.I.Sh./ish turi majburiy, "tadbirkor" tanlansa
/// yuridik shaxs/STIR/boshqaruvdagi roli ham majburiy.
struct RelativeEditView: View {
    let entry: RelativeEntry
    let onSave: (RelativeData) -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var d: RelativeData
    @State private var error: String?

    init(entry: RelativeEntry, onSave: @escaping (RelativeData) -> Void) {
        self.entry = entry
        self.onSave = onSave
        _d = State(initialValue: entry.data)
    }

    var body: some View {
        NavigationStack {
            Form {
                Section("Holat *") {
                    Picker("Holat", selection: $d.holat) {
                        Text("— Tanlang —").tag("")
                        Text("Ma'lumot kiritaman").tag("info")
                        Text("Ma'lumotga ega emasman").tag("no_info")
                        Text("Vafot etgan").tag("deceased")
                        Text("Ajrashgan / aloqada emas").tag("no_contact")
                    }
                    .pickerStyle(.menu)
                    .labelsHidden()
                }

                if d.holat == "info" {
                    Section {
                        TextField("F.I.Sh. *", text: $d.fullName)
                        TextField("JShShIR (ПИНФЛ)", text: $d.jshshir).keyboardType(.numberPad)
                        TextField("Manzil", text: $d.address)
                    }

                    Section("Ish turi *") {
                        Picker("Ish turi", selection: $d.workType) {
                            Text("— Tanlang —").tag("")
                            Text("Tadbirkor / egadorlik").tag("entrepreneur")
                            Text("Boshqa").tag("other")
                        }
                        .pickerStyle(.segmented)
                        .labelsHidden()
                    }

                    if d.workType == "other" {
                        Section {
                            TextField("Ish joyi / lavozimi *", text: $d.workplacePosition)
                        }
                    }

                    if d.workType == "entrepreneur" {
                        Section("Tadbirkorlik ma'lumotlari") {
                            TextField("Yuridik shaxs nomi *", text: $d.legalEntityName)
                            TextField("STIR *", text: $d.stir).keyboardType(.numberPad)
                            TextField("Ulush miqdori", text: $d.ownershipShare)
                            TextField("Boshqaruvdagi roli *", text: $d.managementRole)
                        }
                    }
                }

                if let error {
                    Text(error).foregroundColor(AppColors.coral)
                }
            }
            .navigationTitle(entry.label() + " — ma'lumot kiritish")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Bekor qilish") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Saqlash") { save() }
                }
            }
        }
    }

    private func save() {
        if d.holat.isEmpty {
            error = "Holatni tanlang"
            return
        }
        if d.holat == "info" {
            if d.fullName.trimmingCharacters(in: .whitespaces).isEmpty {
                error = "F.I.Sh.ni to'ldiring"
                return
            }
            if d.workType.isEmpty {
                error = "Ish turini tanlang"
                return
            }
            if d.workType == "other" && d.workplacePosition.trimmingCharacters(in: .whitespaces).isEmpty {
                error = "Ish joyi/lavozimni kiriting"
                return
            }
            if d.workType == "entrepreneur" {
                if d.legalEntityName.trimmingCharacters(in: .whitespaces).isEmpty {
                    error = "Yuridik shaxs nomini kiriting"
                    return
                }
                if d.stir.trimmingCharacters(in: .whitespaces).isEmpty {
                    error = "STIR'ni kiriting"
                    return
                }
                if d.managementRole.trimmingCharacters(in: .whitespaces).isEmpty {
                    error = "Boshqaruvdagi rolini kiriting"
                    return
                }
            }
        }
        onSave(d)
        dismiss()
    }
}
