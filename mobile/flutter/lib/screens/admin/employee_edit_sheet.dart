import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../models/roles.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

const _roleLabels = {
  Roles.user: 'Oddiy xodim',
  Roles.anticorAdmin: 'Anticor - boshqaruvchi',
  Roles.anticor: 'Anticor',
  Roles.hrAdmin: 'HR - boshqaruvchi',
  Roles.hr: 'HR',
  Roles.rahbariyat: 'Rahbariyat',
  Roles.xarid: 'Xarid',
};

/// Yangi xodim qo'shish (existing == null) yoki mavjudini tahrirlash.
/// backend: AdminController::addEmployee / editEmployee — agar hr-admin
/// "user"dan boshqa rol bersa, so'rov kelishuv (pending approval) holatiga
/// tushishi mumkin, shu holat alohida xabar bilan ko'rsatiladi.
class EmployeeEditSheet extends StatefulWidget {
  final Map<String, dynamic>? existing;
  const EmployeeEditSheet({super.key, this.existing});

  @override
  State<EmployeeEditSheet> createState() => _EmployeeEditSheetState();
}

class _EmployeeEditSheetState extends State<EmployeeEditSheet> {
  final _formKey = GlobalKey<FormState>();
  final _loginCtrl = TextEditingController();
  final _parolCtrl = TextEditingController();
  final _familiyaCtrl = TextEditingController();
  final _ismCtrl = TextEditingController();
  final _otasiCtrl = TextEditingController();
  final _lavozimCtrl = TextEditingController();
  final _bolinmaCtrl = TextEditingController();
  final _telefonCtrl = TextEditingController();
  String _rol = Roles.user;
  bool _busy = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    if (e != null) {
      _loginCtrl.text = (e['login'] as String?) ?? '';
      _familiyaCtrl.text = (e['familiya'] as String?) ?? '';
      _ismCtrl.text = (e['ism'] as String?) ?? '';
      _otasiCtrl.text = (e['otasi'] as String?) ?? '';
      _lavozimCtrl.text = (e['lavozim'] as String?) ?? '';
      _bolinmaCtrl.text = (e['bolinma'] as String?) ?? '';
      _telefonCtrl.text = (e['telefon'] as String?) ?? '';
      _rol = (e['rol'] as String?) ?? Roles.user;
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      final params = {
        'familiya': _familiyaCtrl.text.trim(),
        'ism': _ismCtrl.text.trim(),
        'otasi': _otasiCtrl.text.trim(),
        'lavozim': _lavozimCtrl.text.trim(),
        'bolinma': _bolinmaCtrl.text.trim(),
        'telefon': _telefonCtrl.text.trim(),
        'rol': _rol,
        if (_parolCtrl.text.isNotEmpty) 'parol': _parolCtrl.text,
      };
      Map<String, dynamic> data;
      if (_isEdit) {
        data = await api.call('editEmployee', {'id': widget.existing!['id'], ...params});
      } else {
        data = await api.call('addEmployee', {'login': _loginCtrl.text.trim(), ...params});
      }
      if (mounted) {
        Navigator.of(context).pop(data['pending'] == true ? 'pending' : 'ok');
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.9,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          child: Form(
            key: _formKey,
            child: ListView(
              controller: scrollController,
              padding: const EdgeInsets.all(20),
              children: [
                Text(_isEdit ? 'Xodimni tahrirlash' : 'Yangi xodim qo\'shish', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                const SizedBox(height: 16),
                if (!_isEdit) ...[
                  TextFormField(
                    controller: _loginCtrl,
                    decoration: const InputDecoration(labelText: 'Login *'),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                  ),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  controller: _parolCtrl,
                  obscureText: true,
                  decoration: InputDecoration(labelText: _isEdit ? "Yangi parol (ixtiyoriy)" : 'Parol *'),
                  validator: (v) {
                    if (_isEdit) return null;
                    if (v == null || v.isEmpty) return 'Majburiy';
                    return null;
                  },
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _familiyaCtrl,
                  decoration: const InputDecoration(labelText: 'Familiya *'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _ismCtrl,
                  decoration: const InputDecoration(labelText: 'Ism *'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(controller: _otasiCtrl, decoration: const InputDecoration(labelText: 'Otasining ismi')),
                const SizedBox(height: 12),
                TextFormField(controller: _lavozimCtrl, decoration: const InputDecoration(labelText: 'Lavozim')),
                const SizedBox(height: 12),
                TextFormField(controller: _bolinmaCtrl, decoration: const InputDecoration(labelText: "Bo'linma")),
                const SizedBox(height: 12),
                TextFormField(controller: _telefonCtrl, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Telefon')),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: _rol,
                  decoration: const InputDecoration(labelText: 'Rol'),
                  items: _roleLabels.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
                  onChanged: (v) => setState(() => _rol = v ?? Roles.user),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
                ],
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Saqlash'),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
