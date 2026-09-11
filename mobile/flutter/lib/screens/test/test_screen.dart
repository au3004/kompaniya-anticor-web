import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class TestScreen extends StatefulWidget {
  const TestScreen({super.key});

  @override
  State<TestScreen> createState() => _TestScreenState();
}

class _TestScreenState extends State<TestScreen> {
  bool _loading = true;
  bool _active = true;
  String? _error;
  List<dynamic> _questions = [];
  final Map<String, String> _answers = {}; // questionId -> letter
  bool _submitting = false;
  Map<String, dynamic>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      final data = await api.call('getTestQuestions');
      setState(() {
        _active = data['active'] != false;
        _questions = (data['questions'] as List?) ?? [];
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      final api = context.read<SessionProvider>().api;
      final answers = _answers.entries.map((e) => {'id': e.key, 'letter': e.value}).toList();
      final data = await api.call('submitTest', {'answers': answers});
      setState(() => _result = data);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Test')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : !_active
                  ? const Center(child: Text('Test hozircha faol emas', style: TextStyle(color: AppColors.textDim)))
                  : _result != null
                      ? _buildResult()
                      : _buildQuiz(),
    );
  }

  Widget _buildResult() {
    final passed = _result!['passed'] == true;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              passed ? Icons.check_circle_outline : Icons.cancel_outlined,
              size: 64,
              color: passed ? AppColors.teal : AppColors.coral,
            ),
            const SizedBox(height: 12),
            Text(
              passed ? "Muvaffaqiyatli topshirildi" : "Muvaffaqiyatsiz",
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(
              '${_result!['percent']}% (${_result!['points']}/${_result!['maxPoints']})',
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.azure),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuiz() {
    final allAnswered = _answers.length == _questions.length && _questions.isNotEmpty;
    return Column(
      children: [
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: _questions.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final q = _questions[i];
              final id = q['id'].toString();
              final uz = q['uz'] as Map;
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${i + 1}. ${uz['savol']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 8),
                      for (final letter in ['a', 'b', 'c', 'd'])
                        if ((uz[letter] as String?)?.isNotEmpty == true)
                          RadioListTile<String>(
                            dense: true,
                            contentPadding: EdgeInsets.zero,
                            title: Text(uz[letter] as String),
                            value: letter.toUpperCase(),
                            groupValue: _answers[id],
                            onChanged: (v) => setState(() => _answers[id] = v!),
                          ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: (allAnswered && !_submitting) ? _submit : null,
                child: _submitting
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : Text(allAnswered ? 'Yakunlash' : 'Barcha savollarga javob bering (${_answers.length}/${_questions.length})'),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
