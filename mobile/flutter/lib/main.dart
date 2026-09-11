import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'api/api_client.dart';
import 'services/session_provider.dart';
import 'theme.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/totp_screen.dart';
import 'screens/hub/hub_screen.dart';
import 'screens/splash_screen.dart';

void main() {
  runApp(const KompaniyaAnticorApp());
}

class KompaniyaAnticorApp extends StatelessWidget {
  const KompaniyaAnticorApp({super.key});

  @override
  Widget build(BuildContext context) {
    final api = ApiClient();
    return ChangeNotifierProvider(
      create: (_) => SessionProvider(api),
      child: MaterialApp(
        title: 'Korrupsiyaga qarshi kurashish',
        debugShowCheckedModeBanner: false,
        theme: buildAppTheme(),
        home: const _RootRouter(),
      ),
    );
  }
}

/// Sessiya holatiga qarab tegishli ildiz ekranni ko'rsatadi:
/// yuklanmoqda -> splash, 2FA kutilmoqda -> TOTP ekrani,
/// kirilmagan -> login, kirilgan -> Hub.
class _RootRouter extends StatelessWidget {
  const _RootRouter();

  @override
  Widget build(BuildContext context) {
    return Consumer<SessionProvider>(
      builder: (context, session, _) {
        if (session.isLoading) return const SplashScreen();
        if (session.needsTotp) return const TotpScreen();
        if (session.isAuthenticated) return const HubScreen();
        return const LoginScreen();
      },
    );
  }
}
