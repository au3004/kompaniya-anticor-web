<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Util;

final class AdminController
{
    public static function usersList(array $input): void
    {
        Auth::requireRole($input, ['gl-admin', 'admin']);

        $db = Database::connection();
        $rows = $db->query(
            'SELECT id, login, familiya, ism, otasining_ismi, lavozim, bolinma, telefon, rol
             FROM users ORDER BY familiya ASC, ism ASC'
        )->fetchAll();

        $users = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'login' => $r['login'],
            'familiya' => $r['familiya'],
            'ism' => $r['ism'],
            'otasi' => $r['otasining_ismi'],
            'lavozim' => $r['lavozim'],
            'bolinma' => $r['bolinma'],
            'telefon' => $r['telefon'],
            'rol' => $r['rol'],
        ], $rows);

        Response::success(['users' => $users]);
    }

    public static function stats(array $input): void
    {
        Auth::requireRole($input, ['gl-admin']);

        $db = Database::connection();
        $employeeRows = $db->query(
            "SELECT u.*, (SELECT MAX(read_at) FROM doc_reads d WHERE d.user_id = u.id) AS last_doc_read
             FROM users u WHERE u.rol = 'user' ORDER BY u.familiya ASC, u.ism ASC"
        )->fetchAll();

        $attemptRows = $db->query(
            'SELECT * FROM test_attempts ORDER BY user_id ASC, attempted_at DESC'
        )->fetchAll();
        $latestAttempt = [];
        foreach ($attemptRows as $a) {
            $uid = (int) $a['user_id'];
            if (!isset($latestAttempt[$uid])) {
                $latestAttempt[$uid] = $a;
            }
        }

        $employees = [];
        $docsDone = 0;
        $testsPassed = 0;
        $testsFailed = 0;

        foreach ($employeeRows as $u) {
            $uid = (int) $u['id'];
            $hujjatSana = $u['last_doc_read'] ? date('Y-m-d', strtotime((string) $u['last_doc_read'])) : null;
            if ($hujjatSana) {
                $docsDone++;
            }

            $attempt = $latestAttempt[$uid] ?? null;
            $testTaken = $attempt !== null;
            if ($testTaken) {
                if ((bool) $attempt['passed']) {
                    $testsPassed++;
                } else {
                    $testsFailed++;
                }
            }

            $employees[] = [
                'fish' => Util::fullName($u),
                'lavozim' => $u['lavozim'],
                'bolinma' => $u['bolinma'],
                'telefon' => $u['telefon'],
                'hujjatSana' => $hujjatSana,
                'testTaken' => $testTaken,
                'testPoints' => $testTaken ? (int) $attempt['points'] : null,
                'testPercent' => $testTaken ? (int) $attempt['percent'] : null,
                'passed' => $testTaken ? (bool) $attempt['passed'] : false,
            ];
        }

        $total = count($employeeRows);

        Response::success([
            'summary' => [
                'total' => $total,
                'docsDone' => $docsDone,
                'testsPassed' => $testsPassed,
                'testsFailed' => $testsFailed,
                'notStarted' => $total - $testsPassed - $testsFailed,
            ],
            'employees' => $employees,
        ]);
    }
}
