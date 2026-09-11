import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';
import 'sessions_screen.dart';
import 'totp_setup_screen.dart';
import 'change_password_sheet.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _uploadingPhoto = false;

  Future<void> _pickPhoto() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.gallery, maxWidth: 1200, imageQuality: 85);
    if (picked == null) return;

    setState(() => _uploadingPhoto = true);
    try {
      final bytes = await picked.readAsBytes();
      final ext = picked.path.toLowerCase().endsWith('.png') ? 'png' : 'jpeg';
      final dataUrl = ApiClient.toDataUrl(bytes, 'image/$ext');
      final api = context.read<SessionProvider>().api;
      await api.call('updateProfilePhoto', {'rasm': dataUrl});
      if (mounted) await context.read<SessionProvider>().refreshProfile();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingPhoto = false);
    }
  }

  Future<void> _confirmLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Chiqish'),
        content: const Text('Tizimdan chiqishni tasdiqlaysizmi?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Bekor qilish')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Chiqish')),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await context.read<SessionProvider>().logout();
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<SessionProvider>().user!;

    return Scaffold(
      appBar: AppBar(title: const Text('Mening ma\'lumotlarim')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Center(
            child: Stack(
              children: [
                CircleAvatar(
                  radius: 48,
                  backgroundColor: AppColors.bgDeep,
                  backgroundImage: (user.rasm != null && user.rasm!.isNotEmpty) ? NetworkImage(user.rasm!) : null,
                  child: (user.rasm == null || user.rasm!.isEmpty)
                      ? Text(
                          user.ism.isNotEmpty ? user.ism[0].toUpperCase() : '?',
                          style: const TextStyle(fontSize: 32, color: AppColors.azure, fontWeight: FontWeight.w700),
                        )
                      : null,
                ),
                Positioned(
                  right: 0,
                  bottom: 0,
                  child: GestureDetector(
                    onTap: _uploadingPhoto ? null : _pickPhoto,
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: const BoxDecoration(color: AppColors.azure, shape: BoxShape.circle),
                      alignment: Alignment.center,
                      child: _uploadingPhoto
                          ? const SizedBox(
                              width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.camera_alt_outlined, size: 16, color: Colors.white),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Center(
            child: Text(user.fullName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
          ),
          if (user.lavozim != null && user.lavozim!.isNotEmpty)
            Center(child: Text(user.lavozim!, style: const TextStyle(color: AppColors.textDim, fontSize: 13))),
          const SizedBox(height: 24),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(4),
              child: Column(
                children: [
                  _infoRow("Bo'linma", user.bolinma),
                  _infoRow('Telefon', user.telefon),
                  _infoRow("Tug'ilgan sana", user.tugilganSana),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.devices_outlined),
                  title: const Text('Faol sessiyalar'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SessionsScreen())),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.shield_outlined),
                  title: const Text('Ikki bosqichli tasdiqlash'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TotpSetupScreen())),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.lock_reset_outlined),
                  title: const Text('Parolni almashtirish'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => showModalBottomSheet(
                    context: context,
                    isScrollControlled: true,
                    builder: (_) => const ChangePasswordSheet(),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          OutlinedButton.icon(
            onPressed: _confirmLogout,
            icon: const Icon(Icons.logout, color: AppColors.coral),
            label: const Text('Chiqish', style: TextStyle(color: AppColors.coral)),
            style: OutlinedButton.styleFrom(side: const BorderSide(color: AppColors.coral)),
          ),
        ],
      ),
    );
  }

  Widget _infoRow(String label, String? value) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          Text(label, style: const TextStyle(color: AppColors.textDim, fontSize: 13)),
          const Spacer(),
          Text(
            (value == null || value.isEmpty) ? '—' : value,
            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
          ),
        ],
      ),
    );
  }
}
