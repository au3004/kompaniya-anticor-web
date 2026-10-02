import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class ErrorLogScreen extends StatefulWidget {
  const ErrorLogScreen({super.key});

  @override
  State<ErrorLogScreen> createState() => _ErrorLogScreenState();
}

class _ErrorLogScreenState extends State<ErrorLogScreen> {
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
      final data = await api.call('getErrorLog');
      setState(() => _items = (data['entries'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tizim jurnali')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : _items.isEmpty
                  ? const Center(child: Text("Xatoliklar yo'q", style: TextStyle(color: AppColors.textDim)))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, i) {
                          final e = _items[i];
                          return Card(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          (e['action'] as String?) ?? '—',
                                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5, color: AppColors.coral),
                                        ),
                                      ),
                                      Text((e['sana'] as String?) ?? '', style: const TextStyle(fontSize: 11, color: AppColors.textDim)),
                                    ],
                                  ),
                                  const SizedBox(height: 6),
                                  Text((e['message'] as String?) ?? '', style: const TextStyle(fontSize: 12, fontFamily: 'monospace')),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
