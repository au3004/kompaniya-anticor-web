import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/roles.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import '../../widgets/hub_card.dart';
import 'employees_screen.dart';
import 'pending_approvals_screen.dart';
import 'stats_screen.dart';
import 'purchases_screen.dart';
import 'declarations_admin_screen.dart';
import 'error_log_screen.dart';
import 'backups_screen.dart';

class AdminHubScreen extends StatelessWidget {
  const AdminHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<SessionProvider>().user!;
    final rol = user.rol;

    final cards = <Widget>[];

    if (Roles.hrView.contains(rol)) {
      cards.add(HubCard(
        icon: Icons.people_outline,
        title: 'Xodimlar',
        subtitle: "Xodimlar ro'yxati va boshqaruvi",
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EmployeesScreen())),
      ));
    }
    if (Roles.requestApprove.contains(rol) || rol == Roles.hrAdmin) {
      cards.add(HubCard(
        icon: Icons.how_to_reg_outlined,
        title: "Tasdiqlash so'rovlari",
        subtitle: "Yangi rol tayinlash so'rovlari",
        color: AppColors.teal,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PendingApprovalsScreen())),
      ));
    }
    if (Roles.anticorView.contains(rol)) {
      cards.add(HubCard(
        icon: Icons.bar_chart_outlined,
        title: 'Statistika',
        subtitle: "Test/hujjat bo'yicha umumiy ko'rsatkichlar",
        color: AppColors.coral,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const StatsScreen())),
      ));
    }
    if (Roles.purchaseView.contains(rol)) {
      cards.add(HubCard(
        icon: Icons.receipt_long_outlined,
        title: 'Xaridlar reyestri',
        subtitle: 'Shartnomalar ro\'yxati',
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PurchasesScreen())),
      ));
    }
    if (Roles.anticorView.contains(rol)) {
      cards.add(HubCard(
        icon: Icons.fact_check_outlined,
        title: 'Deklaratsiyalar',
        subtitle: "Manfaatlar to'qnashuvi ro'yxati",
        color: AppColors.teal,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const DeclarationsAdminScreen())),
      ));
    }
    if (Roles.anticorManage.contains(rol)) {
      cards.add(HubCard(
        icon: Icons.receipt_long,
        title: 'Tizim jurnali',
        subtitle: 'Server xatoliklari',
        color: AppColors.text,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ErrorLogScreen())),
      ));
      cards.add(HubCard(
        icon: Icons.backup_outlined,
        title: 'Zaxira nusxa',
        subtitle: 'Baza va fayllar zaxirasi',
        color: AppColors.coral,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const BackupsScreen())),
      ));
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Boshqaruv paneli')),
      body: GridView.count(
        padding: const EdgeInsets.all(16),
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.05,
        children: cards,
      ),
    );
  }
}
