import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_exception.dart';
import '../../services/session_provider.dart';
import '../../theme.dart';

class SurveyScreen extends StatefulWidget {
  const SurveyScreen({super.key});

  @override
  State<SurveyScreen> createState() => _SurveyScreenState();
}

class _SurveyScreenState extends State<SurveyScreen> {
  bool _loading = true;
  bool _active = true;
  String? _error;
  List<dynamic> _questions = [];
  final Map<String, String> _choiceAnswers = {};
  final Map<String, int> _starAnswers = {};
  final Map<String, TextEditingController> _textControllers = {};
  bool _submitting = false;
  bool _done = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _textControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<SessionProvider>().api;
      final data = await api.call('getSurveyQuestions');
      setState(() {
        _active = data['active'] != false;
        _questions = (data['questions'] as List?) ?? [];
        for (final q in _questions) {
          if (q['turi'] == 'matn') {
            _textControllers[q['id'].toString()] = TextEditingController();
          }
        }
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
      final answers = <Map<String, String>>[];
      for (final q in _questions) {
        final id = q['id'].toString();
        String? value;
        if (q['turi'] == 'tanlov') {
          value = _choiceAnswers[id];
        } else if (q['turi'] == 'yulduz') {
          value = _starAnswers[id]?.toString();
        } else {
          value = _textControllers[id]?.text.trim();
        }
        if (value != null && value.isNotEmpty) {
          answers.add({'id': id, 'letter': value});
        }
      }
      await api.call('submitSurveyAnswers', {'answers': answers});
      setState(() => _done = true);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("So'rovnoma")),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.coral)))
              : !_active
                  ? const Center(child: Text("So'rovnoma hozircha faol emas", style: TextStyle(color: AppColors.textDim)))
                  : _done
                      ? _buildDone()
                      : _buildForm(),
    );
  }

  Widget _buildDone() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle_outline, size: 64, color: AppColors.teal),
            const SizedBox(height: 12),
            const Text("Rahmat! Javoblaringiz qabul qilindi.", textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }

  Widget _buildForm() {
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
                      const SizedBox(height: 10),
                      if (q['turi'] == 'tanlov')
                        for (final letter in ['a', 'b', 'c', 'd'])
                          if ((uz[letter] as String?)?.isNotEmpty == true)
                            RadioListTile<String>(
                              dense: true,
                              contentPadding: EdgeInsets.zero,
                              title: Text(uz[letter] as String),
                              value: letter.toUpperCase(),
                              groupValue: _choiceAnswers[id],
                              onChanged: (v) => setState(() => _choiceAnswers[id] = v!),
                            )
                      else if (q['turi'] == 'yulduz')
                        Row(
                          children: List.generate(q['stars'] as int, (idx) {
                            final v = idx + 1;
                            final selected = (_starAnswers[id] ?? 0) >= v;
                            return IconButton(
                              icon: Icon(selected ? Icons.star : Icons.star_border, color: AppColors.coral),
                              onPressed: () => setState(() => _starAnswers[id] = v),
                            );
                          }),
                        )
                      else
                        TextField(
                          controller: _textControllers[id],
                          maxLines: 3,
                          decoration: const InputDecoration(hintText: 'Javobingizni yozing...'),
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
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Text('Yuborish'),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
