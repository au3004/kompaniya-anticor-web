import SwiftUI

/// Yuborilgan deklaratsiyaning yakuniy, QR-kodli "imzolangan" yozuv
/// sahifasi — veb versiyadagi declRenderRecord() bilan bir xil ko'rinish
/// va tarkib.
struct DeclarationRecordData: Hashable {
    let refId: String
    let verificationId: String
    let signedAt: Date
    let relatives: [RelativeEntry]
    let answers: [DeclarationAnswer]
    let hasConflict: Bool
    let employeeJshshir: String
}

struct DeclarationRecordView: View {
    @EnvironmentObject var session: SessionStore
    let record: DeclarationRecordData
    /// Butun wizard sheet'ini yopadi (DeclarationWizardView'ning o'z
    /// dismiss()'i — bu view navigatsiya stekida bir necha qadam ichkarida
    /// bo'lgani uchun o'zining @Environment(\.dismiss)'i faqat bitta qadam
    /// orqaga qaytarardi, butun sheet'ni emas).
    var onClose: () -> Void

    private var dateFormatter: DateFormatter {
        let f = DateFormatter()
        f.dateFormat = "dd.MM.yyyy · HH:mm"
        return f
    }
    private var dateOnlyFormatter: DateFormatter {
        let f = DateFormatter()
        f.dateFormat = "dd.MM.yyyy"
        return f
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                HStack {
                    Image(systemName: "checkmark.seal").foregroundColor(AppColors.teal)
                    Text("Deklaratsiya muvaffaqiyatli yuborildi").font(.subheadline.weight(.bold))
                }
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(AppColors.teal.opacity(0.1))
                .cornerRadius(12)

                VStack(spacing: 0) {
                    row("F.I.Sh.", session.user?.fullName ?? "")
                    row("JShShIR", record.employeeJshshir.isEmpty ? "—" : record.employeeJshshir)
                    row("Bo'linma", session.user?.bolinma ?? "—")
                    row("Lavozim", session.user?.lavozim ?? "—")
                    row("To'ldirilgan sana", dateOnlyFormatter.string(from: record.signedAt))
                    row("Status", "Yuborilgan", color: AppColors.teal)
                }
                .background(Color.white)
                .cornerRadius(12)

                Text("Yaqin qarindoshlar").font(.subheadline.weight(.bold))
                ForEach(record.relatives) { r in
                    VStack(alignment: .leading, spacing: 2) {
                        Text(r.label()).font(.footnote.weight(.semibold))
                        Text(r.data.holat == "info" ? "\(r.data.fullName) · \(r.data.workplaceText())" : (kHolatLabels[r.data.holat] ?? "—"))
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
                        if i < 5, let choice = record.answers[i].choice {
                            Text(choice == "ha" ? "HA" : "YO'Q")
                                .font(.caption2.weight(.bold))
                                .foregroundColor(choice == "ha" ? AppColors.coral : AppColors.teal)
                        }
                        if !record.answers[i].note.isEmpty {
                            Text(record.answers[i].note).font(.caption).foregroundColor(AppColors.textDim)
                        }
                    }
                    .padding(12)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(Color.white)
                    .cornerRadius(10)
                }

                Text(record.hasConflict
                     ? "Manfaatlar to'qnashuviga olib keladigan holatlar — MAVJUD"
                     : "Manfaatlar to'qnashuviga olib keladigan holatlar — MAVJUD EMAS")
                    .font(.caption.weight(.bold))
                    .foregroundColor(record.hasConflict ? AppColors.coral : AppColors.teal)
                    .padding(12)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background((record.hasConflict ? AppColors.coral : AppColors.teal).opacity(0.1))
                    .cornerRadius(10)

                VStack(alignment: .leading, spacing: 10) {
                    HStack {
                        Image(systemName: "checkmark.seal.fill").foregroundColor(AppColors.teal)
                        Text("Elektron imzo (E-IMZO)").font(.subheadline.weight(.heavy))
                    }
                    row("Imzolovchi F.I.Sh.", session.user?.fullName ?? "")
                    row("Imzolangan sana", dateFormatter.string(from: record.signedAt))
                    row("Verification ID", record.verificationId, monospaced: true)
                    Image(systemName: "qrcode").font(.system(size: 90)).foregroundColor(AppColors.azure).frame(maxWidth: .infinity)
                    Text("QR orqali imzoni tekshirish").font(.caption2).foregroundColor(AppColors.textDim).frame(maxWidth: .infinity)
                }
                .padding(16)
                .background(AppColors.teal.opacity(0.05))
                .overlay(RoundedRectangle(cornerRadius: 14).stroke(AppColors.teal.opacity(0.25)))
                .cornerRadius(14)
            }
            .padding(20)
        }
        .navigationTitle(record.refId)
        .navigationBarTitleDisplayMode(.inline)
        .navigationBarBackButtonHidden(true)
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                Button("Yopish", action: onClose)
            }
        }
    }

    private func row(_ label: String, _ value: String, color: Color = AppColors.text, monospaced: Bool = false) -> some View {
        HStack {
            Text(label).font(.caption).foregroundColor(AppColors.textDim)
            Spacer()
            Text(value).font(monospaced ? .caption.monospaced() : .caption.weight(.bold)).foregroundColor(color)
        }
        .padding(.horizontal, 4).padding(.vertical, 4)
    }
}

