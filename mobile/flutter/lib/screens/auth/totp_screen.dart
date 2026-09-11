import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class TotpScreen extends StatefulWidget {
  const TotpScreen({super.key});

  @override
  State<TotpScreen> createState() => _TotpScreenState();
}

class _TotpScreenState extends State<TotpScreen> {
  final _codeCtrl = TextEditingController();
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _codeCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final code = _codeCtrl.text.trim();
    if (code.length != 6) {
      setState(() => _error = "6 xonali kodni to'liq kiriting");
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await context.read<SessionProvider>().verifyTotp(code);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Kutilmagan xatolik yuz berdi');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.read<SessionProvider>().cancelTotp(),
        ),
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    width: 64,
                    height: 64,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: AppColors.teal, borderRadius: BorderRadius.circular(18)),
                    child: const Icon(Icons.phonelink_lock_outlined, color: Colors.white, size: 32),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Ikki bosqichli tasdiqlash',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.text),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    "Autentifikator ilovangizdagi 6 xonali kodni kiriting",
                    style: TextStyle(fontSize: 13, color: AppColors.textDim),
                  ),
                  const SizedBox(height: 28),
                  TextField(
                    controller: _codeCtrl,
                    keyboardType: TextInputType.number,
                    textAlign: TextAlign.center,
                    maxLength: 6,
                    autofocus: true,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    style: const TextStyle(fontSize: 28, letterSpacing: 10, fontWeight: FontWeight.w700),
                    decoration: const InputDecoration(counterText: '', hintText: '••••••'),
                    onSubmitted: (_) => _submit(),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 6),
                    Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
                  ],
                  const SizedBox(height: 18),
                  ElevatedButton(
                    onPressed: _submitting ? null : _submit,
                    child: _submitting
                        ? const SizedBox(
                            width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Tasdiqlash'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
