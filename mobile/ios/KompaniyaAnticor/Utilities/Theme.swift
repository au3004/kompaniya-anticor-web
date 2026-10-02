import SwiftUI

/// Veb ilovaning rang palitrasi bilan mos (login.html/admin.html'dagi
/// --azure/--teal/--coral CSS o'zgaruvchilari).
enum AppColors {
    static let azure = Color(red: 0x00 / 255, green: 0x44 / 255, blue: 0x91 / 255)
    static let teal = Color(red: 0x1D / 255, green: 0x6F / 255, blue: 0xBF / 255)
    static let coral = Color(red: 0xFF / 255, green: 0x6B / 255, blue: 0x6B / 255)
    static let bg = Color(red: 0xF1 / 255, green: 0xF2 / 255, blue: 0xF4 / 255)
    static let bgDeep = Color(red: 0xE4 / 255, green: 0xE6 / 255, blue: 0xE9 / 255)
    static let text = Color(red: 0x0B / 255, green: 0x25 / 255, blue: 0x45 / 255)
    static let textDim = Color(red: 0x4E / 255, green: 0x6A / 255, blue: 0x88 / 255)
    static let cardBorder = Color(red: 0x0B / 255, green: 0x25 / 255, blue: 0x45 / 255).opacity(0.14)
}
