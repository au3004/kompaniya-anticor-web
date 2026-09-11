/// Backend action-dispatch API'dan qaytgan xatolik (`{success:false, code, message}`).
class ApiException implements Exception {
  final String code;
  final String message;
  final int statusCode;

  ApiException({required this.code, required this.message, required this.statusCode});

  @override
  String toString() => message;
}
