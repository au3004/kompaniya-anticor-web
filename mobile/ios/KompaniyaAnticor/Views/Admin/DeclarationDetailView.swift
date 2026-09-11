import SwiftUI

/// Boshqaruv panelidan bitta deklaratsiyaning to'liq ma'lumotini ko'rish.
struct DeclarationDetailView: View {
    let declaration: [String: Any]

    var body: some View {
        let payload = (declaration["payload"] as? [String: Any]) ?? [:]
        let relatives = payload.arr("relatives")
        let answers = payload.arr("answers")
        let confirm = (payload["confirm"] as? [String: Any]) ?? [:]
        let hasConflict = (confirm["hasConflict"] as? Bool) == true

        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                VStack(spacing: 0) {
                    row("F.I.Sh.", declaration.str("fullName") ?? "—")
                    row("JShShIR", payload.str("employeeJshshir") ?? "—")
                    row("Lavozim", declaration.str("lavozim") ?? "—")
                    row("Bo'linma", declaration.str("bolinma") ?? "—")
                    row("Telefon", declaration.str("telefon") ?? "—")
                    row("Yuborilgan sana", declaration.str("submittedAt") ?? "—")
                    row("Verification ID", declaration.str("verificationId") ?? "—", monospaced: true)
                }
                .background(Color.white)
                .cornerRadius(12)

                Text(hasConflict ? "Manfaatlar to'qnashuvi — MAVJUD" : "Manfaatlar to'qnashuvi — MAVJUD EMAS")
                    .font(.subheadline.weight(.bold))
                    .foregroundColor(hasConflict ? AppColors.coral : AppColors.teal)
                    .padding(12)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background((hasConflict ? AppColors.coral : AppColors.teal).opacity(0.1))
                    .cornerRadius(10)

                Text("Yaqin qarindoshlar").font(.subheadline.weight(.bold))
                ForEach(Array(relatives.enumerated()), id: \.offset) { _, r in
                    let data = (r["data"] as? [String: Any]) ?? [:]
                    VStack(alignment: .leading, spacing: 2) {
                        Text(r.str("label") ?? "").font(.footnote.weight(.semibold))
                        Text(data.str("holat") == "info" ? (data.str("fullName") ?? "—") : (kHolatLabels[data.str("holat") ?? ""] ?? "—"))
                            .font(.caption).foregroundColor(AppColors.textDim)
                    }
                    .padding(10)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(Color.white)
                    .cornerRadius(10)
                }

                Text("Savollar va javoblar").font(.subheadline.weight(.bold))
                ForEach(Array(kDeclarationQuestions.enumerated()), id: \.offset) { i, q in
                    VStack(alignment: .leading, spacing: 6) {
                        Text("\(i + 1). \(q)").font(.caption)
                        if i < answers.count {
                            let a = answers[i]
                            if let choice = a.str("choice") {
                                Text(choice == "ha" ? "HA" : "YO'Q")
                                    .font(.caption2.weight(.bold))
                                    .foregroundColor(choice == "ha" ? AppColors.coral : AppColors.teal)
                            }
                            if let note = a.str("note"), !note.isEmpty {
                                Text(note).font(.caption).foregroundColor(AppColors.textDim)
                            }
                        }
                    }
                    .padding(12)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(Color.white)
                    .cornerRadius(10)
                }
            }
            .padding(20)
        }
        .navigationTitle(declaration.str("refId") ?? "Deklaratsiya")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func row(_ label: String, _ value: String, monospaced: Bool = false) -> some View {
        HStack {
            Text(label).font(.caption).foregroundColor(AppColors.textDim)
            Spacer()
            Text(value).font(monospaced ? .caption.monospaced() : .caption.weight(.bold))
                .multilineTextAlignment(.trailing)
        }
        .padding(.horizontal, 12).padding(.vertical, 8)
    }
}
