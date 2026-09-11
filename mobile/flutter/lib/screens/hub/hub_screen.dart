import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/roles.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import '../../widgets/hub_card.dart';
import '../profile/profile_screen.dart';
import '../docs/docs_screen.dart';
import '../test/test_screen.dart';
import '../survey/survey_screen.dart';
import '../support/support_screen.dart';
import '../hr_documents/hr_documents_screen.dart';
import '../notifications/notifications_screen.dart';
import '../declaration/declaration_wizard_screen.dart';
import '../admin/admin_hub_screen.dart';

class HubScreen extends StatefulWidget {
  const HubScreen({super.key});

  @override
  State<HubScreen> createState() => _HubScreenState();
}

class _HubScreenState extends State<HubScreen> {
  int _unreadCount = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadUnread());
  }

  Future<void> _loadUnread() async {
    try {
      final api = context.read<SessionProvider>().api;
      final data = await api.call('getMyNotifications');
      final list = (data['notifications'] as List?) ?? [];
      final unread = list.where((n) => n['read'] != true).length;
      if (mounted) setState(() => _unreadCount = unread);
    } catch (_) {
      // Jim o'tkazamiz — badge ko'rsatilmaydi, hub baribir ishlayveradi.
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<SessionProvider>().user!;
    final canPanel = Roles.anyPanelAccess.contains(user.rol);

    return Scaffold(
      appBar: AppBar(
        title: Text('Assalomu alaykum, ${user.ism}'),
        actions: [
          IconButton(
            icon: Badge(
              isLabelVisible: _unreadCount > 0,
              label: Text('$_unreadCount'),
              child: const Icon(Icons.notifications_outlined),
            ),
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const NotificationsScreen()),
              );
              _loadUnread();
            },
          ),
          IconButton(
            icon: const CircleAvatar(radius: 14, backgroundColor: AppColors.azure, child: Icon(Icons.person, size: 16, color: Colors.white)),
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProfileScreen())),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await context.read<SessionProvider>().refreshProfile();
          await _loadUnread();
        },
        child: GridView.count(
          padding: const EdgeInsets.all(16),
          crossAxisCount: 2,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.05,
          children: [
            HubCard(
              icon: Icons.menu_book_outlined,
              title: 'Hujjatlar',
              subtitle: "Korrupsiyaga qarshi kurash bo'yicha hujjatlar",
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const DocsScreen())),
            ),
            HubCard(
              icon: Icons.quiz_outlined,
              title: 'Test',
              subtitle: 'Bilim darajasini tekshirish testi',
              color: AppColors.teal,
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TestScreen())),
            ),
            HubCard(
              icon: Icons.poll_outlined,
              title: "So'rovnoma",
              subtitle: 'Anonim so\'rovnomada ishtirok eting',
              color: AppColors.coral,
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SurveyScreen())),
            ),
            HubCard(
              icon: Icons.fact_check_outlined,
              title: 'Deklaratsiya',
              subtitle: "Manfaatlar to'qnashuvi deklaratsiyasi",
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const DeclarationWizardScreen())),
            ),
            HubCard(
              icon: Icons.support_agent_outlined,
              title: 'Yordam',
              subtitle: 'Savol yoki murojaatingizni yuboring',
              color: AppColors.teal,
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SupportScreen())),
            ),
            HubCard(
              icon: Icons.description_outlined,
              title: 'HR hujjatlari',
              subtitle: 'Shaxsiy hujjat (ariza va h.k.) yuboring',
              color: AppColors.coral,
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const HrDocumentsScreen())),
            ),
            if (canPanel)
              HubCard(
                icon: Icons.admin_panel_settings_outlined,
                title: 'Boshqaruv paneli',
                subtitle: 'Xodimlar, hisobotlar va boshqalar',
                color: AppColors.text,
                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AdminHubScreen())),
              ),
          ],
        ),
      ),
    );
  }
}
