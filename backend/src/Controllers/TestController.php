<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\Response;
use App\Validate;

final class TestController
{
    private static function isActive(): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'test_active' LIMIT 1");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? true : $value === 'true';
    }

    public static function getQuestions(array $input): void
    {
        $viewer = Auth::optionalUser($input);
        $isAdmin = $viewer && in_array($viewer['rol'], ['admin', 'gl-admin'], true);

        $db = Database::connection();
        $rows = $db->query('SELECT * FROM test_questions ORDER BY id ASC')->fetchAll();

        $questions = array_map(static function (array $r) use ($isAdmin) {
            $uz = [
                'savol' => $r['savol'], 'a' => $r['variant_a'], 'b' => $r['variant_b'],
                'c' => $r['variant_c'], 'd' => $r['variant_d'],
            ];
            $ru = [
                'savol' => $r['savol_ru'], 'a' => $r['variant_a_ru'], 'b' => $r['variant_b_ru'],
                'c' => $r['variant_c_ru'], 'd' => $r['variant_d_ru'],
            ];
            if ($isAdmin) {
                $uz['correct'] = $r['togri_javob'];
                $ru['correct'] = $r['togri_javob_ru'];
            }
            return ['id' => (int) $r['id'], 'uz' => $uz, 'ru' => $ru];
        }, $rows);

        Response::success(['active' => self::isActive(), 'questions' => $questions]);
    }

    public static function submit(array $input): void
    {
        $user = Auth::requireUser($input);

        if (!self::isActive()) {
            Response::error('Test hozircha faol emas', 'TEST_INACTIVE');
        }

        $answers = Validate::array($input, 'answers');
        $db = Database::connection();

        $rows = $db->query('SELECT * FROM test_questions')->fetchAll();
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        $totalQuestions = count($rows);

        $letterCol = ['A' => 'variant_a', 'B' => 'variant_b', 'C' => 'variant_c', 'D' => 'variant_d'];
        $letterColRu = ['A' => 'variant_a_ru', 'B' => 'variant_b_ru', 'C' => 'variant_c_ru', 'D' => 'variant_d_ru'];

        $points = 0;
        $wrongIds = [];

        foreach ($answers as $answer) {
            $qid = (int) ($answer['id'] ?? 0);
            $letter = strtoupper((string) ($answer['letter'] ?? ''));
            if (!isset($byId[$qid]) || !isset($letterCol[$letter])) {
                continue;
            }
            $q = $byId[$qid];
            $uzMatch = $q[$letterCol[$letter]] !== null && $q[$letterCol[$letter]] === $q['togri_javob'];
            $ruMatch = $q[$letterColRu[$letter]] !== null && $q[$letterColRu[$letter]] === $q['togri_javob_ru'];
            if ($uzMatch || $ruMatch) {
                $points++;
            } else {
                $wrongIds[] = $qid;
            }
        }

        $maxPoints = max($totalQuestions, 1);
        $percent = (int) round($points / $maxPoints * 100);
        $threshold = Config::int('TEST_PASS_THRESHOLD', 80);
        $passed = $percent >= $threshold;

        $ins = $db->prepare(
            'INSERT INTO test_attempts (user_id, points, max_points, percent, passed)
             VALUES (:user_id, :points, :max_points, :percent, :passed)'
        );
        $ins->execute([
            'user_id' => $user['id'],
            'points' => $points,
            'max_points' => $totalQuestions,
            'percent' => $percent,
            'passed' => $passed ? 1 : 0,
        ]);

        Response::success([
            'points' => $points,
            'maxPoints' => $totalQuestions,
            'percent' => $percent,
            'passed' => $passed,
            'wrongIds' => $wrongIds,
        ]);
    }

    public static function setActive(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $active = Validate::bool($input, 'active');

        $db = Database::connection();
        $stmt = $db->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('test_active', :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2"
        );
        $stmt->execute(['v' => $active ? 'true' : 'false', 'v2' => $active ? 'true' : 'false']);

        Response::success();
    }

    private static function questionFields(array $input): array
    {
        return [
            'savol' => Validate::requiredStr($input, 'savol', 2000),
            'variant_a' => Validate::str($input, 'a', 500),
            'variant_b' => Validate::str($input, 'b', 500),
            'variant_c' => Validate::str($input, 'c', 500),
            'variant_d' => Validate::str($input, 'd', 500),
            'togri_javob' => Validate::str($input, 'correct', 500),
            'savol_ru' => Validate::str($input, 'savolRu', 2000),
            'variant_a_ru' => Validate::str($input, 'aRu', 500),
            'variant_b_ru' => Validate::str($input, 'bRu', 500),
            'variant_c_ru' => Validate::str($input, 'cRu', 500),
            'variant_d_ru' => Validate::str($input, 'dRu', 500),
            'togri_javob_ru' => Validate::str($input, 'correctRu', 500),
        ];
    }

    public static function add(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);
        $f = self::questionFields($input);

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO test_questions
             (savol, variant_a, variant_b, variant_c, variant_d, togri_javob,
              savol_ru, variant_a_ru, variant_b_ru, variant_c_ru, variant_d_ru, togri_javob_ru)
             VALUES
             (:savol, :variant_a, :variant_b, :variant_c, :variant_d, :togri_javob,
              :savol_ru, :variant_a_ru, :variant_b_ru, :variant_c_ru, :variant_d_ru, :togri_javob_ru)'
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
            'UPDATE test_questions SET
                savol = :savol, variant_a = :variant_a, variant_b = :variant_b,
                variant_c = :variant_c, variant_d = :variant_d, togri_javob = :togri_javob,
                savol_ru = :savol_ru, variant_a_ru = :variant_a_ru, variant_b_ru = :variant_b_ru,
                variant_c_ru = :variant_c_ru, variant_d_ru = :variant_d_ru, togri_javob_ru = :togri_javob_ru
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
        $stmt = $db->prepare('DELETE FROM test_questions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success();
    }
}
