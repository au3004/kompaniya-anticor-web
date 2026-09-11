import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../models/roles.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import 'declaration_detail_screen.dart';

class DeclarationsAdminScreen extends StatefulWidget {
  const DeclarationsAdminScreen({super.key});

  @override
  State<DeclarationsAdminScreen> createState() => _DeclarationsAdminScreenState();
}

class _DeclarationsAdminScreenState extends State<DeclarationsAdminScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

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
      final data = await api.call('getDeclarations');
      setState(() => _items = (data['declarations'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _open(dynamic d) async {
    try {
      final api = context.read<SessionProvider>().api;
      final data = await api.call('getDeclaration', {'id': d['id']});
      if (mounted) {
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => DeclarationDetailScreen(declaration: data['declaration'] as Map<String, dynamic>)),
        );
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete(dynamic d) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text("O'chirish"),
        content: const Text('Bu deklaratsiyani butunlay o\'chirishni tasdiqlaysizmi?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Bekor qilish')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text("O'chirish")),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('deleteDeclaration', {'id': d['id']});
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final rol = context.watch<SessionProvider>().user!.rol;
    final canDelete = Roles.anticorManage.contains(rol);

    return Scaffold(
      appBar: AppBar(title: const Text('Deklaratsiyalar')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : _items.isEmpty
                  ? const Center(child: Text("Hozircha deklaratsiyalar yo'q", style: TextStyle(color: AppColors.textDim)))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, i) {
                          final d = _items[i];
                          final hasConflict = d['hasConflict'];
                          return Card(
                            child: ListTile(
                              title: Text(d['fullName'] as String? ?? ''),
                              subtitle: Text('${d['lavozim'] ?? '—'} · ${d['submittedAt'] ?? ''}', style: const TextStyle(fontSize: 11.5)),
                              leading: CircleAvatar(
                                backgroundColor: hasConflict == true
                                    ? AppColors.coral.withOpacity(0.15)
                                    : AppColors.teal.withOpacity(0.15),
                                child: Icon(
                                  hasConflict == true ? Icons.warning_amber_outlined : Icons.check_outlined,
                                  color: hasConflict == true ? AppColors.coral : AppColors.teal,
                                  size: 18,
                                ),
                              ),
                              onTap: () => _open(d),
                              trailing: canDelete
                                  ? IconButton(icon: const Icon(Icons.delete_outline, color: AppColors.coral), onPressed: () => _delete(d))
                                  : null,
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
