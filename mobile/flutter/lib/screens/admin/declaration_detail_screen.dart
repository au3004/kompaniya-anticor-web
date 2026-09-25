import 'package:flutter/material.dart';

import '../../models/declaration_models.dart';
import '../../theme.dart';

/// Boshqaruv panelidan bitta deklaratsiyaning to'liq ma'lumotini ko'rish.
class DeclarationDetailScreen extends StatelessWidget {
  final Map<String, dynamic> declaration;
  const DeclarationDetailScreen({super.key, required this.declaration});

  @override
  Widget build(BuildContext context) {
    final payload = (declaration['payload'] as Map?) ?? {};
    final relatives = (payload['relatives'] as List?) ?? [];
    final answers = (payload['answers'] as List?) ?? [];
    final confirm = (payload['confirm'] as Map?) ?? {};
    final hasConflict = confirm['hasConflict'] == true;

    return Scaffold(
      appBar: AppBar(title: Text((declaration['refId'] as String?) ?? 'Deklaratsiya')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(6),
              child: Column(
                children: [
                  _row('F.I.Sh.', declaration['fullName'] as String? ?? '—'),
                  _row('JShShIR', payload['employeeJshshir'] as String? ?? '—'),
                  _row('Lavozim', declaration['lavozim'] as String? ?? '—'),
                  _row("Bo'linma", declaration['bolinma'] as String? ?? '—'),
                  _row('Telefon', declaration['telefon'] as String? ?? '—'),
                  _row('Yuborilgan sana', declaration['submittedAt'] as String? ?? '—'),
                  _row('Verification ID', declaration['verificationId'] as String? ?? '—', monospace: true),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: hasConflict ? AppColors.coral.withOpacity(0.1) : AppColors.teal.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              hasConflict ? "Manfaatlar to'qnashuvi — MAVJUD" : "Manfaatlar to'qnashuvi — MAVJUD EMAS",
              style: TextStyle(color: hasConflict ? AppColors.coral : AppColors.teal, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(height: 20),
          const Text('Yaqin qarindoshlar', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          const SizedBox(height: 8),
          for (final r in relatives)
            Card(
              margin: const EdgeInsets.only(bottom: 6),
              child: ListTile(
                dense: true,
                title: Text((r['label'] as String?) ?? ''),
                subtitle: Text(
                  (r['data'] != null && r['data']['holat'] == 'info')
                      ? (r['data']['fullName'] as String? ?? '—')
                      : (kHolatLabels[r['data']?['holat']] ?? '—'),
                  style: const TextStyle(fontSize: 12),
                ),
              ),
            ),
          const SizedBox(height: 20),
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
                    if (i < answers.length && answers[i]?['choice'] != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        answers[i]['choice'] == 'ha' ? 'HA' : "YO'Q",
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 11,
                          color: answers[i]['choice'] == 'ha' ? AppColors.coral : AppColors.teal,
                        ),
                      ),
                    ],
                    if (i < answers.length && (answers[i]?['note'] as String?)?.isNotEmpty == true) ...[
                      const SizedBox(height: 4),
                      Text(answers[i]['note'] as String, style: const TextStyle(fontSize: 12, color: AppColors.textDim)),
                    ],
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _row(String label, String value, {bool monospace = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppColors.textDim, fontSize: 12.5))),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5, fontFamily: monospace ? 'monospace' : null),
            ),
          ),
        ],
      ),
    );
  }
}
