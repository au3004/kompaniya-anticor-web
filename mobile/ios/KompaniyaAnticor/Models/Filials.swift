import Foundation

/// backend/src/Filials.php bilan bir xil belgilangan ro'yxat (erkin matn
/// emas — tanlov orqali belgilanadi).
enum Filials {
    static let ijroiyaApparati = "ijroiya_apparati"
    static let markaziy = "markaziy"
    static let shimoliy = "shimoliy"
    static let sharqiy = "sharqiy"
    static let janubiy = "janubiy"
    static let garbiy = "garbiy"
    static let janubiGarbiy = "janubi_garbiy"
    static let texnik = "texnik"
    static let tmsHub = "tms_hub"

    static let all = [
        ijroiyaApparati, markaziy, shimoliy, sharqiy,
        janubiy, garbiy, janubiGarbiy, texnik, tmsHub,
    ]

    static let labels: [String: String] = [
        ijroiyaApparati: "Ijroiya apparati",
        markaziy: "Markaziy filial",
        shimoliy: "Shimoliy filial",
        sharqiy: "Sharqiy filial",
        janubiy: "Janubiy filial",
        garbiy: "G'arbiy filial",
        janubiGarbiy: "Janubi-G'arbiy filial",
        texnik: "Ixtisoslashtirilgan texnik filial",
        tmsHub: "\"TMS Hub\" filiali",
    ]

    static func label(_ filial: String?) -> String {
        guard let filial, !filial.isEmpty else { return "—" }
        return labels[filial] ?? filial
    }
}
