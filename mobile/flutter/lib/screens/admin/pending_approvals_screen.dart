import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class PendingApprovalsScreen extends StatefulWidget {
  const PendingApprovalsScreen({super.key});

  @override
  State<PendingApprovalsScreen> createState() => _PendingApprovalsScreenState();
}

class _PendingApprovalsScreenState extends State<PendingApprovalsScreen> {
  List<dynamic> _requests = [];
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
      final data = await api.call('getPendingEmployeeRequests');
      setState(() => _requests = (data['requests'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _decide(dynamic req, String decision) async {
    try {
      final api = context.read<SessionProvider>().api;
      await api.call('decidePendingEmployeeRequest', {'requestId': req['id'], 'decision': decision});
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("Tasdiqlash so'rovlari")),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : _requests.isEmpty
                  ? const Center(child: Text("Hozircha so'rovlar yo'q", style: TextStyle(color: AppColors.textDim)))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _requests.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, i) {
                          final r = _requests[i];
                          final approvals = (r['approvals'] as List?) ?? [];
                          final isPending = r['status'] == 'pending';
                          return Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          (r['fish'] as String?)?.isNotEmpty == true ? r['fish'] as String : "Noma'lum",
                                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                                        ),
                                      ),
                                      Chip(
                                        label: Text(
                                          r['type'] == 'add' ? 'Yangi' : 'Tahrirlash',
                                          style: const TextStyle(fontSize: 11),
                                        ),
                                        visualDensity: VisualDensity.compact,
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text('Rol: ${r['rol'] ?? '—'} · Yuboruvchi: ${r['requestedByFish'] ?? '—'}', style: const TextStyle(fontSize: 12, color: AppColors.textDim)),
                                  Text('Sana: ${r['sana'] ?? '—'}', style: const TextStyle(fontSize: 11, color: AppColors.textDim)),
                                  if (approvals.isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Wrap(
                                      spacing: 6,
                                      runSpacing: 4,
                                      children: approvals
                                          .map<Widget>((a) => Chip(
                                                label: Text('${a['fish']}: ${a['decision'] == 'approved' ? 'tasdiqladi' : 'rad etdi'}', style: const TextStyle(fontSize: 10.5)),
                                                visualDensity: VisualDensity.compact,
                                                backgroundColor: a['decision'] == 'approved'
                                                    ? AppColors.teal.withOpacity(0.12)
                                                    : AppColors.coral.withOpacity(0.12),
                                              ))
                                          .toList(),
                                    ),
                                  ],
                                  if (isPending) ...[
                                    const SizedBox(height: 10),
                                    Row(
                                      children: [
                                        Expanded(
                                          child: OutlinedButton(
                                            onPressed: () => _decide(r, 'rejected'),
                                            style: OutlinedButton.styleFrom(foregroundColor: AppColors.coral, side: const BorderSide(color: AppColors.coral)),
                                            child: const Text('Rad etish'),
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: ElevatedButton(
                                            onPressed: () => _decide(r, 'approved'),
                                            child: const Text('Tasdiqlash'),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ] else
                                    Padding(
                                      padding: const EdgeInsets.only(top: 8),
                                      child: Text(
                                        r['status'] == 'approved' ? 'Tasdiqlangan' : 'Rad etilgan',
                                        style: TextStyle(
                                          color: r['status'] == 'approved' ? AppColors.teal : AppColors.coral,
                                          fontWeight: FontWeight.w700,
                                          fontSize: 12,
                                        ),
                                      ),
                                    ),
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
