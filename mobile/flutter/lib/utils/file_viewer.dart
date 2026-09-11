import 'dart:io';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../theme.dart';

/// Bearer-token bilan himoyalangan server faylini (masalan
/// `document-download.php?id=5`) yuklab, vaqtincha papkaga saqlaydi va
/// qurilmaning standart ko'ruvchisida ochadi (PDF uchun odatda tizim PDF
/// dasturi). Oddiy `url_launcher` bilan ochib bo'lmaydi, chunki bu
/// fayllarga faqat "Authorization: Bearer" sarlavhasi bilan kirish mumkin.
Future<void> openRemoteFile({
  required BuildContext context,
  required ApiClient api,
  required String pathAndQuery,
  required String suggestedFileName,
}) async {
  showDialog(
    context: context,
    barrierDismissible: false,
    builder: (_) => const Center(child: CircularProgressIndicator(color: Colors.white)),
  );
  try {
    final bytes = await api.downloadFile(pathAndQuery);
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/$suggestedFileName');
    await file.writeAsBytes(bytes, flush: true);
    if (context.mounted) Navigator.of(context, rootNavigator: true).pop();
    await OpenFilex.open(file.path);
  } on ApiException catch (e) {
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message), backgroundColor: AppColors.coral));
    }
  } catch (e) {
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text("Faylni ochib bo'lmadi"), backgroundColor: AppColors.coral));
    }
  }
}
