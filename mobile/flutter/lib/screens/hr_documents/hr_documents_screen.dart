import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

/// Xodim o'zining shaxsiy hujjatini (ariza, ma'lumotnoma va h.k.) Inson
/// resurslari bo'limiga yuboradi. Faqat PDF, maksimal 25MB (backend:
/// HrDocumentController::MAX_BYTES bilan bir xil chegara).
class HrDocumentsScreen extends StatefulWidget {
  const HrDocumentsScreen({super.key});

  @override
  State<HrDocumentsScreen> createState() => _HrDocumentsScreenState();
}

class _HrDocumentsScreenState extends State<HrDocumentsScreen> {
  static const _maxBytes = 25 * 1024 * 1024;

  PlatformFile? _picked;
  bool _submitting = false;
  String? _error;
  bool _done = false;

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: ['pdf']);
    if (result == null || result.files.isEmpty) return;
    final file = result.files.first;
    if (file.size > _maxBytes) {
      setState(() => _error = 'Fayl hajmi juda katta (maksimal 25MB)');
      return;
    }
    setState(() {
      _picked = file;
      _error = null;
    });
  }

  Future<void> _submit() async {
    if (_picked?.path == null) {
      setState(() => _error = 'PDF fayl tanlanishi shart');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final bytes = await File(_picked!.path!).readAsBytes();
      final dataUrl = ApiClient.toDataUrl(bytes, 'application/pdf');
      final api = context.read<SessionProvider>().api;
      await api.call('submitHrDocument', {'file': dataUrl, 'fileName': _picked!.name});
      setState(() => _done = true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('HR hujjatlari')),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: _done
            ? Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.check_circle_outline, size: 56, color: AppColors.teal),
                    const SizedBox(height: 12),
                    const Text('Hujjat yuborildi', style: TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () => setState(() {
                        _done = false;
                        _picked = null;
                      }),
                      child: const Text('Yana hujjat yuborish'),
                    ),
                  ],
                ),
              )
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Shaxsiy hujjatingizni (ariza, ma\'lumotnoma va h.k.) PDF formatida yuboring.',
                    style: TextStyle(color: AppColors.textDim, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  InkWell(
                    onTap: _pickFile,
                    child: Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        border: Border.all(color: AppColors.cardBorder),
                        borderRadius: BorderRadius.circular(14),
                        color: Colors.white,
                      ),
                      child: Column(
                        children: [
                          Icon(
                            _picked == null ? Icons.upload_file_outlined : Icons.picture_as_pdf_outlined,
                            size: 36,
                            color: _picked == null ? AppColors.textDim : AppColors.coral,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _picked?.name ?? 'PDF fayl tanlash uchun bosing',
                            textAlign: TextAlign.center,
                            style: const TextStyle(fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    Text(_error!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
                  ],
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: _submitting ? null : _submit,
                    child: _submitting
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Yuborish'),
                  ),
                ],
              ),
      ),
    );
  }
}
