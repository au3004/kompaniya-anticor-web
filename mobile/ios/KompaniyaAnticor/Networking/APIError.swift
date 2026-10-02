import Foundation

/// Backend action-dispatch API'dan qaytgan xatolik (`{success:false, code, message}`).
struct APIError: Error, LocalizedError {
    let code: String
    let message: String
    let statusCode: Int

    var errorDescription: String? { message }
}
