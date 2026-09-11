import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
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
      final data = await api.call('getMyNotifications');
      setState(() => _items = (data['notifications'] as List?) ?? []);
      for (final n in _items) {
        if (n['read'] != true) {
          api.call('markNotificationRead', {'notifId': n['id']}).catchError((_) => <String, dynamic>{});
        }
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Xabarnomalar')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : _items.isEmpty
                  ? const Center(child: Text("Xabarnomalar yo'q", style: TextStyle(color: AppColors.textDim)))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, i) {
                          final n = _items[i];
                          final unread = n['read'] != true;
                          return Card(
                            color: unread ? AppColors.azure.withOpacity(0.04) : null,
                            child: ListTile(
                              leading: Icon(
                                Icons.notifications_outlined,
                                color: unread ? AppColors.azure : AppColors.textDim,
                              ),
                              title: Text(
                                (n['text'] as String?) ?? '',
                                style: TextStyle(fontWeight: unread ? FontWeight.w600 : FontWeight.w400),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
