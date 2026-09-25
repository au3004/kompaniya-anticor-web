import Foundation

/// backend/src/Roles.php bilan bir xil rol/imtiyoz guruhlari.
///
/// Ilgari alohida bo'lgan hr-admin/hr/rahbariyat/xarid rollari olib
/// tashlangan — ularning barcha vakolati anticor-adminga o'tkazilgan.
enum Roles {
    static let user = "user"
    static let anticorAdmin = "anticor-admin"
    static let anticor = "anticor"
    static let superAdmin = "super-admin"

    static let anticorView = [anticorAdmin, anticor, superAdmin]
    static let anticorManage = [anticorAdmin, superAdmin]
    static let hrView = [anticorAdmin, superAdmin]
    static let hrManage = [anticorAdmin, superAdmin]
    static let hrEdit = [anticorAdmin, superAdmin]
    static let notifySend = [anticorAdmin, anticor, superAdmin]
    static let anyPanelAccess = [anticorAdmin, anticor, superAdmin]
    static let purchaseView = [anticorAdmin, superAdmin]
    static let purchaseEntry = [anticorAdmin, superAdmin]
    static let hrDocs = [anticorAdmin, superAdmin]

    static let labels: [String: String] = [
        user: "Oddiy xodim",
        anticorAdmin: "Anticor - boshqaruvchi",
        anticor: "Anticor",
        superAdmin: "Super-admin",
    ]

    static func label(_ role: String?) -> String {
        guard let role else { return "—" }
        return labels[role] ?? role
    }
}
