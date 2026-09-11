import Foundation

/// backend/src/Roles.php bilan bir xil rol/imtiyoz guruhlari.
enum Roles {
    static let user = "user"
    static let anticorAdmin = "anticor-admin"
    static let anticor = "anticor"
    static let hrAdmin = "hr-admin"
    static let hr = "hr"
    static let superAdmin = "super-admin"
    static let rahbariyat = "rahbariyat"
    static let xarid = "xarid"

    static let anticorView = [anticorAdmin, anticor, superAdmin]
    static let anticorManage = [anticorAdmin, superAdmin]
    static let hrView = [hrAdmin, hr, rahbariyat, superAdmin]
    static let hrManage = [hrAdmin, superAdmin]
    static let hrEdit = [hrAdmin, hr, superAdmin]
    static let notifySend = [anticorAdmin, anticor, hrAdmin, hr, rahbariyat, superAdmin]
    static let anyPanelAccess = [anticorAdmin, anticor, hrAdmin, hr, rahbariyat, superAdmin, xarid]
    static let purchaseView = [xarid, anticorAdmin, superAdmin]
    static let purchaseEntry = [xarid, superAdmin]
    static let hrDocs = [hrAdmin, hr, superAdmin]
    static let requestApprove = [anticorAdmin, rahbariyat, superAdmin]

    static let labels: [String: String] = [
        user: "Oddiy xodim",
        anticorAdmin: "Anticor - boshqaruvchi",
        anticor: "Anticor",
        hrAdmin: "HR - boshqaruvchi",
        hr: "HR",
        rahbariyat: "Rahbariyat",
        xarid: "Xarid",
    ]

    static func label(_ role: String?) -> String {
        guard let role else { return "—" }
        return labels[role] ?? role
    }
}
