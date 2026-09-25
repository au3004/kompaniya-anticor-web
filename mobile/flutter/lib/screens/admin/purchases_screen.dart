import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../models/roles.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import '../../utils/file_viewer.dart';
import 'purchase_add_sheet.dart';

class PurchasesScreen extends StatefulWidget {
  const PurchasesScreen({super.key});

  @override
  State<PurchasesScreen> createState() => _PurchasesScreenState();
}

class _PurchasesScreenState extends State<PurchasesScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;
  final _currencyFmt = NumberFormat.decimalPattern('uz');

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
      final data = await api.call('getPurchases');
      setState(() => _items = (data['purchases'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _addNew() async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const PurchaseAddSheet(),
    );
    if (result == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    final rol = context.watch<SessionProvider>().user!.rol;
    final canEntry = Roles.purchaseEntry.contains(rol);

    return Scaffold(
      appBar: AppBar(title: const Text('Xaridlar reyestri')),
      floatingActionButton: canEntry ? FloatingActionButton(onPressed: _addNew, child: const Icon(Icons.add)) : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : _items.isEmpty
                  ? const Center(child: Text("Hozircha yozuvlar yo'q", style: TextStyle(color: AppColors.textDim)))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, i) {
                          final p = _items[i];
                          return Card(
                            child: ListTile(
                              title: Text(p['kontragent'] as String? ?? ''),
                              subtitle: Text(
                                '${p['shartnomaRaqami'] ?? ''} · ${p['shartnomaSana'] ?? ''}\n${_currencyFmt.format(p['shartnomaSummasi'] ?? 0)} so\'m',
                                style: const TextStyle(fontSize: 11.5),
                              ),
                              isThreeLine: true,
                              trailing: const Icon(Icons.picture_as_pdf_outlined, color: AppColors.coral),
                              onTap: () {
                                final api = context.read<SessionProvider>().api;
                                openRemoteFile(
                                  context: context,
                                  api: api,
                                  pathAndQuery: 'purchase-download.php?id=${p['id']}',
                                  suggestedFileName: 'shartnoma_${p['id']}.pdf',
                                );
                              },
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
