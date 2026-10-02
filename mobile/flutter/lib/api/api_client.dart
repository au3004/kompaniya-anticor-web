import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_exception.dart';

/// Backendning yagona action-dispatch endpoint'i bilan gaplashadigan
/// past darajadagi klient. Har bir chaqiruv `POST {baseUrl}/index.php`ga
/// `{"action": "...", ...}` JSON tanasi bilan boradi — xuddi veb frontend
/// (main.html/admin.html) ishlatadigan `apiCall()` bilan bir xil andoza.
///
/// Autentifikatsiya: backend HttpOnly cookie'dan tashqari
/// "Authorization: Bearer <token>" sarlavhasini ham qabul qiladi (qarang:
/// backend/src/Auth.php::tokenFromRequest()). Token doim
/// flutter_secure_storage orqali (iOS Keychain / Android Keystore)
/// saqlanadi — hech qachon oddiy SharedPreferences yoki xotirada oddiy
/// o'zgaruvchida emas.
class ApiClient {
  /// Backend'ning ochiq manzili — server joylashuviga qarab o'zgartiring.
  /// Mahalliy rivojlantirishda masalan: http://10.0.2.2:8080/backend/public
  /// (Android emulyatori "localhost"ni shunday ko'radi) yoki
  /// http://localhost:8080/backend/public (iOS simulyatori uchun).
  static const String defaultBaseUrl = 'https://your-domain.uz/backend/public';

  final String baseUrl;
  final http.Client _http;
  final FlutterSecureStorage _storage;

  static const _tokenKey = 'session_token';
  static const _rememberTokenKey = 'remember_token';

  ApiClient({String? baseUrl, http.Client? httpClient, FlutterSecureStorage? storage})
      : baseUrl = baseUrl ?? defaultBaseUrl,
        _http = httpClient ?? http.Client(),
        _storage = storage ?? const FlutterSecureStorage();

  Future<String?> get storedToken => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  /// "Meni eslab qol" tokeni — sessiya harakatsizlikdan (10 daqiqa) tugab
  /// qolganda parolsiz jim qayta kirish uchun (mobileLoginViaRememberToken).
  /// Mutlaq 1 soatlik umr chegarasiga ega, hech qachon uzaytirilmaydi.
  Future<String?> get storedRememberToken => _storage.read(key: _rememberTokenKey);

  Future<void> saveRememberToken(String token) => _storage.write(key: _rememberTokenKey, value: token);

  Future<void> clearRememberToken() => _storage.delete(key: _rememberTokenKey);

  /// Asosiy chaqiruv usuli. `params` ichiga `action`ni qo'shmang — u
  /// alohida beriladi. Muvaffaqiyatli javobning to'liq (`success` ham
  /// ichida bo'lgan) xaritasini qaytaradi; xatolikda ApiException otadi.
  Future<Map<String, dynamic>> call(String action, [Map<String, dynamic> params = const {}]) async {
    final token = await storedToken;
    final headers = <String, String>{
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };

    final body = jsonEncode({'action': action, ...params});

    late http.Response resp;
    try {
      resp = await _http.post(Uri.parse('$baseUrl/index.php'), headers: headers, body: body);
    } catch (e) {
      throw ApiException(code: 'NETWORK_ERROR', message: "Tarmoq xatoligi: so'rov yuborilmadi", statusCode: 0);
    }

    Map<String, dynamic> data;
    try {
      data = jsonDecode(resp.body) as Map<String, dynamic>;
    } catch (e) {
      throw ApiException(code: 'BAD_RESPONSE', message: "Server javobini o'qib bo'lmadi", statusCode: resp.statusCode);
    }

    if (data['success'] != true) {
      throw ApiException(
        code: (data['code'] as String?) ?? 'ERROR',
        message: (data['message'] as String?) ?? "Noma'lum xatolik",
        statusCode: resp.statusCode,
      );
    }

    return data;
  }

  /// document-download.php / hr-document-download.php / purchase-download.php
  /// / backup-download.php kabi JSON action-dispatch'dan tashqaridagi
  /// GET skriptlarni Bearer sarlavhasi bilan yuklab oladi. [pathAndQuery]
  /// `baseUrl`dan keyingi qism, masalan "document-download.php?id=5".
  Future<List<int>> downloadFile(String pathAndQuery) async {
    final token = await storedToken;
    final headers = <String, String>{
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
    late http.Response resp;
    try {
      resp = await _http.get(Uri.parse('$baseUrl/$pathAndQuery'), headers: headers);
    } catch (e) {
      throw ApiException(code: 'NETWORK_ERROR', message: "Tarmoq xatoligi: fayl yuklanmadi", statusCode: 0);
    }
    if (resp.statusCode != 200) {
      throw ApiException(code: 'DOWNLOAD_FAILED', message: "Faylni yuklab bo'lmadi", statusCode: resp.statusCode);
    }
    return resp.bodyBytes;
  }

  /// Binary faylni backend kutgan "data:<mime>;base64,..." shakliga o'giradi
  /// (hujjat/rasm yuklash amallari — addDocument, submitHrDocument,
  /// addPurchase, updateProfilePhoto — shu formatni JSON tana ichida kutadi).
  static String toDataUrl(List<int> bytes, String mimeType) {
    return 'data:$mimeType;base64,${base64Encode(bytes)}';
  }
}
