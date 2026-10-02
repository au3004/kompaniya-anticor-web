import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

/// Ikki bosqichli tasdiqlash (TOTP) sozlamalari. Backend hozircha QR-kod
/// xizmatiga bog'liq bo'lmagan, "qo'lda kiritish" (manual entry) yo'lini
/// qo'llab-quvvatlaydi — xuddi veb versiyasidagi kabi.
class TotpSetupScreen extends StatefulWidget {
  const TotpSetupScreen({super.key});

  @override
  State<TotpSetupScreen> createState() => _TotpSetupScreenState();
}

enum _Step { loading, disabled, settingUp, enabled, disabling }

class _TotpSetupScreenState extends State<TotpSetupScreen> {
  _Step _step = _Step.loading;
  String? _secret;
  String? _account;
  final _codeCtrl = TextEditingController();
  final _parolCtrl = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadStatus();
  }

  @override
  void dispose() {
    _codeCtrl.dispose();
    _parolCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadStatus() async {
    try {
      final api = context.read<SessionProvider>().api;
      final data = await api.call('totpStatus');
      setState(() => _step = data['enabled'] == true ? _Step.enabled : _Step.disabled);
    } on ApiException catch (e) {
      setState(() {
        _step = _Step.disabled;
        _error = e.message;
      });
    }
  }

  Future<void> _startSetup() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      final data = await api.call('totpSetupStart');
      setState(() {
        _secret = data['secret'] as String;
        _account = data['account'] as String;
        _step = _Step.settingUp;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirmSetup() async {
    if (_codeCtrl.text.trim().length != 6) {
      setState(() => _error = "6 xonali kodni to'liq kiriting");
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('totpSetupConfirm', {'secret': _secret, 'code': _codeCtrl.text.trim()});
      setState(() => _step = _Step.enabled);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _disable() async {
    if (_parolCtrl.text.isEmpty) {
      setState(() => _error = 'Parolni kiriting');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('totpDisable', {'parol': _parolCtrl.text});
      _parolCtrl.clear();
      setState(() => _step = _Step.disabled);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Ikki bosqichli tasdiqlash')),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    switch (_step) {
      case _Step.loading:
        return const Center(child: CircularProgressIndicator());
      case _Step.disabled:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Icon(Icons.shield_outlined, size: 48, color: AppColors.textDim),
            const SizedBox(height: 12),
            const Text('2FA hozircha o\'chirilgan', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            const SizedBox(height: 6),
            const Text(
              "Yoqilsa, har kirishda parol ustiga autentifikator ilovasidagi 6 xonali kod ham so'raladi.",
              style: TextStyle(color: AppColors.textDim, fontSize: 13),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: AppColors.coral)),
            ],
            const SizedBox(height: 20),
            ElevatedButton(onPressed: _busy ? null : _startSetup, child: const Text('Yoqish')),
          ],
        );
      case _Step.settingUp:
        return SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('1-qadam', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              const Text(
                "Quyidagi kalitni autentifikator ilovangizga (Google Authenticator, Authy va h.k.) qo'lda kiriting (\"Kalitni qo'lda kiritish\" variantidan foydalaning):",
                style: TextStyle(color: AppColors.textDim, fontSize: 13),
              ),
              const SizedBox(height: 10),
              GestureDetector(
                onTap: () {
                  Clipboard.setData(ClipboardData(text: _secret ?? ''));
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Nusxalandi')));
                },
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: AppColors.bgDeep, borderRadius: BorderRadius.circular(10)),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          _secret ?? '',
                          style: const TextStyle(fontFamily: 'monospace', fontSize: 16, letterSpacing: 1.5),
                        ),
                      ),
                      const Icon(Icons.copy, size: 18, color: AppColors.textDim),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 4),
              Text('Hisob: ${_account ?? ''}', style: const TextStyle(color: AppColors.textDim, fontSize: 12)),
              const SizedBox(height: 20),
              const Text('2-qadam', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              const Text('Ilova ko\'rsatgan 6 xonali kodni kiriting:', style: TextStyle(color: AppColors.textDim, fontSize: 13)),
              const SizedBox(height: 10),
              TextField(
                controller: _codeCtrl,
                keyboardType: TextInputType.number,
                maxLength: 6,
                textAlign: TextAlign.center,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                style: const TextStyle(fontSize: 22, letterSpacing: 8),
                decoration: const InputDecoration(counterText: ''),
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(_error!, style: const TextStyle(color: AppColors.coral)),
              ],
              const SizedBox(height: 16),
              ElevatedButton(onPressed: _busy ? null : _confirmSetup, child: const Text('Tasdiqlash va yoqish')),
            ],
          ),
        );
      case _Step.enabled:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Icon(Icons.verified_user_outlined, size: 48, color: AppColors.teal),
            const SizedBox(height: 12),
            const Text('2FA yoqilgan', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            const SizedBox(height: 20),
            const Text("O'chirish uchun parolingizni tasdiqlang:", style: TextStyle(color: AppColors.textDim, fontSize: 13)),
            const SizedBox(height: 10),
            TextField(
              controller: _parolCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Parol'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: const TextStyle(color: AppColors.coral)),
            ],
            const SizedBox(height: 16),
            OutlinedButton(onPressed: _busy ? null : _disable, child: const Text("2FA'ni o'chirish")),
          ],
        );
      case _Step.disabling:
        return const Center(child: CircularProgressIndicator());
    }
  }
}

