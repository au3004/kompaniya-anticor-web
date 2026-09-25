import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class SessionsScreen extends StatefulWidget {
  const SessionsScreen({super.key});

  @override
  State<SessionsScreen> createState() => _SessionsScreenState();
}

class _SessionsScreenState extends State<SessionsScreen> {
  List<dynamic> _sessions = [];
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
      final data = await api.call('getMySessions');
      setState(() => _sessions = (data['sessions'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _revoke(String idHash) async {
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('revokeSession', {'idHash': idHash});
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _revokeOthers() async {
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('revokeOtherSessions');
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Faol sessiyalar')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_sessions.where((s) => s['isCurrent'] != true).isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: OutlinedButton.icon(
                            onPressed: _revokeOthers,
                            icon: const Icon(Icons.logout, size: 18),
                            label: const Text("Boshqa qurilmalardan chiqish"),
                          ),
                        ),
                      for (final s in _sessions)
                        Card(
                          margin: const EdgeInsets.only(bottom: 10),
                          child: ListTile(
                            leading: Icon(
                              s['isCurrent'] == true ? Icons.smartphone : Icons.devices_other,
                              color: s['isCurrent'] == true ? AppColors.teal : AppColors.textDim,
                            ),
                            title: Text(s['isCurrent'] == true ? 'Joriy qurilma' : 'Boshqa qurilma'),
                            subtitle: Text('Kirilgan: ${s['createdAt']}\nTugaydi: ${s['expiresAt']}'),
                            isThreeLine: true,
                            trailing: s['isCurrent'] == true
                                ? null
                                : IconButton(
                                    icon: const Icon(Icons.close, color: AppColors.coral),
                                    onPressed: () => _revoke(s['idHash'] as String),
                                  ),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
