import Foundation

/// Veb versiyaning (main.html) relativeTypes ro'yxati bilan bir xil.
struct RelativeType: Identifiable, Hashable {
    let key: String
    let label: String
    var id: String { key }
}

let kRelativeTypes: [RelativeType] = [
    RelativeType(key: "aka-uka", label: "Aka-uka"),
    RelativeType(key: "opa-singil", label: "Opa-singil"),
    RelativeType(key: "ogil-qiz", label: "O'g'il-qiz"),
    RelativeType(key: "turmush-ortogi", label: "Turmush o'rtog'i"),
    RelativeType(key: "qaynota-ona", label: "Qaynota/qaynona"),
    RelativeType(key: "qayn-akauka", label: "Qayn aka-uka"),
    RelativeType(key: "qayn-opasingil", label: "Qayn opa-singil"),
]

let kHolatLabels: [String: String] = [
    "info": "To'ldirildi",
    "no_info": "Ma'lumotga ega emas",
    "deceased": "Vafot etgan",
    "no_contact": "Ajrashgan / aloqada emas",
]

/// Bitta yaqin qarindosh haqidagi to'ldiriladigan ma'lumot (veb versiyadagi
/// declModalFieldsHtml() bilan bir xil maydonlar).
struct RelativeData: Hashable {
    var holat: String = "" // "" | info | no_info | deceased | no_contact
    var fullName: String = ""
    var jshshir: String = ""
    var address: String = ""
    var workType: String = "" // entrepreneur | other
    var workplacePosition: String = ""
    var legalEntityName: String = ""
    var stir: String = ""
    var ownershipShare: String = ""
    var managementRole: String = ""

    var isFilled: Bool { !holat.isEmpty }

    func workplaceText() -> String {
        guard holat == "info" else { return "—" }
        if workType == "other" { return workplacePosition.isEmpty ? "—" : workplacePosition }
        if workType == "entrepreneur" { return legalEntityName.isEmpty ? "—" : legalEntityName }
        return "—"
    }

    func toJSON() -> [String: Any] {
        ["holat": holat, "fullName": fullName, "jshshir": jshshir, "address": address,
         "workType": workType, "workplacePosition": workplacePosition, "legalEntityName": legalEntityName,
         "stir": stir, "ownershipShare": ownershipShare, "managementRole": managementRole]
    }
}

/// Ota/ona (fixed, o'chirilmaydi) yoki foydalanuvchi qo'shgan tur — bir xil
/// turdan bir nechtasi bo'lsa suffixNum: 2, 3, ...
struct RelativeEntry: Identifiable, Hashable {
    let id: String
    var typeKey: String?
    var suffixNum: Int?
    var fixed: Bool = false
    var data: RelativeData = RelativeData()

    var filled: Bool { data.isFilled }

    func label() -> String {
        if id == "ota" { return "Ota" }
        if id == "ona" { return "Ona" }
        let base = kRelativeTypes.first(where: { $0.key == typeKey })?.label ?? ""
        if let n = suffixNum { return "\(base) \(n)" }
        return base
    }

    func toJSON() -> [String: Any] {
        ["id": id, "typeKey": typeKey as Any, "suffixNum": suffixNum as Any,
         "fixed": fixed, "filled": filled, "label": label(), "data": data.toJSON()]
    }
}

struct DeclarationAnswer: Hashable {
    var choice: String? // "ha" | "yoq" — faqat birinchi 5 savol uchun
    var note: String = ""

    func toJSON() -> [String: Any] { ["choice": choice as Any, "note": note] }
}

/// Veb versiyadagi ADMIN_DECL_QUESTIONS bilan bir xil 7 ta savol — birinchi
/// 5 tasi HA/YO'Q + izoh, oxirgi 2 tasi faqat erkin matn (izoh).
let kDeclarationQuestions: [String] = [
    "Siz boshqaruv organi (boshqaruv, Kuzatuv kengashi, direktorlar kengashi va hokazolar) xodimi, a'zosi, qandaydir tashkilot direktori (bosh buxgalteri, buxgalteri va hokazo) yoki vakilimisiz?",
    "Sizda / yaqin qarindoshlaringizda qandaydir tashkilotlarda moliyaviy manfaatdorlik bormi (ustav kapitalida ishtirok, aksiya va obligatsiyalarga egalik) yoki bunday tashkilotlar qarorlariga boshqa tarzda ta'sir ko'rsata olasizmi?",
    "Yaqin qarindoshlaringiz boshqaruv organlari (boshqaruv, kuzatuv kengashi, direktorlar kengashi va h.k.) xodimi, a'zosi, tashkilot direktori yoki vakilimi?",
    "Yaqin qarindoshlaringiz davlat organlarining mansabdor shaxsi hisoblanadimi?",
    "Shaxsiy manfaatlaringiz, yaqin qarindoshlaringiz yoki aloqador shaxslar manfaatlari yo'lida maxfiy hisoblangan, davlat organlari va tashkilotlarida ishlash davomida ma'lum bo'lgan axborotdan foydalanganmisiz?",
    "Manfaatlar to'qnashuviga olib kelishi mumkin bo'lgan boshqa shart-sharoitlar mavjud bo'lsa, ularni ko'rsatib o'ting.",
    "Zarur topsangiz, har qanday qo'shimcha ma'lumotni ko'rsating.",
]

struct DeclarationTerm {
    let title: String
    let text: String
}

/// Veb versiyadagi terms[] (step3 — asosiy atamalar) bilan bir xil.
let kDeclarationTerms: [DeclarationTerm] = [
    DeclarationTerm(title: "Yaqin qarindoshlar", text: "Ota-onalar, aka-ukalar, opa-singillar, o'g'illar, qizlar, er-xotinlar, shuningdek er-xotinlarning ota-onalari, aka-ukalari, opa-singillari va farzandlari."),
    DeclarationTerm(title: "Aloqador shaxslar", text: "Xodimning yaqin qarindoshlari; xodim va (yoki) yaqin qarindoshlari ustav fondida ulush/aksiyaga ega bo'lgan yuridik shaxs; xodim yoxud yaqin qarindoshlari boshqaruv organi rahbari yoki a'zosi bo'lgan yuridik shaxs."),
    DeclarationTerm(title: "Manfaatlar to'qnashuvi", text: "Xodimning shaxsiy (bevosita yoki bilvosita) manfaatdorligi lavozim majburiyatlarini lozim darajada bajarishiga ta'sir ko'rsatayotgan yoki ko'rsatishi mumkin bo'lgan, shaxsiy manfaatdorlik bilan fuqarolar, tashkilotlar, jamiyat yoki davlat huquqlari va qonuniy manfaatlari o'rtasida qarama-qarshilik yuzaga kelgan (mavjud) yoki kelishi mumkin bo'lgan (ehtimoliy) vaziyat."),
    DeclarationTerm(title: "Xodimning shaxsiy manfaatdorligi", text: "Xodim yoxud u bilan aloqador shaxslar xodim tomonidan qaror qabul qilinishi yoki jarayonda boshqacha ishtirok etishi natijasida olishi mumkin bo'lgan har qanday naf yoki afzallik."),
]
