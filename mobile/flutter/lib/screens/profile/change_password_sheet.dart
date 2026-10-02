import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class ChangePasswordSheet extends StatefulWidget {
  const ChangePasswordSheet({super.key});

  @override
  State<ChangePasswordSheet> createState() => _ChangePasswordSheetState();
}

class _ChangePasswordSheetState extends State<ChangePasswordSheet> {
  final _formKey = GlobalKey<FormState>();
  final _oldCtrl = TextEditingController();
  final _newCtrl = TextEditingController();
  bool _busy = false;
  String? _error;
  bool _done = false;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('changePassword', {
        'eskiParol': _oldCtrl.text,
        'yangiParol': _newCtrl.text,
      });
      setState(() => _done = true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
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
      child: _done
          ? Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.check_circle_outline, color: AppColors.teal, size: 48),
                const SizedBox(height: 10),
                const Text("Parol muvaffaqiyatli o'zgartirildi", style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 16),
                ElevatedButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Yopish')),
              ],
            )
          : Form(
              key: _formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('Parolni almashtirish', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _oldCtrl,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'Eski parol'),
                    validator: (v) => (v == null || v.isEmpty) ? 'Majburiy' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _newCtrl,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'Yangi parol'),
                    validator: (v) {
                      if (v == null || v.isEmpty) return 'Majburiy';
                      final strong = v.length >= 8 &&
                          RegExp(r'[A-Z]').hasMatch(v) &&
                          RegExp(r'[a-z]').hasMatch(v) &&
                          RegExp(r'[0-9]').hasMatch(v) &&
                          RegExp(r'[^A-Za-z0-9]').hasMatch(v);
                      if (!strong) {
                        return "Kamida 8 belgi, katta/kichik harf, raqam va maxsus belgi";
                      }
                      return null;
                    },
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    Text(_error!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
                  ],
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: _busy ? null : _submit,
                    child: _busy
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Saqlash'),
                  ),
                ],
              ),
            ),
    );
  }
}
