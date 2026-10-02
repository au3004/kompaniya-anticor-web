import SwiftUI

/// "Manfaatlar to'qnashuvi" deklaratsiyasi — veb versiyaning (main.html,
/// declStep*Html funksiyalari) 8 bosqichli wizard'i bilan bir xil oqim:
/// 1) Hujjatlar bilan tanishish  2) Xodim ma'lumotlari  3) Atamalar
/// 4) Yaqin qarindoshlar  5) Savollar  6) Yakuniy tasdiqlash
/// 7) Ko'rib chiqish  8) E-IMZO bilan imzolash (mock, real E-IMZO
/// integratsiyasi hali yo'q — backend ham shuni ko'zda tutadi).
struct DeclarationWizardView: View {
    @EnvironmentObject var session: SessionStore
    @Environment(\.dismiss) private var dismiss

    @State private var step = 0
    private let stepCount = 8

    @State private var check1 = false
    @State private var jshshir = ""
    @State private var check3 = false

    @State private var relatives: [RelativeEntry] = [
        RelativeEntry(id: "ota", fixed: true),
        RelativeEntry(id: "ona", fixed: true),
    ]
    @State private var relSeq = 0
    @State private var editingRelative: RelativeEntry?

    @State private var answers: [DeclarationAnswer] = Array(repeating: DeclarationAnswer(), count: kDeclarationQuestions.count)

    @State private var hasConflict: Bool?
    @State private var ack1 = false
    @State private var ack2 = false

    @State private var signPassword = ""
    @State private var submitting = false
    @State private var submitError: String?
    @State private var record: DeclarationRecordData?

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                ProgressView(value: Double(step + 1), total: Double(stepCount))
                    .tint(AppColors.azure)
                ScrollView {
                    stepContent.padding(20)
                }
                nav
            }
            .navigationTitle("Deklaratsiya")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button { dismiss() } label: { Image(systemName: "xmark") }
                }
            }
            .sheet(item: $editingRelative) { entry in
                RelativeEditView(entry: entry) { newData in
                    if let idx = relatives.firstIndex(where: { $0.id == entry.id }) {
                        relatives[idx].data = newData
                    }
                }
            }
            .navigationDestination(item: $record) { rec in
                DeclarationRecordView(record: rec, onClose: { dismiss() })
            }
        }
    }

    // MARK: - Step content

    @ViewBuilder private var stepContent: some View {
        switch step {
        case 0: stepIntro
        case 1: stepEmployee
        case 2: stepTerms
        case 3: stepRelatives
        case 4: stepQuestions
        case 5: stepConfirm
        case 6: stepReview
        default: stepEsign
        }
    }

    private func title(_ t: String) -> some View {
        Text(t).font(.title3.weight(.heavy)).foregroundColor(AppColors.text)
    }
    private func sub(_ s: String) -> some View {
        Text(s).font(.footnote).foregroundColor(AppColors.textDim).padding(.top, 4).padding(.bottom, 12)
    }

    private var stepIntro: some View {
        VStack(alignment: .leading, spacing: 8) {
            title("Qonunchilik va Nizom talablari bilan tanishish")
            sub("Deklaratsiyani to'ldirishdan avval quyidagi hujjatlar bilan tanishib chiqing va tanishganingizni tasdiqlang.")
            HStack {
                Image(systemName: "doc.text").foregroundColor(AppColors.azure)
                Text("Manfaatlar to'qnashuvi to'g'risidagi Nizom")
                Spacer()
                Text("Ochish").font(.footnote).foregroundColor(AppColors.azure)
            }
            .padding(14)
            .background(Color.white)
            .cornerRadius(12)

            Toggle(isOn: $check1) {
                Text("Men yuqoridagi Qonun va «O'zbektelekom» AK Nizomi talablari bilan to'liq tanishdim va ularga rioya etish majburiyatini olaman.")
                    .font(.footnote)
            }
            .padding(.top, 8)
        }
    }

    private var stepEmployee: some View {
        VStack(alignment: .leading, spacing: 12) {
            title("Xodim ma'lumotlari")
            sub("Ma'lumotlarda xatolik bo'lsa, Inson resurslarini boshqarish xizmatiga murojaat qiling.")
            labeledReadonly("F.I.Sh. (to'liq)", session.user?.fullName ?? "")
            VStack(alignment: .leading, spacing: 4) {
                Text("JShShIR (ПИНФЛ)").font(.caption).foregroundColor(AppColors.textDim)
                TextField("14 raqam", text: $jshshir).textFieldStyle(.roundedBorder).keyboardType(.numberPad)
            }
            labeledReadonly("Bo'linma", session.user?.bolinma ?? "—")
            labeledReadonly("Lavozim", session.user?.lavozim ?? "—")
        }
    }

    private var stepTerms: some View {
        VStack(alignment: .leading, spacing: 8) {
            title("Asosiy atamalar")
            sub("Deklaratsiyada qo'llaniladigan atamalar bilan tanishib chiqing — savollarga to'g'ri javob berish uchun muhim.")
            ForEach(kDeclarationTerms, id: \.title) { term in
                VStack(alignment: .leading, spacing: 4) {
                    Text(term.title).font(.subheadline.weight(.bold))
                    Text(term.text).font(.caption).foregroundColor(AppColors.textDim)
                }
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.white)
                .cornerRadius(12)
            }
            Toggle(isOn: $check3) {
                Text("Atamalar mazmuni bilan tanishdim.").font(.footnote)
            }
            .padding(.top, 4)
        }
    }

    private var stepRelatives: some View {
        let filled = relatives.filter(\.filled).count
        return VStack(alignment: .leading, spacing: 8) {
            title("Yaqin qarindoshlar to'g'risida ma'lumot")
            sub("Har bir yaqin qarindosh bo'yicha ma'lumot kiritish majburiy. To'ldirilgan: \(filled)/\(relatives.count)")
            ForEach(relatives) { r in
                HStack {
                    VStack(alignment: .leading, spacing: 2) {
                        Text(r.label()).font(.subheadline.weight(.semibold))
                        Text(r.filled ? (kHolatLabels[r.data.holat] ?? "To'ldirildi") : "Ma'lumot kiritilmagan")
                            .font(.caption)
                            .foregroundColor(r.filled ? AppColors.teal : AppColors.textDim)
                    }
                    Spacer()
                    Button(r.filled ? "Tahrirlash" : "To'ldirish") { editingRelative = r }
                        .font(.footnote)
                    if !r.fixed {
                        Button {
                            relatives.removeAll { $0.id == r.id }
                        } label: {
                            Image(systemName: "xmark.circle.fill").foregroundColor(AppColors.coral)
                        }
                    }
                }
                .padding(12)
                .background(Color.white)
                .cornerRadius(12)
            }
            Menu {
                ForEach(kRelativeTypes) { t in
                    Button(t.label) { addRelative(t) }
                }
            } label: {
                Text("Yana qarindosh qo'shish (aka-uka, farzand va h.k.)")
                    .font(.footnote.weight(.semibold))
                    .foregroundColor(AppColors.azure)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 12)
                    .overlay(RoundedRectangle(cornerRadius: 10).stroke(AppColors.azure))
            }
        }
    }

    private var stepQuestions: some View {
        VStack(alignment: .leading, spacing: 12) {
            title("Deklaratsiya savollari")
            sub("Har bir savolga javob bering.")
            ForEach(Array(kDeclarationQuestions.enumerated()), id: \.offset) { i, q in
                VStack(alignment: .leading, spacing: 8) {
                    Text("\(i + 1). \(q)").font(.footnote.weight(.semibold))
                    if i < 5 {
                        HStack {
                            choiceChip("HA", selected: answers[i].choice == "ha") { answers[i].choice = "ha" }
                            choiceChip("YO'Q", selected: answers[i].choice == "yoq") { answers[i].choice = "yoq" }
                        }
                    }
                    TextField(i < 5 ? "Izoh (ixtiyoriy)" : "Javobingiz", text: Binding(
                        get: { answers[i].note },
                        set: { answers[i].note = $0 }
                    ), axis: .vertical)
                    .textFieldStyle(.roundedBorder)
                    .lineLimit(2, reservesSpace: true)
                }
                .padding(14)
                .background(Color.white)
                .cornerRadius(12)
            }
        }
    }

    private var stepConfirm: some View {
        VStack(alignment: .leading, spacing: 12) {
            title("Yakuniy tasdiqlash")
            sub("Deklaratsiyani imzolashdan oldin quyidagilarni tasdiqlang.")
            confirmOption(
                selected: hasConflict == true,
                heading: "Menda manfaatlar to'qnashuviga olib keladigan holatlar MAVJUD",
                detail: "Savollarda «Ha» deb belgilagan holatlaringiz bo'lsa, ushbu variantni tanlang."
            ) { hasConflict = true }
            confirmOption(
                selected: hasConflict == false,
                heading: "Menda manfaatlar to'qnashuviga olib keladigan holatlar MAVJUD EMAS",
                detail: "Hech qanday to'qnashuv holati bo'lmasa, ushbu variantni tanlang."
            ) { hasConflict = false }

            Toggle(isOn: $ack1) {
                Text("Ushbu deklaratsiyada aks ettirilgan ma'lumotlar to'liqligi va haqqoniyligini tasdiqlayman hamda ushbu ma'lumotlar tegishli huquq-tartibot organlari tomonidan tekshirilishiga rozilik bildiraman.")
                    .font(.caption)
            }
            Toggle(isOn: $ack2) {
                Text("Nizom talablariga binoan, deklaratsiya haqqoniyligiga ta'sir qiladigan yangi holatlar to'g'risida Kompaniyaga darhol xabar berish majburiyatini zimmamga olaman.")
                    .font(.caption)
            }
        }
    }

    private var stepReview: some View {
        VStack(alignment: .leading, spacing: 12) {
            title("Ko'rib chiqish")
            sub("Yuborishdan oldin ma'lumotlarni tekshiring.")
            VStack(spacing: 0) {
                reviewRow("F.I.Sh.", session.user?.fullName ?? "")
                reviewRow("JShShIR", jshshir)
                reviewRow("Bo'linma", session.user?.bolinma ?? "—")
                reviewRow("Lavozim", session.user?.lavozim ?? "—")
                reviewRow("Manfaatlar to'qnashuvi", hasConflict == true ? "MAVJUD" : "MAVJUD EMAS",
                           color: hasConflict == true ? AppColors.coral : AppColors.teal)
            }
            .background(Color.white)
            .cornerRadius(12)

            Text("Yaqin qarindoshlar").font(.subheadline.weight(.bold))
            ForEach(relatives) { r in
                VStack(alignment: .leading, spacing: 2) {
                    Text(r.label()).font(.footnote.weight(.semibold))
                    Text(r.data.holat == "info" ? r.data.fullName : (kHolatLabels[r.data.holat] ?? "—"))
                        .font(.caption).foregroundColor(AppColors.textDim)
                }
                .padding(10)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.white)
                .cornerRadius(10)
            }
        }
    }

    private var stepEsign: some View {
        VStack(alignment: .leading, spacing: 12) {
            title("E-IMZO bilan imzolash")
            sub("Deklaratsiyani yakunlash uchun parolingizni tasdiqlang.")
            SecureField("Parol", text: $signPassword).textFieldStyle(.roundedBorder)
            if let submitError {
                Text(submitError).font(.footnote).foregroundColor(AppColors.coral)
            }
        }
    }

    // MARK: - Helpers

    private func labeledReadonly(_ label: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(label).font(.caption).foregroundColor(AppColors.textDim)
            Text(value.isEmpty ? "—" : value).font(.subheadline)
                .padding(10)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(AppColors.bgDeep)
                .cornerRadius(8)
        }
    }

    private func choiceChip(_ text: String, selected: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(text)
                .font(.caption.weight(.bold))
                .padding(.horizontal, 14).padding(.vertical, 6)
                .background(selected ? AppColors.azure : AppColors.bgDeep)
                .foregroundColor(selected ? .white : AppColors.text)
                .clipShape(Capsule())
        }
    }

    private func confirmOption(selected: Bool, heading: String, detail: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(alignment: .top, spacing: 10) {
                Image(systemName: selected ? "largecircle.fill.circle" : "circle").foregroundColor(selected ? AppColors.azure : AppColors.textDim)
                VStack(alignment: .leading, spacing: 3) {
                    Text(heading).font(.footnote.weight(.bold)).foregroundColor(AppColors.text)
                    Text(detail).font(.caption).foregroundColor(AppColors.textDim)
                }
            }
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(selected ? AppColors.azure.opacity(0.05) : Color.white)
            .overlay(RoundedRectangle(cornerRadius: 12).stroke(selected ? AppColors.azure : AppColors.cardBorder, lineWidth: selected ? 1.6 : 1))
            .cornerRadius(12)
        }
    }

    private func reviewRow(_ label: String, _ value: String, color: Color = AppColors.text) -> some View {
        HStack {
            Text(label).font(.footnote).foregroundColor(AppColors.textDim)
            Spacer()
            Text(value).font(.footnote.weight(.bold)).foregroundColor(color)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 8)
    }

    private func addRelative(_ type: RelativeType) {
        relSeq += 1
        let sameCount = relatives.filter { $0.typeKey == type.key }.count
        relatives.append(RelativeEntry(id: "rel\(relSeq)", typeKey: type.key, suffixNum: sameCount > 0 ? sameCount + 1 : nil))
    }

    private func canAdvance() -> Bool {
        switch step {
        case 0: return check1
        case 2: return check3
        case 3: return !relatives.isEmpty && relatives.allSatisfy(\.filled)
        case 4: return answers.prefix(5).allSatisfy { $0.choice != nil }
        case 5: return hasConflict != nil && ack1 && ack2
        default: return true
        }
    }

    private var nav: some View {
        HStack(spacing: 10) {
            if step > 0 {
                Button("Ortga") { step = max(0, step - 1) }
                    .buttonStyle(.bordered)
            }
            Button {
                if step == stepCount - 1 {
                    submit()
                } else {
                    step = min(stepCount - 1, step + 1)
                }
            } label: {
                if submitting {
                    ProgressView().tint(.white).frame(maxWidth: .infinity)
                } else {
                    Text(step == stepCount - 1 ? "Imzolash va yuborish" : "Keyingisi").frame(maxWidth: .infinity)
                }
            }
            .buttonStyle(.borderedProminent).tint(AppColors.azure)
            .disabled(!canAdvance() || submitting)
        }
        .padding(16)
    }

    private func submit() {
        guard !signPassword.isEmpty else {
            submitError = "Parolni kiriting"
            return
        }
        submitting = true
        submitError = nil

        let now = Date()
        let verificationId = "UT-EIMZO-\(randomCode(8))"
        let year = Calendar.current.component(.year, from: now)
        let refId = "DEK-\(year)-\(randomCode(6))"

        let payload: [String: Any] = [
            "relatives": relatives.map { $0.toJSON() },
            "answers": answers.map { $0.toJSON() },
            "confirm": ["hasConflict": hasConflict as Any, "ack1": ack1, "ack2": ack2],
            "employeeJshshir": jshshir,
        ]

        Task {
            do {
                let jsonData = try JSONSerialization.data(withJSONObject: payload)
                let payloadString = String(data: jsonData, encoding: .utf8) ?? "{}"
                _ = try await session.api.call("submitDeclaration", [
                    "refId": refId,
                    "verificationId": verificationId,
                    "hasConflict": hasConflict == true,
                    "payload": payloadString,
                ])
                record = DeclarationRecordData(
                    refId: refId, verificationId: verificationId, signedAt: now,
                    relatives: relatives, answers: answers,
                    hasConflict: hasConflict == true, employeeJshshir: jshshir
                )
            } catch let apiError as APIError {
                submitError = apiError.message
            } catch {
                submitError = "Xatolik yuz berdi"
            }
            submitting = false
        }
    }

    private func randomCode(_ len: Int) -> String {
        let chars = Array("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789")
        return String((0..<len).map { _ in chars.randomElement()! })
    }
}
