import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../models/roles.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import 'employee_edit_sheet.dart';

class EmployeesScreen extends StatefulWidget {
  const EmployeesScreen({super.key});

  @override
  State<EmployeesScreen> createState() => _EmployeesScreenState();
}

class _EmployeesScreenState extends State<EmployeesScreen> {
  List<dynamic> _users = [];
  bool _loading = true;
  String? _error;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      final data = await api.call('getUsersList');
      setState(() => _users = (data['users'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openEdit([Map<String, dynamic>? existing]) async {
    final canEdit = existing == null || Roles.hrEdit.contains(context.read<SessionProvider>().user!.rol);
    if (!canEdit) return;
    final result = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (_) => EmployeeEditSheet(existing: existing),
    );
    if (result == 'ok') {
      _load();
    } else if (result == 'pending' && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Kelishuv (tasdiqlash) uchun yuborildi")),
      );
    }
  }

  Future<void> _delete(Map<String, dynamic> u) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text("O'chirish"),
        content: Text('${u['familiya']} ${u['ism']}ni o\'chirishni tasdiqlaysizmi?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Bekor qilish')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text("O'chirish")),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('deleteEmployee', {'id': u['id']});
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _unlock(Map<String, dynamic> u) async {
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('unlockLogin', {'login': u['login']});
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final rol = context.watch<SessionProvider>().user!.rol;
    final canManage = Roles.hrManage.contains(rol);
    final canEdit = Roles.hrEdit.contains(rol);

    final filtered = _query.isEmpty
        ? _users
        : _users.where((u) {
            final name = '${u['familiya']} ${u['ism']}'.toLowerCase();
            return name.contains(_query.toLowerCase()) || ((u['login'] as String?) ?? '').toLowerCase().contains(_query.toLowerCase());
          }).toList();

    return Scaffold(
      appBar: AppBar(title: const Text('Xodimlar')),
      floatingActionButton: canManage
          ? FloatingActionButton(onPressed: () => _openEdit(), child: const Icon(Icons.add))
          : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: TextField(
                        decoration: const InputDecoration(hintText: 'Qidirish...', prefixIcon: Icon(Icons.search)),
                        onChanged: (v) => setState(() => _query = v),
                      ),
                    ),
                    Expanded(
                      child: RefreshIndicator(
                        onRefresh: _load,
                        child: ListView.separated(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          itemCount: filtered.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                          itemBuilder: (context, i) {
                            final u = filtered[i];
                            final locked = u['locked'] == true;
                            return Card(
                              child: ListTile(
                                onTap: canEdit ? () => _openEdit(u) : null,
                                title: Text('${u['familiya']} ${u['ism']}'),
                                subtitle: Text('${u['lavozim'] ?? '—'} · ${_roleLabel(u['rol'])}', style: const TextStyle(fontSize: 12)),
                                leading: CircleAvatar(
                                  backgroundColor: locked ? AppColors.coral.withOpacity(0.15) : AppColors.azure.withOpacity(0.1),
                                  child: Icon(locked ? Icons.lock_outline : Icons.person_outline, color: locked ? AppColors.coral : AppColors.azure, size: 18),
                                ),
                                trailing: PopupMenuButton<String>(
                                  onSelected: (v) {
                                    if (v == 'edit') _openEdit(u);
                                    if (v == 'delete') _delete(u);
                                    if (v == 'unlock') _unlock(u);
                                  },
                                  itemBuilder: (context) => [
                                    if (canEdit) const PopupMenuItem(value: 'edit', child: Text('Tahrirlash')),
                                    if (locked && canManage) const PopupMenuItem(value: 'unlock', child: Text('Blokdan chiqarish')),
                                    if (canManage) const PopupMenuItem(value: 'delete', child: Text("O'chirish")),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }

  String _roleLabel(dynamic rol) {
    const labels = {
      Roles.user: 'Oddiy xodim',
      Roles.anticorAdmin: 'Anticor - boshqaruvchi',
      Roles.anticor: 'Anticor',
      Roles.hrAdmin: 'HR - boshqaruvchi',
      Roles.hr: 'HR',
      Roles.rahbariyat: 'Rahbariyat',
      Roles.xarid: 'Xarid',
    };
    return labels[rol] ?? (rol?.toString() ?? '—');
  }
}
