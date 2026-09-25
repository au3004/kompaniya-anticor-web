import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../models/user.dart';

/// Ilova bo'ylab sessiya holatini (joriy foydalanuvchi, TOTP 2FA
/// kutilayotgan bosqich) boshqaradi. main.dart'da ChangeNotifierProvider
/// orqali butun widget daraxtiga taqdim etiladi.
class SessionProvider extends ChangeNotifier {
  final ApiClient api;

  AppUser? user;
  bool isLoading = true;
  String? pendingTotpToken;
  String? lastError;
  bool _rememberMeRequested = false;

  SessionProvider(this.api) {
    _restore();
  }

  bool get isAuthenticated => user != null;
  bool get needsTotp => pendingTotpToken != null;

  Future<void> _restore() async {
    final token = await api.storedToken;
    if (token != null && token.isNotEmpty) {
      try {
        final data = await api.call('me');
        user = AppUser.fromJson(data);
        isLoading = false;
        notifyListeners();
        return;
      } catch (_) {
        // Sessiya (10 daqiqa harakatsizlikdan) tugagan bo'lishi mumkin —
        // pastda remember-token orqali jim qayta kirishga urinib ko'ramiz.
        await api.clearToken();
      }
    }

    final rememberToken = await api.storedRememberToken;
    if (rememberToken != null && rememberToken.isNotEmpty) {
      try {
        final data = await api.call('mobileLoginViaRememberToken', {'rememberToken': rememberToken});
        isLoading = false;
        await _completeLogin(data);
        return;
      } catch (_) {
        // Remember-token ham (masalan 1 soatlik mutlaq muddati o'tib)
        // yaroqsiz bo'lsa, foydalanuvchi parolni qayta kiritishi kerak.
        await api.clearRememberToken();
      }
    }

    user = null;
    isLoading = false;
    notifyListeners();
  }

  /// Login qadam 1. Agar foydalanuvchida 2FA yoqilgan bo'lsa, [needsTotp]
  /// true bo'lib qoladi va chaqiruvchi TOTP kod ekraniga o'tishi kerak.
  /// [rememberMe] holati TOTP bosqichi orqali ham (verifyTotp'gacha) saqlanadi.
  Future<void> login(String login, String parol, {bool rememberMe = false}) async {
    _rememberMeRequested = rememberMe;
    final data = await api.call('mobileLogin', {
      'login': login,
      'parol': parol,
      if (rememberMe) 'rememberMe': true,
    });
    if (data['needsTotp'] == true) {
      pendingTotpToken = data['pendingToken'] as String;
      notifyListeners();
      return;
    }
    await _completeLogin(data);
  }

  /// Login qadam 2 (faqat 2FA yoqilgan hisoblar uchun).
  Future<void> verifyTotp(String code) async {
    final data = await api.call('mobileVerifyTotpLogin', {
      'pendingToken': pendingTotpToken,
      'code': code,
      if (_rememberMeRequested) 'rememberMe': true,
    });
    pendingTotpToken = null;
    await _completeLogin(data);
  }

  void cancelTotp() {
    pendingTotpToken = null;
    notifyListeners();
  }

  Future<void> _completeLogin(Map<String, dynamic> data) async {
    final token = data['sessionToken'] as String;
    await api.saveToken(token);
    final rememberToken = data['rememberToken'] as String?;
    if (rememberToken != null && rememberToken.isNotEmpty) {
      await api.saveRememberToken(rememberToken);
    }
    user = AppUser.fromJson(data);
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await api.call('logout');
    } catch (_) {
      // Server bilan bog'lanib bo'lmasa ham, mahalliy tokenni tozalaymiz —
      // foydalanuvchi baribir chiqib ketishi kerak.
    }
    await api.clearToken();
    await api.clearRememberToken();
    user = null;
    notifyListeners();
  }

  /// Profil ma'lumotlari o'zgargandan keyin (masalan rasm yuklangach) qayta yuklaydi.
  Future<void> refreshProfile() async {
    try {
      final data = await api.call('me');
      user = AppUser.fromJson(data);
      notifyListeners();
    } catch (_) {
      // jim o'tkazamiz — chaqiruvchi ekranning o'zi xatolikni ko'rsatadi
    }
  }
}
