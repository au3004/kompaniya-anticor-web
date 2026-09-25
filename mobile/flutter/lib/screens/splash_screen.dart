import 'package:flutter/material.dart';

import '../theme.dart';

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AppColors.azure,
      body: Center(
        child: CircularProgressIndicator(color: Colors.white),
      ),
    );
  }
}
