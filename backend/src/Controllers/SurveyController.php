<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Validate;

final class SurveyController
{
    private static function isActive(): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'survey_active' LIMIT 1");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? true : $value === 'true';
    }

    public static function getQuestions(array $input): void
    {
        $db = Database::connection();
        $rows = $db->query('SELECT * FROM survey_questions ORDER BY id ASC')->fetchAll();

        $questions = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'turi' => $r['turi'],
            'stars' => (int) $r['yulduzlar_soni'],
            'uz' => [
                'savol' => $r['savol'], 'a' => $r['variant_a'], 'b' => $r['variant_b'],
                'c' => $r['variant_c'], 'd' => $r['variant_d'],
            ],
            'ru' => [
                'savol' => $r['savol_ru'], 'a' => $r['variant_a_ru'], 'b' => $r['variant_b_ru'],
                'c' => $r['variant_c_ru'], 'd' => $r['variant_d_ru'],
            ],
        ], $rows);

        Response::success(['active' => self::isActive(), 'questions' => $questions]);
    }

    /**
     * MUHIM: bu yerda hech qanday token yoki foydalanuvchi identifikatori
     * qabul qilinmaydi va saqlanmaydi — javoblar to'liq anonim qoladi.
     */
    public static function submit(array $input): void
    {
        if (!self::isActive()) {
            Response::error("So'rovnoma hozircha faol emas", 'SURVEY_INACTIVE');
        }

        $answers = Validate::array($input, 'answers');
        if (count($answers) === 0) {
            Response::error('Javoblar bo\'sh', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $validIds = array_map('intval', $db->query('SELECT id FROM survey_questions')->fetchAll(\PDO::FETCH_COLUMN));

        $db->beginTransaction();
        try {
            $ins = $db->prepare('INSERT INTO survey_submissions () VALUES ()');
            $ins->execute();
            $submissionId = (int) $db->lastInsertId();

            $ansStmt = $db->prepare(
                'INSERT INTO survey_answers (submission_id, question_id, answer_value)
                 VALUES (:sub, :qid, :val)'
            );
            foreach ($answers as $a) {
                $qid = (int) ($a['id'] ?? 0);
                $val = trim((string) ($a['letter'] ?? ''));
                if (!in_array($qid, $validIds, true) || $val === '') {
                    continue;
                }
                $ansStmt->execute(['sub' => $submissionId, 'qid' => $qid, 'val' => mb_substr($val, 0, 1000)]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Response::error('Xatolik yuz berdi', 'SUBMIT_FAILED', 500);
        }

        Response::success();
    }

    public static function setActive(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $active = Validate::bool($input, 'active');

        $db = Database::connection();
        $stmt = $db->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('survey_active', :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2"
        );
        $stmt->execute(['v' => $active ? 'true' : 'false', 'v2' => $active ? 'true' : 'false']);

        Response::success();
    }

    private static function questionFields(array $input): array
    {
        $turi = Validate::str($input, 'turi', 20);
        if (!in_array($turi, ['tanlov', 'yulduz', 'matn'], true)) {
            $turi = 'tanlov';
        }
        $stars = Validate::int($input, 'stars', 5) ?: 5;

        return [
            'turi' => $turi,
            'yulduzlar_soni' => $stars,
            'savol' => Validate::requiredStr($input, 'savol', 2000),
            'variant_a' => Validate::str($input, 'a', 500),
            'variant_b' => Validate::str($input, 'b', 500),
            'variant_c' => Validate::str($input, 'c', 500),
            'variant_d' => Validate::str($input, 'd', 500),
            'savol_ru' => Validate::str($input, 'savolRu', 2000),
            'variant_a_ru' => Validate::str($input, 'aRu', 500),
            'variant_b_ru' => Validate::str($input, 'bRu', 500),
            'variant_c_ru' => Validate::str($input, 'cRu', 500),
            'variant_d_ru' => Validate::str($input, 'dRu', 500),
        ];
    }

    public static function add(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $f = self::questionFields($input);

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO survey_questions
             (turi, yulduzlar_soni, savol, variant_a, variant_b, variant_c, variant_d,
              savol_ru, variant_a_ru, variant_b_ru, variant_c_ru, variant_d_ru)
             VALUES
             (:turi, :yulduzlar_soni, :savol, :variant_a, :variant_b, :variant_c, :variant_d,
              :savol_ru, :variant_a_ru, :variant_b_ru, :variant_c_ru, :variant_d_ru)'
        );
        $stmt->execute($f);

        Response::success(['id' => (int) $db->lastInsertId()]);
    }

    public static function edit(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }
        $f = self::questionFields($input);
        $f['id'] = $id;

        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE survey_questions SET
                turi = :turi, yulduzlar_soni = :yulduzlar_soni, savol = :savol,
                variant_a = :variant_a, variant_b = :variant_b, variant_c = :variant_c, variant_d = :variant_d,
                savol_ru = :savol_ru, variant_a_ru = :variant_a_ru, variant_b_ru = :variant_b_ru,
                variant_c_ru = :variant_c_ru, variant_d_ru = :variant_d_ru
             WHERE id = :id'
        );
        $stmt->execute($f);

        Response::success();
    }

    public static function delete(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM survey_questions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success();
    }

    public static function results(array $input): void
    {
        Auth::requireRole($input, ['gl-admin', 'admin']);

        $db = Database::connection();
        $totalSubmissions = (int) $db->query('SELECT COUNT(*) FROM survey_submissions')->fetchColumn();
        $questions = $db->query('SELECT * FROM survey_questions ORDER BY id ASC')->fetchAll();

        $report = [];
        foreach ($questions as $q) {
            $qid = (int) $q['id'];

            if ($q['turi'] === 'matn') {
                $stmt = $db->prepare('SELECT answer_value FROM survey_answers WHERE question_id = :id ORDER BY id ASC');
                $stmt->execute(['id' => $qid]);
                $responses = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $report[] = [
                    'turi' => 'matn',
                    'savol' => $q['savol'],
                    'total' => count($responses),
                    'responses' => $responses,
                ];
                continue;
            }

            $countStmt = $db->prepare(
                'SELECT answer_value, COUNT(*) AS cnt FROM survey_answers WHERE question_id = :id GROUP BY answer_value'
            );
            $countStmt->execute(['id' => $qid]);
            $counts = [];
            foreach ($countStmt->fetchAll() as $row) {
                $counts[$row['answer_value']] = (int) $row['cnt'];
            }

            if ($q['turi'] === 'yulduz') {
                $stars = (int) $q['yulduzlar_soni'];
                $total = array_sum($counts);
                $weighted = 0;
                $ratingCounts = [];
                for ($v = 1; $v <= $stars; $v++) {
                    $c = $counts[(string) $v] ?? 0;
                    $ratingCounts[] = ['value' => $v, 'count' => $c];
                    $weighted += $v * $c;
                }
                $report[] = [
                    'turi' => 'yulduz',
                    'savol' => $q['savol'],
                    'stars' => $stars,
                    'total' => $total,
                    'average' => $total > 0 ? round($weighted / $total, 1) : 0,
                    'ratingCounts' => $ratingCounts,
                ];
                continue;
            }

            // tanlov
            $letters = ['A' => 'variant_a', 'B' => 'variant_b', 'C' => 'variant_c', 'D' => 'variant_d'];
            $options = [];
            $total = 0;
            foreach ($letters as $letter => $col) {
                if (!$q[$col]) {
                    continue;
                }
                $c = $counts[$letter] ?? 0;
                $total += $c;
                $options[] = ['text' => $q[$col], 'count' => $c];
            }
            $report[] = [
                'turi' => 'tanlov',
                'savol' => $q['savol'],
                'total' => $total,
                'options' => $options,
            ];
        }

        Response::success(['totalSubmissions' => $totalSubmissions, 'report' => $report]);
    }
}
