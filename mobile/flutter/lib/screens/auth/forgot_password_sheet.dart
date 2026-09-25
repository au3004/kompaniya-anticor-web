import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../theme.dart';

/// "Parolni tiklash so'rovi" — haqiqiy avtomatik tiklash emas: login/telefon
/// raqamini Yordam navbatiga murojaat sifatida yozib qo'yadi (backend:
/// AuthController::requestPasswordReset). Boshqaruv paneli xodimi buni
/// ko'rib, parolni qo'lda tiklaydi.
class ForgotPasswordSheet extends StatefulWidget {
  const ForgotPasswordSheet({super.key});

  @override
  State<ForgotPasswordSheet> createState() => _ForgotPasswordSheetState();
}

class _ForgotPasswordSheetState extends State<ForgotPasswordSheet> {
  final _formKey = GlobalKey<FormState>();
  final _loginCtrl = TextEditingController();
  final _telefonCtrl = TextEditingController();
  bool _submitting = false;
  bool _sent = false;
  String? _error;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final api = ApiClient();
      await api.call('requestPasswordReset', {
        'login': _loginCtrl.text.trim(),
        'telefon': _telefonCtrl.text.trim(),
      });
      setState(() => _sent = true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: _sent ? _buildSentState() : _buildForm(),
    );
  }

  Widget _buildSentState() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.check_circle_outline, color: AppColors.teal, size: 48),
        const SizedBox(height: 12),
        const Text(
          "So'rovingiz yuborildi",
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
        const SizedBox(height: 6),
        const Text(
          "Boshqaruv xodimi tez orada siz bilan bog'lanadi.",
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.textDim, fontSize: 13),
        ),
        const SizedBox(height: 16),
        ElevatedButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Yopish')),
      ],
    );
  }

  Widget _buildForm() {
    return Form(
      key: _formKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Parolni tiklash', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          const SizedBox(height: 4),
          const Text(
            "Login va telefon raqamingizni kiriting — so'rovingiz boshqaruv xodimiga yuboriladi.",
            style: TextStyle(color: AppColors.textDim, fontSize: 12.5),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _loginCtrl,
            decoration: const InputDecoration(labelText: 'Login'),
            validator: (v) => (v == null || v.trim().isEmpty) ? "Login kiritilishi shart" : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _telefonCtrl,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'Telefon raqami'),
            validator: (v) => (v == null || v.trim().isEmpty) ? "Telefon raqami kiritilishi shart" : null,
          ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(_error!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
          ],
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(
                    width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Yuborish'),
          ),
        ],
      ),
    );
  }
}
