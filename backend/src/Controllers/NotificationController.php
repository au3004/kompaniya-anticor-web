<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Util;
use App\Validate;

final class NotificationController
{
    public static function mine(array $input): void
    {
        $user = Auth::requireUser($input);

        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT n.id, n.matn,
                EXISTS(
                    SELECT 1 FROM notification_reads nr
                    WHERE nr.notification_id = n.id AND nr.user_id = :uid
                ) AS is_read
             FROM notifications n
             WHERE (n.target_type = 'department' AND n.target_value = :bolinma)
                OR (n.target_type = 'users' AND FIND_IN_SET(:login, n.target_value))
             ORDER BY n.sent_at DESC"
        );
        $stmt->execute([
            'uid' => $user['id'],
            'bolinma' => (string) $user['bolinma'],
            'login' => (string) $user['login'],
        ]);

        $notifications = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'text' => $r['matn'],
            'read' => (bool) $r['is_read'],
        ], $stmt->fetchAll());

        Response::success(['notifications' => $notifications]);
    }

    public static function markRead(array $input): void
    {
        $user = Auth::requireUser($input);
        $notifId = Validate::int($input, 'notifId');
        if (!$notifId) {
            Response::error('ID talab qilinadi', 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO notification_reads (notification_id, user_id) VALUES (:nid, :uid)
             ON DUPLICATE KEY UPDATE read_at = read_at'
        );
        $stmt->execute(['nid' => $notifId, 'uid' => $user['id']]);

        Response::success();
    }

    public static function send(array $input): void
    {
        $user = Auth::requireRole($input, ['gl-admin', 'admin']);
        $targetType = Validate::str($input, 'targetType', 20);
        $text = Validate::requiredStr($input, 'text', 4000);

        if (!in_array($targetType, ['users', 'department'], true)) {
            Response::error("Noto'g'ri qamrov turi", 'VALIDATION_ERROR', 422);
        }

        if ($targetType === 'department') {
            $targetValue = Validate::requiredStr($input, 'department', 200);
        } else {
            $logins = Validate::array($input, 'targetLogins');
            $logins = array_values(array_filter(array_map(static fn ($l) => trim((string) $l), $logins)));
            if (count($logins) === 0) {
                Response::error('Kamida bitta qabul qiluvchi tanlang', 'VALIDATION_ERROR', 422);
            }
            $targetValue = implode(',', $logins);
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO notifications (sender_id, matn, target_type, target_value)
             VALUES (:sender_id, :matn, :target_type, :target_value)'
        );
        $stmt->execute([
            'sender_id' => $user['id'],
            'matn' => $text,
            'target_type' => $targetType,
            'target_value' => mb_substr($targetValue, 0, 1000),
        ]);

        Response::success();
    }

    public static function report(array $input): void
    {
        Auth::requireRole($input, ['gl-admin', 'admin']);

        $db = Database::connection();
        $rows = $db->query(
            "SELECT n.*, u.familiya AS s_familiya, u.ism AS s_ism, u.otasining_ismi AS s_otasi
             FROM notifications n
             JOIN users u ON u.id = n.sender_id
             ORDER BY n.sent_at DESC"
        )->fetchAll();

        $report = [];
        foreach ($rows as $n) {
            $nid = (int) $n['id'];

            if ($n['target_type'] === 'department') {
                $totalStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE bolinma = :d');
                $totalStmt->execute(['d' => $n['target_value']]);
                $totalTarget = (int) $totalStmt->fetchColumn();

                $readersStmt = $db->prepare(
                    'SELECT u.familiya, u.ism, u.otasining_ismi, nr.read_at
                     FROM notification_reads nr JOIN users u ON u.id = nr.user_id
                     WHERE nr.notification_id = :id AND u.bolinma = :d
                     ORDER BY nr.read_at ASC'
                );
                $readersStmt->execute(['id' => $nid, 'd' => $n['target_value']]);
            } else {
                $logins = array_values(array_filter(explode(',', (string) $n['target_value'])));
                $totalTarget = count($logins);

                if (count($logins) > 0) {
                    $placeholders = implode(',', array_fill(0, count($logins), '?'));
                    $readersStmt = $db->prepare(
                        "SELECT u.familiya, u.ism, u.otasining_ismi, nr.read_at
                         FROM notification_reads nr JOIN users u ON u.id = nr.user_id
                         WHERE nr.notification_id = ? AND u.login IN ($placeholders)
                         ORDER BY nr.read_at ASC"
                    );
                    $readersStmt->execute(array_merge([$nid], $logins));
                } else {
                    $readersStmt = null;
                }
            }

            $readers = [];
            if ($readersStmt) {
                foreach ($readersStmt->fetchAll() as $r) {
                    $readers[] = [
                        'fish' => Util::fullName($r),
                        'sana' => date('Y-m-d H:i', strtotime((string) $r['read_at'])),
                    ];
                }
            }

            $report[] = [
                'senderFish' => Util::fullName(['familiya' => $n['s_familiya'], 'ism' => $n['s_ism'], 'otasining_ismi' => $n['s_otasi']]),
                'targetType' => $n['target_type'],
                'targetValue' => $n['target_type'] === 'department' ? $n['target_value'] : null,
                'sana' => date('Y-m-d H:i', strtotime((string) $n['sent_at'])),
                'text' => $n['matn'],
                'totalTarget' => $totalTarget,
                'readCount' => count($readers),
                'readers' => $readers,
            ];
        }

        Response::success(['report' => $report]);
    }
}
