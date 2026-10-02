import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../models/declaration_models.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

/// Yuborilgan deklaratsiyaning yakuniy, QR-kodli "imzolangan" yozuv
/// sahifasi — veb versiyadagi declRenderRecord() bilan bir xil ko'rinish
/// va tarkib.
class DeclarationRecordScreen extends StatelessWidget {
  final String refId;
  final String verificationId;
  final DateTime signedAt;
  final List<RelativeEntry> relatives;
  final List<DeclarationAnswer> answers;
  final bool hasConflict;
  final String employeeJshshir;

  const DeclarationRecordScreen({
    super.key,
    required this.refId,
    required this.verificationId,
    required this.signedAt,
    required this.relatives,
    required this.answers,
    required this.hasConflict,
    required this.employeeJshshir,
  });

  @override
  Widget build(BuildContext context) {
    final user = context.read<SessionProvider>().user!;
    final dateFmt = DateFormat('dd.MM.yyyy · HH:mm');

    return Scaffold(
      appBar: AppBar(
        title: Text(refId),
        automaticallyImplyLeading: false,
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
            child: const Text('Yopish'),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: AppColors.teal.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
            child: Row(
              children: [
                const Icon(Icons.verified_outlined, color: AppColors.teal),
                const SizedBox(width: 10),
                const Expanded(child: Text('Deklaratsiya muvaffaqiyatli yuborildi', style: TextStyle(fontWeight: FontWeight.w700))),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _card([
            _row('F.I.Sh.', user.fullName),
            _row('JShShIR', employeeJshshir.isEmpty ? '—' : employeeJshshir),
            _row("Bo'linma", user.bolinma ?? '—'),
            _row('Lavozim', user.lavozim ?? '—'),
            _row("To'ldirilgan sana", DateFormat('dd.MM.yyyy').format(signedAt)),
            _row('Status', 'Yuborilgan', valueColor: AppColors.teal),
          ]),
          const SizedBox(height: 16),
          const Text('Yaqin qarindoshlar', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          const SizedBox(height: 8),
          for (final r in relatives)
            Card(
              margin: const EdgeInsets.only(bottom: 6),
              child: ListTile(
                dense: true,
                title: Text(r.label(), style: const TextStyle(fontWeight: FontWeight.w600)),
                subtitle: Text(
                  r.data?.holat == 'info'
                      ? '${r.data?.fullName ?? '—'} · ${r.data?.workplaceText() ?? '—'}'
                      : (kHolatLabels[r.data?.holat] ?? '—'),
                  style: const TextStyle(fontSize: 12),
                ),
              ),
            ),
          const SizedBox(height: 16),
          const Text('Savollar va javoblar', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          const SizedBox(height: 8),
          for (int i = 0; i < kDeclarationQuestions.length; i++)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${i + 1}. ${kDeclarationQuestions[i]}', style: const TextStyle(fontSize: 12.5)),
                    if (i < 5 && answers[i].choice != null) ...[
                      const SizedBox(height: 6),
                      Chip(
                        label: Text(answers[i].choice == 'ha' ? 'HA' : "YO'Q"),
                        backgroundColor: answers[i].choice == 'ha'
                            ? AppColors.coral.withOpacity(0.15)
                            : AppColors.teal.withOpacity(0.15),
                        labelStyle: TextStyle(
                          color: answers[i].choice == 'ha' ? AppColors.coral : AppColors.teal,
                          fontWeight: FontWeight.w700,
                          fontSize: 11,
                        ),
                        visualDensity: VisualDensity.compact,
                      ),
                    ],
                    if (answers[i].note.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(answers[i].note, style: const TextStyle(fontSize: 12, color: AppColors.textDim)),
                    ],
                  ],
                ),
              ),
            ),
          const SizedBox(height: 4),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: hasConflict ? AppColors.coral.withOpacity(0.1) : AppColors.teal.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              hasConflict
                  ? "Manfaatlar to'qnashuviga olib keladigan holatlar — MAVJUD"
                  : "Manfaatlar to'qnashuviga olib keladigan holatlar — MAVJUD EMAS",
              style: TextStyle(
                color: hasConflict ? AppColors.coral : AppColors.teal,
                fontWeight: FontWeight.w700,
                fontSize: 12.5,
              ),
            ),
          ),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.teal.withOpacity(0.05),
              border: Border.all(color: AppColors.teal.withOpacity(0.25)),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.verified, color: AppColors.teal, size: 20),
                    SizedBox(width: 8),
                    Text('Elektron imzo (E-IMZO)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                  ],
                ),
                const SizedBox(height: 12),
                _row('Imzolovchi F.I.Sh.', user.fullName),
                _row('Imzolangan sana', dateFmt.format(signedAt)),
                _row('Verification ID', verificationId, monospace: true),
                const SizedBox(height: 10),
                const Center(child: Icon(Icons.qr_code_2, size: 96, color: AppColors.azure)),
                const SizedBox(height: 6),
                const Center(
                  child: Text('QR orqali imzoni tekshirish', style: TextStyle(fontSize: 11, color: AppColors.textDim)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _card(List<Widget> children) => Card(child: Padding(padding: const EdgeInsets.all(6), child: Column(children: children)));

  Widget _row(String label, String value, {Color? valueColor, bool monospace = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppColors.textDim, fontSize: 12.5))),
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 12.5,
              color: valueColor ?? AppColors.text,
              fontFamily: monospace ? 'monospace' : null,
            ),
          ),
        ],
      ),
    );
  }
}
