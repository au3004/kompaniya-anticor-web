import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import '../../utils/file_viewer.dart';

class DocsScreen extends StatefulWidget {
  const DocsScreen({super.key});

  @override
  State<DocsScreen> createState() => _DocsScreenState();
}

class _DocsScreenState extends State<DocsScreen> {
  List<dynamic> _docs = [];
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
      final data = await api.call('getDocuments');
      setState(() => _docs = (data['docs'] as List?) ?? []);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _open(Map doc) async {
    final api = context.read<SessionProvider>().api;
    try {
      await api.call('markDocRead');
    } catch (_) {
      // O'qilgan deb belgilash muvaffaqiyatsiz bo'lsa ham, hujjatni ochishga davom etamiz.
    }
    if (!mounted) return;
    await openRemoteFile(
      context: context,
      api: api,
      pathAndQuery: 'document-download.php?id=${doc['id']}',
      suggestedFileName: 'hujjat_${doc['id']}.pdf',
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Hujjatlar')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : _docs.isEmpty
                  ? const Center(child: Text('Hozircha hujjatlar yo\'q', style: TextStyle(color: AppColors.textDim)))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _docs.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, i) {
                          final d = _docs[i];
                          return Card(
                            child: ListTile(
                              leading: const Icon(Icons.picture_as_pdf_outlined, color: AppColors.coral),
                              title: Text((d['uz'] as String?) ?? ''),
                              trailing: const Icon(Icons.chevron_right),
                              onTap: () => _open(d),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
