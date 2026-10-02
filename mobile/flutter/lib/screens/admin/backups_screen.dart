import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import '../../utils/file_viewer.dart';

class BackupsScreen extends StatefulWidget {
  const BackupsScreen({super.key});

  @override
  State<BackupsScreen> createState() => _BackupsScreenState();
}

class _BackupsScreenState extends State<BackupsScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  bool _creating = false;
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
      final data = await api.call('listBackups');
      setState(() => _items = (data['backups'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _create() async {
    setState(() => _creating = true);
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('createBackup');
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _creating = false);
    }
  }

  String _formatSize(dynamic bytes) {
    final b = (bytes as num?) ?? 0;
    if (b > 1024 * 1024) return '${(b / (1024 * 1024)).toStringAsFixed(1)} MB';
    if (b > 1024) return '${(b / 1024).toStringAsFixed(1)} KB';
    return '$b B';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Zaxira nusxalar')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      ElevatedButton.icon(
                        onPressed: _creating ? null : _create,
                        icon: _creating
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.add),
                        label: Text(_creating ? 'Yaratilmoqda...' : 'Yangi zaxira nusxa yaratish'),
                      ),
                      const SizedBox(height: 16),
                      if (_items.isEmpty)
                        const Padding(
                          padding: EdgeInsets.only(top: 20),
                          child: Center(child: Text("Zaxira nusxalar yo'q", style: TextStyle(color: AppColors.textDim))),
                        ),
                      for (final b in _items)
                        Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          child: ListTile(
                            leading: const Icon(Icons.archive_outlined, color: AppColors.azure),
                            title: Text(b['name'] as String? ?? ''),
                            subtitle: Text('${b['createdAt'] ?? ''} · ${_formatSize(b['sizeBytes'])}', style: const TextStyle(fontSize: 11.5)),
                            trailing: const Icon(Icons.download_outlined),
                            onTap: () {
                              final api = context.read<SessionProvider>().api;
                              openRemoteFile(
                                context: context,
                                api: api,
                                pathAndQuery: 'backup-download.php?name=${b['name']}',
                                suggestedFileName: 'backup_${b['name']}.zip',
                              );
                            },
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
