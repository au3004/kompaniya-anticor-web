import 'dart:convert';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../models/declaration_models.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import 'relative_edit_sheet.dart';
import 'declaration_record_screen.dart';

/// "Manfaatlar to'qnashuvi" deklaratsiyasi — veb versiyaning (main.html,
/// declStep*Html funksiyalari) 8 bosqichli wizard'i bilan bir xil oqim:
/// 1) Hujjatlar bilan tanishish  2) Xodim ma'lumotlari  3) Atamalar
/// 4) Yaqin qarindoshlar  5) Savollar  6) Yakuniy tasdiqlash
/// 7) Ko'rib chiqish  8) E-IMZO bilan imzolash (mock, real E-IMZO
/// integratsiyasi hali yo'q — backend ham shuni ko'zda tutadi).
class DeclarationWizardScreen extends StatefulWidget {
  const DeclarationWizardScreen({super.key});

  @override
  State<DeclarationWizardScreen> createState() => _DeclarationWizardScreenState();
}

class _DeclarationWizardScreenState extends State<DeclarationWizardScreen> {
  int _step = 0;
  static const _stepCount = 8;

  bool _check1 = false;
  final _jshshirCtrl = TextEditingController();
  bool _check3 = false;

  List<RelativeEntry> _relatives = [
    RelativeEntry(id: 'ota', fixed: true),
    RelativeEntry(id: 'ona', fixed: true),
  ];
  int _relSeq = 0;

  final List<DeclarationAnswer> _answers = List.generate(kDeclarationQuestions.length, (_) => DeclarationAnswer());

  bool? _hasConflict;
  bool _ack1 = false;
  bool _ack2 = false;

  final _signPasswordCtrl = TextEditingController();
  bool _submitting = false;
  String? _submitError;

  @override
  void dispose() {
    _jshshirCtrl.dispose();
    _signPasswordCtrl.dispose();
    super.dispose();
  }

  void _goTo(int step) => setState(() => _step = step.clamp(0, _stepCount - 1));

  bool _canAdvanceFrom(int step) {
    switch (step) {
      case 0:
        return _check1;
      case 2:
        return _check3;
      case 3:
        return _relatives.isNotEmpty && _relatives.every((r) => r.filled);
      case 4:
        return _answers.take(5).every((a) => a.choice != null);
      case 5:
        return _hasConflict != null && _ack1 && _ack2;
      default:
        return true;
    }
  }

  Future<void> _editRelative(RelativeEntry entry) async {
    final result = await showModalBottomSheet<RelativeData>(
      context: context,
      isScrollControlled: true,
      builder: (_) => RelativeEditSheet(entry: entry),
    );
    if (result != null) {
      setState(() => entry.data = result);
    }
  }

  void _addRelative(RelativeType type) {
    _relSeq++;
    final sameTypeCount = _relatives.where((r) => r.typeKey == type.key).length;
    setState(() {
      _relatives.add(RelativeEntry(
        id: 'rel$_relSeq',
        typeKey: type.key,
        suffixNum: sameTypeCount > 0 ? sameTypeCount + 1 : null,
      ));
    });
  }

  Future<void> _submit() async {
    if (_signPasswordCtrl.text.isEmpty) {
      setState(() => _submitError = 'Parolni kiriting');
      return;
    }
    setState(() {
      _submitting = true;
      _submitError = null;
    });

    final rand = Random();
    String randCode(int len) {
      const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      return List.generate(len, (_) => chars[rand.nextInt(chars.length)]).join();
    }

    final now = DateTime.now();
    final verificationId = 'UT-EIMZO-${randCode(8)}';
    final refId = 'DEK-${now.year}-${randCode(6)}';

    final payload = {
      'relatives': _relatives.map((r) => r.toJson()).toList(),
      'answers': _answers.map((a) => a.toJson()).toList(),
      'confirm': {'hasConflict': _hasConflict, 'ack1': _ack1, 'ack2': _ack2},
      'employeeJshshir': _jshshirCtrl.text.trim(),
    };

    try {
      final api = context.read<SessionProvider>().api;
      await api.call('submitDeclaration', {
        'refId': refId,
        'verificationId': verificationId,
        'hasConflict': _hasConflict == true,
        'payload': jsonEncode(payload),
      });
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(
        builder: (_) => DeclarationRecordScreen(
          refId: refId,
          verificationId: verificationId,
          signedAt: now,
          relatives: _relatives,
          answers: _answers,
          hasConflict: _hasConflict == true,
          employeeJshshir: _jshshirCtrl.text.trim(),
        ),
      ));
    } on ApiException catch (e) {
      setState(() => _submitError = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Deklaratsiya'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: Column(
        children: [
          LinearProgressIndicator(value: (_step + 1) / _stepCount, color: AppColors.azure, backgroundColor: AppColors.bgDeep),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: _buildStep(),
            ),
          ),
          SafeArea(top: false, child: _buildNav()),
        ],
      ),
    );
  }

  Widget _buildStep() {
    switch (_step) {
      case 0:
        return _stepIntro();
      case 1:
        return _stepEmployee();
      case 2:
        return _stepTerms();
      case 3:
        return _stepRelatives();
      case 4:
        return _stepQuestions();
      case 5:
        return _stepConfirm();
      case 6:
        return _stepReview();
      case 7:
        return _stepEsign();
      default:
        return const SizedBox.shrink();
    }
  }

  Widget _title(String t) => Text(t, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.text));
  Widget _sub(String s) => Padding(
        padding: const EdgeInsets.only(top: 6, bottom: 16),
        child: Text(s, style: const TextStyle(color: AppColors.textDim, fontSize: 13)),
      );

  Widget _stepIntro() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title('Qonunchilik va Nizom talablari bilan tanishish'),
        _sub("Deklaratsiyani to'ldirishdan avval quyidagi hujjatlar bilan tanishib chiqing va tanishganingizni tasdiqlang."),
        Card(
          child: ListTile(
            leading: const Icon(Icons.description_outlined, color: AppColors.azure),
            title: const Text("Manfaatlar to'qnashuvi to'g'risidagi Nizom"),
            trailing: TextButton(
              onPressed: () => ScaffoldMessenger.of(context)
                  .showSnackBar(const SnackBar(content: Text("Hujjat tez orada qo'shiladi"))),
              child: const Text('Ochish'),
            ),
          ),
        ),
        const SizedBox(height: 12),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _check1,
          onChanged: (v) => setState(() => _check1 = v ?? false),
          title: const Text(
            "Men yuqoridagi Qonun va «O'zbektelekom» AK Nizomi talablari bilan to'liq tanishdim va ularga rioya etish majburiyatini olaman.",
            style: TextStyle(fontSize: 13),
          ),
        ),
      ],
    );
  }

  Widget _stepEmployee() {
    final user = context.read<SessionProvider>().user!;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title("Xodim ma'lumotlari"),
        _sub('Ma\'lumotlarda xatolik bo\'lsa, Inson resurslarini boshqarish xizmatiga murojaat qiling.'),
        TextField(
          enabled: false,
          decoration: const InputDecoration(labelText: 'F.I.Sh. (to\'liq)'),
          controller: TextEditingController(text: user.fullName),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _jshshirCtrl,
          maxLength: 14,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'JShShIR (ПИНФЛ)', hintText: '14 raqam', counterText: ''),
        ),
        const SizedBox(height: 12),
        TextField(
          enabled: false,
          decoration: const InputDecoration(labelText: "Bo'linma"),
          controller: TextEditingController(text: user.bolinma ?? '—'),
        ),
        const SizedBox(height: 12),
        TextField(
          enabled: false,
          decoration: const InputDecoration(labelText: 'Lavozim'),
          controller: TextEditingController(text: user.lavozim ?? '—'),
        ),
      ],
    );
  }

  Widget _stepTerms() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title('Asosiy atamalar'),
        _sub("Deklaratsiyada qo'llaniladigan atamalar bilan tanishib chiqing — savollarga to'g'ri javob berish uchun muhim."),
        for (final term in kDeclarationTerms)
          Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(term['t']!, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                  const SizedBox(height: 4),
                  Text(term['d']!, style: const TextStyle(fontSize: 12.5, color: AppColors.textDim)),
                ],
              ),
            ),
          ),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _check3,
          onChanged: (v) => setState(() => _check3 = v ?? false),
          title: const Text('Atamalar mazmuni bilan tanishdim.', style: TextStyle(fontSize: 13)),
        ),
      ],
    );
  }

  Widget _stepRelatives() {
    final filled = _relatives.where((r) => r.filled).length;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title("Yaqin qarindoshlar to'g'risida ma'lumot"),
        _sub("Har bir yaqin qarindosh bo'yicha ma'lumot kiritish majburiy. To'ldirilgan: $filled/${_relatives.length}"),
        for (final r in _relatives)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              title: Text(r.label(), style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text(
                r.filled ? (kHolatLabels[r.data?.holat] ?? kHolatLabels['info']!) : 'Ma\'lumot kiritilmagan',
                style: TextStyle(color: r.filled ? AppColors.teal : AppColors.textDim, fontSize: 12.5),
              ),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextButton(onPressed: () => _editRelative(r), child: Text(r.filled ? 'Tahrirlash' : "To'ldirish")),
                  if (!r.fixed)
                    IconButton(
                      icon: const Icon(Icons.close, size: 18, color: AppColors.coral),
                      onPressed: () => setState(() => _relatives.remove(r)),
                    ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 8),
        PopupMenuButton<RelativeType>(
          onSelected: _addRelative,
          itemBuilder: (context) => kRelativeTypes.map((t) => PopupMenuItem(value: t, child: Text(t.label))).toList(),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.azure),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Text(
              'Yana qarindosh qo\'shish (aka-uka, farzand va h.k.)',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.azure, fontWeight: FontWeight.w600, fontSize: 13),
            ),
          ),
        ),
      ],
    );
  }

  Widget _stepQuestions() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title('Deklaratsiya savollari'),
        _sub('Har bir savolga javob bering.'),
        for (int i = 0; i < kDeclarationQuestions.length; i++)
          Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${i + 1}. ${kDeclarationQuestions[i]}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                  if (i < 5) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        ChoiceChip(
                          label: const Text('HA'),
                          selected: _answers[i].choice == 'ha',
                          onSelected: (_) => setState(() => _answers[i].choice = 'ha'),
                        ),
                        const SizedBox(width: 8),
                        ChoiceChip(
                          label: const Text("YO'Q"),
                          selected: _answers[i].choice == 'yoq',
                          onSelected: (_) => setState(() => _answers[i].choice = 'yoq'),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 8),
                  TextField(
                    maxLines: 2,
                    decoration: InputDecoration(hintText: i < 5 ? 'Izoh (ixtiyoriy)' : 'Javobingiz'),
                    controller: TextEditingController(text: _answers[i].note),
                    onChanged: (v) => _answers[i].note = v,
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _stepConfirm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title('Yakuniy tasdiqlash'),
        _sub('Deklaratsiyani imzolashdan oldin quyidagilarni tasdiqlang.'),
        _confirmOption(
          selected: _hasConflict == true,
          title: "Menda manfaatlar to'qnashuviga olib keladigan holatlar MAVJUD",
          subtitle: 'Savollarda «Ha» deb belgilagan holatlaringiz bo\'lsa, ushbu variantni tanlang.',
          onTap: () => setState(() => _hasConflict = true),
        ),
        const SizedBox(height: 8),
        _confirmOption(
          selected: _hasConflict == false,
          title: "Menda manfaatlar to'qnashuviga olib keladigan holatlar MAVJUD EMAS",
          subtitle: "Hech qanday to'qnashuv holati bo'lmasa, ushbu variantni tanlang.",
          onTap: () => setState(() => _hasConflict = false),
        ),
        const SizedBox(height: 12),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _ack1,
          onChanged: (v) => setState(() => _ack1 = v ?? false),
          title: const Text(
            "Ushbu deklaratsiyada aks ettirilgan ma'lumotlar to'liqligi va haqqoniyligini tasdiqlayman hamda ushbu ma'lumotlar tegishli huquq-tartibot organlari tomonidan tekshirilishiga rozilik bildiraman.",
            style: TextStyle(fontSize: 12.5),
          ),
        ),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _ack2,
          onChanged: (v) => setState(() => _ack2 = v ?? false),
          title: const Text(
            "Nizom talablariga binoan, deklaratsiya haqqoniyligiga ta'sir qiladigan yangi holatlar to'g'risida Kompaniyaga darhol xabar berish majburiyatini zimmamga olaman.",
            style: TextStyle(fontSize: 12.5),
          ),
        ),
      ],
    );
  }

  Widget _confirmOption({required bool selected, required String title, required String subtitle, required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? AppColors.azure : AppColors.cardBorder, width: selected ? 1.6 : 1),
          color: selected ? AppColors.azure.withOpacity(0.05) : Colors.white,
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(selected ? Icons.radio_button_checked : Icons.radio_button_off, color: selected ? AppColors.azure : AppColors.textDim, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                  const SizedBox(height: 3),
                  Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textDim)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _stepReview() {
    final user = context.read<SessionProvider>().user!;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title("Ko'rib chiqish"),
        _sub("Yuborishdan oldin ma'lumotlarni tekshiring."),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              children: [
                _reviewRow('F.I.Sh.', user.fullName),
                _reviewRow('JShShIR', _jshshirCtrl.text.trim()),
                _reviewRow("Bo'linma", user.bolinma ?? '—'),
                _reviewRow('Lavozim', user.lavozim ?? '—'),
                _reviewRow(
                  "Manfaatlar to'qnashuvi",
                  _hasConflict == true ? 'MAVJUD' : 'MAVJUD EMAS',
                  valueColor: _hasConflict == true ? AppColors.coral : AppColors.teal,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        const Text('Yaqin qarindoshlar', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        const SizedBox(height: 8),
        for (final r in _relatives)
          Card(
            margin: const EdgeInsets.only(bottom: 6),
            child: ListTile(
              dense: true,
              title: Text(r.label()),
              subtitle: Text(
                r.data?.holat == 'info' ? (r.data?.fullName ?? '—') : (kHolatLabels[r.data?.holat] ?? '—'),
                style: const TextStyle(fontSize: 12),
              ),
            ),
          ),
      ],
    );
  }

  Widget _reviewRow(String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppColors.textDim, fontSize: 13))),
          Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: valueColor ?? AppColors.text)),
        ],
      ),
    );
  }

  Widget _stepEsign() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _title('E-IMZO bilan imzolash'),
        _sub("Deklaratsiyani yakunlash uchun parolingizni tasdiqlang."),
        TextField(
          controller: _signPasswordCtrl,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Parol'),
        ),
        if (_submitError != null) ...[
          const SizedBox(height: 10),
          Text(_submitError!, style: const TextStyle(color: AppColors.coral, fontSize: 13)),
        ],
      ],
    );
  }

  Widget _buildNav() {
    final isLast = _step == _stepCount - 1;
    final canAdvance = _canAdvanceFrom(_step);
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          if (_step > 0)
            Expanded(
              child: OutlinedButton(onPressed: () => _goTo(_step - 1), child: const Text('Ortga')),
            ),
          if (_step > 0) const SizedBox(width: 10),
          Expanded(
            flex: 2,
            child: ElevatedButton(
              onPressed: !canAdvance
                  ? null
                  : isLast
                      ? (_submitting ? null : _submit)
                      : () => _goTo(_step + 1),
              child: _submitting
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(isLast ? 'Imzolash va yuborish' : 'Keyingisi'),
            ),
          ),
        ],
      ),
    );
  }
}
