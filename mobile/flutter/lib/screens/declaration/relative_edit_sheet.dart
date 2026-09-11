import 'package:flutter/material.dart';

import '../../models/declaration_models.dart';
import '../../theme.dart';

/// declModalFieldsHtml() (main.html) bilan bir xil qoidalar: "Ma'lumot
/// kiritaman" tanlansa F.I.Sh./ish turi majburiy, "tadbirkor" tanlansa
/// yuridik shaxs/STIR/boshqaruvdagi roli ham majburiy.
class RelativeEditSheet extends StatefulWidget {
  final RelativeEntry entry;
  const RelativeEditSheet({super.key, required this.entry});

  @override
  State<RelativeEditSheet> createState() => _RelativeEditSheetState();
}

class _RelativeEditSheetState extends State<RelativeEditSheet> {
  late RelativeData _d;
  final _fullNameCtrl = TextEditingController();
  final _jshshirCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _workplacePositionCtrl = TextEditingController();
  final _legalEntityNameCtrl = TextEditingController();
  final _stirCtrl = TextEditingController();
  final _ownershipShareCtrl = TextEditingController();
  final _managementRoleCtrl = TextEditingController();
  String? _error;

  @override
  void initState() {
    super.initState();
    _d = widget.entry.data?.copy() ?? RelativeData();
    _fullNameCtrl.text = _d.fullName ?? '';
    _jshshirCtrl.text = _d.jshshir ?? '';
    _addressCtrl.text = _d.address ?? '';
    _workplacePositionCtrl.text = _d.workplacePosition ?? '';
    _legalEntityNameCtrl.text = _d.legalEntityName ?? '';
    _stirCtrl.text = _d.stir ?? '';
    _ownershipShareCtrl.text = _d.ownershipShare ?? '';
    _managementRoleCtrl.text = _d.managementRole ?? '';
  }

  @override
  void dispose() {
    for (final c in [
      _fullNameCtrl,
      _jshshirCtrl,
      _addressCtrl,
      _workplacePositionCtrl,
      _legalEntityNameCtrl,
      _stirCtrl,
      _ownershipShareCtrl,
      _managementRoleCtrl,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  void _save() {
    _d.fullName = _fullNameCtrl.text.trim();
    _d.jshshir = _jshshirCtrl.text.trim();
    _d.address = _addressCtrl.text.trim();
    _d.workplacePosition = _workplacePositionCtrl.text.trim();
    _d.legalEntityName = _legalEntityNameCtrl.text.trim();
    _d.stir = _stirCtrl.text.trim();
    _d.ownershipShare = _ownershipShareCtrl.text.trim();
    _d.managementRole = _managementRoleCtrl.text.trim();

    if (_d.holat == null || _d.holat!.isEmpty) {
      setState(() => _error = 'Holatni tanlang');
      return;
    }
    if (_d.holat == 'info') {
      if (_d.fullName == null || _d.fullName!.isEmpty) {
        setState(() => _error = "F.I.Sh.ni to'ldiring");
        return;
      }
      if (_d.workType == null || _d.workType!.isEmpty) {
        setState(() => _error = 'Ish turini tanlang');
        return;
      }
      if (_d.workType == 'other' && (_d.workplacePosition == null || _d.workplacePosition!.isEmpty)) {
        setState(() => _error = 'Ish joyi/lavozimni kiriting');
        return;
      }
      if (_d.workType == 'entrepreneur') {
        if (_d.legalEntityName == null || _d.legalEntityName!.isEmpty) {
          setState(() => _error = 'Yuridik shaxs nomini kiriting');
          return;
        }
        if (_d.stir == null || _d.stir!.isEmpty) {
          setState(() => _error = "STIR'ni kiriting");
          return;
        }
        if (_d.managementRole == null || _d.managementRole!.isEmpty) {
          setState(() => _error = 'Boshqaruvdagi rolini kiriting');
          return;
        }
      }
    }
    Navigator.of(context).pop(_d);
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          child: ListView(
            controller: scrollController,
            padding: const EdgeInsets.all(20),
            children: [
              Text(
                '${widget.entry.label()} — ma\'lumot kiritish',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
              ),
              const SizedBox(height: 16),
              const Text('Holat *', style: TextStyle(fontSize: 12.5, color: AppColors.textDim)),
              const SizedBox(height: 6),
              DropdownButtonFormField<String>(
                value: (_d.holat?.isEmpty ?? true) ? null : _d.holat,
                hint: const Text('— Tanlang —'),
                items: const [
                  DropdownMenuItem(value: 'info', child: Text('Ma\'lumot kiritaman')),
                  DropdownMenuItem(value: 'no_info', child: Text('Ma\'lumotga ega emasman')),
                  DropdownMenuItem(value: 'deceased', child: Text('Vafot etgan')),
                  DropdownMenuItem(value: 'no_contact', child: Text('Ajrashgan / aloqada emas')),
                ],
                onChanged: (v) => setState(() => _d.holat = v),
              ),
              if (_d.holat == 'info') ...[
                const SizedBox(height: 14),
                TextField(controller: _fullNameCtrl, decoration: const InputDecoration(labelText: 'F.I.Sh. *')),
                const SizedBox(height: 12),
                TextField(
                  controller: _jshshirCtrl,
                  maxLength: 14,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'JShShIR (ПИНФЛ)', counterText: ''),
                ),
                const SizedBox(height: 12),
                TextField(controller: _addressCtrl, decoration: const InputDecoration(labelText: 'Manzil')),
                const SizedBox(height: 14),
                const Text('Ish turi *', style: TextStyle(fontSize: 12.5, color: AppColors.textDim)),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Expanded(
                      child: ChoiceChip(
                        label: const Text('Tadbirkor / egadorlik'),
                        selected: _d.workType == 'entrepreneur',
                        onSelected: (_) => setState(() => _d.workType = 'entrepreneur'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ChoiceChip(
                        label: const Text('Boshqa'),
                        selected: _d.workType == 'other',
                        onSelected: (_) => setState(() => _d.workType = 'other'),
                      ),
                    ),
                  ],
                ),
                if (_d.workType == 'other') ...[
                  const SizedBox(height: 12),
                  TextField(
                    controller: _workplacePositionCtrl,
                    decoration: const InputDecoration(labelText: 'Ish joyi / lavozimi *'),
                  ),
                ],
                if (_d.workType == 'entrepreneur') ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: AppColors.bgDeep, borderRadius: BorderRadius.circular(10)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Tadbirkorlik ma\'lumotlari', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
                        const SizedBox(height: 10),
                        TextField(controller: _legalEntityNameCtrl, decoration: const InputDecoration(labelText: 'Yuridik shaxs nomi *')),
                        const SizedBox(height: 10),
                        TextField(
                          controller: _stirCtrl,
                          maxLength: 9,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'STIR *', counterText: ''),
                        ),
                        const SizedBox(height: 10),
                        TextField(controller: _ownershipShareCtrl, decoration: const InputDecoration(labelText: 'Ulush miqdori')),
                        const SizedBox(height: 10),
                        TextField(controller: _managementRoleCtrl, decoration: const InputDecoration(labelText: 'Boshqaruvdagi roli *')),
                      ],
                    ),
                  ),
                ],
              ],
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
              ],
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Bekor qilish')),
                  ),
                  const SizedBox(width: 10),
                  Expanded(child: ElevatedButton(onPressed: _save, child: const Text('Saqlash'))),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
