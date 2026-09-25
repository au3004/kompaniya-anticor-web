import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class StatsScreen extends StatefulWidget {
  const StatsScreen({super.key});

  @override
  State<StatsScreen> createState() => _StatsScreenState();
}

class _StatsScreenState extends State<StatsScreen> {
  Map<String, dynamic>? _summary;
  List<dynamic> _employees = [];
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
      final data = await api.call('getStats');
      setState(() {
        _summary = data['summary'] as Map<String, dynamic>?;
        _employees = (data['employees'] as List?) ?? [];
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Statistika')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_summary != null)
                        Row(
                          children: [
                            _statTile('Jami', _summary!['total'], AppColors.azure),
                            const SizedBox(width: 8),
                            _statTile('Hujjat', _summary!['docsDone'], AppColors.teal),
                          ],
                        ),
                      if (_summary != null) const SizedBox(height: 8),
                      if (_summary != null)
                        Row(
                          children: [
                            _statTile("O'tdi", _summary!['testsPassed'], AppColors.teal),
                            const SizedBox(width: 8),
                            _statTile('Yiqildi', _summary!['testsFailed'], AppColors.coral),
                          ],
                        ),
                      const SizedBox(height: 20),
                      const Text('Xodimlar', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                      const SizedBox(height: 8),
                      for (final e in _employees)
                        Card(
                          margin: const EdgeInsets.only(bottom: 6),
                          child: ListTile(
                            dense: true,
                            title: Text((e['fish'] as String?) ?? ''),
                            subtitle: Text('${e['lavozim'] ?? '—'} · ${e['bolinma'] ?? '—'}', style: const TextStyle(fontSize: 11.5)),
                            trailing: Wrap(
                              spacing: 6,
                              children: [
                                Icon(Icons.menu_book, size: 16, color: e['hujjatSana'] != null ? AppColors.teal : AppColors.bgDeep),
                                Icon(
                                  Icons.quiz,
                                  size: 16,
                                  color: e['testTaken'] == true ? (e['passed'] == true ? AppColors.teal : AppColors.coral) : AppColors.bgDeep,
                                ),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }

  Widget _statTile(String label, dynamic value, Color color) {
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('$value', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: color)),
              const SizedBox(height: 2),
              Text(label, style: const TextStyle(fontSize: 12, color: AppColors.textDim)),
            ],
          ),
        ),
      ),
    );
  }
}
