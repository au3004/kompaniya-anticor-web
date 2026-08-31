<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Util;
use App\Validate;
use PDO;

/**
 * "Hisobotlar" bo'limi uchun — admin.html'da xom (flat) jadval ko'rinishida
 * yuklab olinadigan ma'lumotlarni tayyorlaydi. Har bir metod eski Google
 * Sheets'dagi tegishli varaqning ustunlariga mos massiv qaytaradi.
 */
final class ReportsController
{
    public static function getUsersReport(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        $rows = $db->query(
            "SELECT u.*,
                EXISTS(SELECT 1 FROM doc_reads d WHERE d.user_id = u.id) AS has_docs,
                EXISTS(SELECT 1 FROM test_attempts t WHERE t.user_id = u.id) AS has_test
             FROM users u ORDER BY u.familiya ASC, u.ism ASC"
        )->fetchAll();

        $users = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'login' => $r['login'],
            'familiya' => $r['familiya'],
            'ism' => $r['ism'],
            'otasi' => $r['otasining_ismi'],
            'lavozim' => $r['lavozim'],
            'lavozimRu' => $r['lavozim_ru'],
            'bolinma' => $r['bolinma'],
            'bolinmaRu' => $r['bolinma_ru'],
            'telefon' => $r['telefon'],
            'rol' => $r['rol'],
            'hujjatTanishgan' => (bool) $r['has_docs'],
            'testTopshirgan' => (bool) $r['has_test'],
        ], $rows);

        Response::success(['users' => $users]);
    }

    public static function getProgressReport(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        $employees = $db->query(
            "SELECT id, familiya, ism, otasining_ismi, telefon FROM users
             WHERE rol = 'user' ORDER BY familiya ASC, ism ASC"
        )->fetchAll();

        $docsByUser = [];
        foreach ($db->query('SELECT user_id, read_at FROM doc_reads ORDER BY user_id ASC, read_at ASC') as $d) {
            $docsByUser[(int) $d['user_id']][] = $d['read_at'];
        }

        $attemptsByUser = [];
        foreach ($db->query('SELECT user_id, attempted_at, points, percent FROM test_attempts ORDER BY user_id ASC, attempted_at ASC') as $a) {
            $attemptsByUser[(int) $a['user_id']][] = $a;
        }

        $rows = [];
        $rowNum = 0;
        foreach ($employees as $u) {
            $uid = (int) $u['id'];
            $rowNum++;
            $docs = $docsByUser[$uid] ?? [];
            $attempts = $attemptsByUser[$uid] ?? [];

            $tanishganSana = $docs ? date('d.m.Y G:i', strtotime($docs[0])) : '';
            $qaytaTanishgan = implode("\n", array_map(
                static fn ($d) => date('d.m.Y G:i', strtotime($d)),
                array_slice($docs, 1)
            ));

            $testSana = $attempts ? date('d.m.Y', strtotime($attempts[0]['attempted_at'])) : '';
            $natija = $attempts ? "{$attempts[0]['points']} ball {$attempts[0]['percent']}%" : '';
            $qaytaTopshirish = implode("\n", array_map(
                static fn ($a) => date('d.m.Y G:i', strtotime($a['attempted_at'])) . " - {$a['points']} ball {$a['percent']}%",
                array_slice($attempts, 1)
            ));

            $rows[] = [
                'id' => $rowNum,
                'fish' => Util::fullName($u),
                'telefon' => $u['telefon'],
                'tanishganSana' => $tanishganSana,
                'qaytaTanishgan' => $qaytaTanishgan,
                'test' => $testSana,
                'natija' => $natija,
                'qaytaTopshirish' => $qaytaTopshirish,
            ];
        }

        Response::success(['rows' => $rows]);
    }

    public static function getSupportRequests(array $input): void
    {
        Auth::requireRole($input, ['gl-admin', 'admin']);

        $db = Database::connection();
        $rows = $db->query(
            "SELECT sr.id, sr.murojaat, sr.created_at,
                    u.login, u.familiya, u.ism, u.otasining_ismi, u.telefon
             FROM support_requests sr
             JOIN users u ON u.id = sr.user_id
             ORDER BY sr.created_at DESC"
        )->fetchAll();

        $commentsByRequest = [];
        $commentRows = $db->query(
            "SELECT sc.support_request_id, sc.izoh, sc.created_at,
                    u.familiya, u.ism, u.otasining_ismi
             FROM support_comments sc
             JOIN users u ON u.id = sc.user_id
             ORDER BY sc.support_request_id ASC, sc.created_at ASC, sc.id ASC"
        )->fetchAll();
        foreach ($commentRows as $c) {
            $commentsByRequest[(int) $c['support_request_id']][] = [
                'fish' => Util::fullName($c),
                'izoh' => $c['izoh'],
                'sana' => date('d.m.Y G:i', strtotime((string) $c['created_at'])),
            ];
        }

        $list = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'login' => $r['login'],
            'fish' => Util::fullName($r),
            'telefon' => $r['telefon'],
            'murojaat' => $r['murojaat'],
            'comments' => $commentsByRequest[(int) $r['id']] ?? [],
            'sana' => date('d.m.Y G:i', strtotime((string) $r['created_at'])),
        ], $rows);

        Response::success(['requests' => $list]);
    }

    public static function addSupportComment(array $input): void
    {
        $user = Auth::requireRole($input, ['gl-admin', 'admin']);

        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }
        $izoh = Validate::str($input, 'izoh', 4000);
        if ($izoh === '') {
            Response::error('Izoh matni talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $exists = $db->prepare('SELECT id FROM support_requests WHERE id = :id');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            Response::error('So\'rov topilmadi', 'NOT_FOUND', 404);
        }

        $stmt = $db->prepare(
            'INSERT INTO support_comments (support_request_id, user_id, izoh) VALUES (:rid, :uid, :izoh)'
        );
        $stmt->execute(['rid' => $id, 'uid' => (int) $user['id'], 'izoh' => $izoh]);

        Response::success();
    }

    public static function getNotificationsRaw(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        $rows = $db->query(
            "SELECT n.id, n.matn, n.target_type, n.target_value, n.sent_at,
                    u.login AS sender_login, u.familiya, u.ism, u.otasining_ismi
             FROM notifications n
             JOIN users u ON u.id = n.sender_id
             ORDER BY n.id ASC"
        )->fetchAll();

        $list = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'senderLogin' => $r['sender_login'],
            'senderFish' => Util::fullName($r),
            'sana' => date('d.m.Y G:i', strtotime((string) $r['sent_at'])),
            'text' => $r['matn'],
            'targetType' => $r['target_type'],
            'targetValue' => $r['target_value'],
        ], $rows);

        Response::success(['notifications' => $list]);
    }

    public static function getNotificationReadsRaw(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        $rows = $db->query(
            "SELECT nr.id, nr.notification_id, nr.read_at,
                    u.login, u.familiya, u.ism, u.otasining_ismi
             FROM notification_reads nr
             JOIN users u ON u.id = nr.user_id
             ORDER BY nr.id ASC"
        )->fetchAll();

        $list = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'notificationId' => (int) $r['notification_id'],
            'login' => $r['login'],
            'fish' => Util::fullName($r),
            'sana' => date('d.m.Y G:i', strtotime((string) $r['read_at'])),
        ], $rows);

        Response::success(['reads' => $list]);
    }

    public static function getSurveyAnswersWide(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        $questionIds = $db->query('SELECT id FROM survey_questions ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
        $position = array_flip($questionIds);

        $submissions = $db->query('SELECT id, submitted_at FROM survey_submissions ORDER BY id ASC')->fetchAll();

        $bySubmission = [];
        $answersStmt = $db->query('SELECT submission_id, question_id, answer_value FROM survey_answers');
        foreach ($answersStmt as $a) {
            $qid = (int) $a['question_id'];
            if (!isset($position[$qid])) {
                continue;
            }
            $bySubmission[(int) $a['submission_id']][$position[$qid]] = $a['answer_value'];
        }

        $questionCount = count($questionIds);
        $rows = [];
        foreach ($submissions as $s) {
            $sid = (int) $s['id'];
            $answers = [];
            for ($i = 0; $i < $questionCount; $i++) {
                $answers[] = $bySubmission[$sid][$i] ?? '';
            }
            $rows[] = [
                'id' => $sid,
                'sana' => date('d.m.Y G:i', strtotime((string) $s['submitted_at'])),
                'answers' => $answers,
            ];
        }

        Response::success(['questionCount' => $questionCount, 'submissions' => $rows]);
    }
}
