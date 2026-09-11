import Foundation

struct AppUser {
    let id: Int
    let familiya: String
    let ism: String
    let otasi: String?
    let tugilganSana: String?
    let lavozim: String?
    let bolinma: String?
    let telefon: String?
    let rasm: String?
    let rol: String

    var fullName: String {
        [familiya, ism, otasi].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: " ")
    }

    init?(json: [String: Any]) {
        guard let id = json.int("id") else { return nil }
        self.id = id
        self.familiya = json.str("familiya") ?? ""
        self.ism = json.str("ism") ?? ""
        self.otasi = json.str("otasi")
        self.tugilganSana = json.str("tugilganSana")
        self.lavozim = json.str("lavozim")
        self.bolinma = json.str("bolinma")
        self.telefon = json.str("telefon")
        self.rasm = json.str("rasm")
        self.rol = json.str("rol") ?? Roles.user
    }
}
