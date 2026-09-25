import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class SupportScreen extends StatefulWidget {
  const SupportScreen({super.key});

  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  final _ctrl = TextEditingController();
  bool _submitting = false;
  bool _done = false;
  String? _error;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_ctrl.text.trim().isEmpty) {
      setState(() => _error = 'Murojaat matnini kiriting');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('submitSupport', {'murojaat': _ctrl.text.trim()});
      setState(() => _done = true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Yordam')),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: _done
            ? Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.check_circle_outline, size: 56, color: AppColors.teal),
                    const SizedBox(height: 12),
                    const Text('Murojaatingiz yuborildi', style: TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () => setState(() {
                        _done = false;
                        _ctrl.clear();
                      }),
                      child: const Text('Yana murojaat yuborish'),
                    ),
                  ],
                ),
              )
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Savol yoki murojaatingizni quyida yozing — boshqaruv bo\'limi xodimi tez orada javob beradi.',
                    style: TextStyle(color: AppColors.textDim, fontSize: 13),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: _ctrl,
                    maxLines: 8,
                    maxLength: 4000,
                    decoration: const InputDecoration(hintText: 'Murojaatingizni yozing...', alignLabelWithHint: true),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 4),
                    Text(_error!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
                  ],
                  const SizedBox(height: 10),
                  ElevatedButton(
                    onPressed: _submitting ? null : _submit,
                    child: _submitting
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Yuborish'),
                  ),
                ],
              ),
      ),
    );
  }
}
