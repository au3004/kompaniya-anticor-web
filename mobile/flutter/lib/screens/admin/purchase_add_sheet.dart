import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class PurchaseAddSheet extends StatefulWidget {
  const PurchaseAddSheet({super.key});

  @override
  State<PurchaseAddSheet> createState() => _PurchaseAddSheetState();
}

class _PurchaseAddSheetState extends State<PurchaseAddSheet> {
  final _formKey = GlobalKey<FormState>();
  DateTime? _sana;
  final _raqamiCtrl = TextEditingController();
  final _kontragentCtrl = TextEditingController();
  final _predmetiCtrl = TextEditingController();
  final _summasiCtrl = TextEditingController();
  final _turiCtrl = TextEditingController();
  final _izohCtrl = TextEditingController();
  PlatformFile? _picked;
  bool _busy = false;
  String? _error;

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: ['pdf']);
    if (result != null && result.files.isNotEmpty) {
      setState(() => _picked = result.files.first);
    }
  }

  Future<void> _pickDate() async {
    final d = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(2015),
      lastDate: DateTime(2100),
    );
    if (d != null) setState(() => _sana = d);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_sana == null) {
      setState(() => _error = 'Shartnoma sanasini tanlang');
      return;
    }
    if (_picked?.path == null) {
      setState(() => _error = 'Shartnoma fayli (PDF) yuklanishi shart');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final bytes = await File(_picked!.path!).readAsBytes();
      final dataUrl = ApiClient.toDataUrl(bytes, 'application/pdf');
      final api = context.read<SessionProvider>().api;
      final sanaStr =
          '${_sana!.year.toString().padLeft(4, '0')}-${_sana!.month.toString().padLeft(2, '0')}-${_sana!.day.toString().padLeft(2, '0')}';
      await api.call('addPurchase', {
        'shartnomaSana': sanaStr,
        'shartnomaRaqami': _raqamiCtrl.text.trim(),
        'kontragent': _kontragentCtrl.text.trim(),
        'shartnomaPredmeti': _predmetiCtrl.text.trim(),
        'shartnomaSummasi': _summasiCtrl.text.trim(),
        'xaridTuri': _turiCtrl.text.trim(),
        'izoh': _izohCtrl.text.trim(),
        'file': dataUrl,
        'fileName': _picked!.name,
      });
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.92,
      minChildSize: 0.5,
      maxChildSize: 0.98,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          child: Form(
            key: _formKey,
            child: ListView(
              controller: scrollController,
              padding: const EdgeInsets.all(20),
              children: [
                const Text('Reyestrga kiritish', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                const SizedBox(height: 16),
                InkWell(
                  onTap: _pickDate,
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Shartnoma sanasi *'),
                    child: Text(_sana == null
                        ? 'Tanlanmagan'
                        : '${_sana!.day.toString().padLeft(2, '0')}.${_sana!.month.toString().padLeft(2, '0')}.${_sana!.year}'),
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _raqamiCtrl,
                  decoration: const InputDecoration(labelText: 'Shartnoma raqami *'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _kontragentCtrl,
                  decoration: const InputDecoration(labelText: 'Kontragent *'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _predmetiCtrl,
                  decoration: const InputDecoration(labelText: 'Shartnoma predmeti *'),
                  maxLines: 2,
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _summasiCtrl,
                  decoration: const InputDecoration(labelText: 'Shartnoma summasi *'),
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _turiCtrl,
                  decoration: const InputDecoration(labelText: 'Xarid turi *'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Majburiy' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(controller: _izohCtrl, decoration: const InputDecoration(labelText: 'Izoh'), maxLines: 2),
                const SizedBox(height: 14),
                InkWell(
                  onTap: _pickFile,
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(border: Border.all(color: AppColors.cardBorder), borderRadius: BorderRadius.circular(12)),
                    child: Row(
                      children: [
                        Icon(Icons.picture_as_pdf_outlined, color: _picked == null ? AppColors.textDim : AppColors.coral),
                        const SizedBox(width: 10),
                        Expanded(child: Text(_picked?.name ?? 'Shartnoma fayli (PDF) tanlash *', style: const TextStyle(fontSize: 13))),
                      ],
                    ),
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
                ],
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Saqlash'),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
