<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Database;
use App\Response;
use App\Roles;
use App\TestPolicy;
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

        // Faqat aslida shu foydalanuvchiga mo'ljallangan bildirishnomani "o'qildi" deb
        // belgilash mumkin — boshqa birovga yo'llangan notifId'ni taxmin qilib yubormaslik uchun.
        $db = Database::connection();
        $stmt = $db->prepare(
            "INSERT INTO notification_reads (notification_id, user_id)
             SELECT n.id, :uid FROM notifications n
             WHERE n.id = :nid
               AND (
                 (n.target_type = 'department' AND n.target_value = :bolinma)
                 OR (n.target_type = 'users' AND FIND_IN_SET(:login, n.target_value))
               )
             ON DUPLICATE KEY UPDATE read_at = read_at"
        );
        $stmt->execute([
            'uid' => $user['id'],
            'nid' => $notifId,
            'bolinma' => (string) $user['bolinma'],
            'login' => (string) $user['login'],
        ]);

        Response::success();
    }

    public static function send(array $input): void
    {
        $user = Auth::requireRole($input, Roles::NOTIFY_SEND);
        $targetType = Validate::str($input, 'targetType', 20);
        $text = Validate::requiredStr($input, 'text', 4000);

        if (!in_array($targetType, ['users', 'department'], true)) {
            Response::error("Noto'g'ri qamrov turi", 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();

        if ($targetType === 'department') {
            $targetValue = Validate::requiredStr($input, 'department', 200);
        } else {
            $logins = Validate::array($input, 'targetLogins');
            $logins = array_values(array_filter(array_map(static fn ($l) => trim((string) $l), $logins)));
            // Himoya chorasi: mijoz tomonidan yuborilgan ro'yxatda super-admin
            // login'i bo'lsa ham, u xabarnoma qabul qiluvchilar ro'yxatidan
            // (va shu orqali keyingi hisobot/o'qilganlik yozuvlaridan)
            // chetlatiladi — super-admin hech qaysi ro'yxatda ko'rinmasligi shart.
            if (count($logins) > 0) {
                $placeholders = implode(',', array_fill(0, count($logins), '?'));
                $superStmt = $db->prepare(
                    "SELECT login FROM users WHERE rol = ? AND login IN ($placeholders)"
                );
                $superStmt->execute(array_merge([Roles::SUPER_ADMIN], $logins));
                $excluded = $superStmt->fetchAll(\PDO::FETCH_COLUMN);
                if ($excluded) {
                    $logins = array_values(array_diff($logins, $excluded));
                }
            }
            if (count($logins) === 0) {
                Response::error('Kamida bitta qabul qiluvchi tanlang', 'VALIDATION_ERROR', 422);
            }
            $targetValue = implode(',', $logins);
        }

        if ($targetType === 'department') {
            $stmt = $db->prepare(
                "INSERT INTO notifications (sender_id, matn, target_type, target_value)
                 VALUES (:sender_id, :matn, 'department', :target_value)"
            );
            $stmt->execute(['sender_id' => $user['id'], 'matn' => $text, 'target_value' => mb_substr($targetValue, 0, 1000)]);
        } else {
            self::insertForLogins($db, (int) $user['id'], $text, $logins);
        }
        Audit::log($user, 'notification_send',
            ($targetType === 'department' ? "bo'linma: {$targetValue}" : count($logins) . ' ta xodim')
            . ' — ' . mb_substr($text, 0, 200));

        Response::success();
    }

    /**
     * Login'lar ro'yxatiga xabarnoma yozadi. target_value 1000 belgi bilan
     * cheklangani uchun ko'p qabul qiluvchili ro'yxat bir nechta yozuvga
     * bo'linadi (avval ortig'i jimgina kesilib, ba'zi xodimlarga xabar yetmasdi).
     */
    private static function insertForLogins(\PDO $db, int $senderId, string $text, array $logins): int
    {
        $stmt = $db->prepare(
            "INSERT INTO notifications (sender_id, matn, target_type, target_value)
             VALUES (:sender_id, :matn, 'users', :target_value)"
        );
        $chunks = [];
        $current = '';
        foreach ($logins as $login) {
            $candidate = $current === '' ? $login : $current . ',' . $login;
            if (mb_strlen($candidate) > 1000 && $current !== '') {
                $chunks[] = $current;
                $candidate = $login;
            }
            $current = $candidate;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }
        foreach ($chunks as $chunk) {
            $stmt->execute(['sender_id' => $senderId, 'matn' => $text, 'target_value' => $chunk]);
        }
        return count($chunks);
    }

    /**
     * Bir tugma bilan eslatma: testni (joriy tsiklda) topshirmagan va/yoki
     * hujjat bilan tanishmagan xodimlarga xabarnoma. types: ["test", "doc"];
     * filial: '' — hamma, '__none' — filiali ko'rsatilmaganlar, aks holda filial kaliti.
     * Admin rollari ham xodim sifatida hisobga olinadi (statistika bilan bir xil);
     * super-admin hech qachon.
     */
    public static function sendReminders(array $input): void
    {
        $me = Auth::requireRole($input, Roles::NOTIFY_SEND);
        $types = array_values(array_intersect(['test', 'doc'], array_map('strval', Validate::array($input, 'types'))));
        if (!$types) {
            Response::error('Eslatma turini tanlang', 'VALIDATION_ERROR', 422);
        }
        $filial = Validate::str($input, 'filial', 50);
        $preview = Validate::bool($input, 'preview');

        $db = Database::connection();
        $users = $db->prepare('SELECT id, login, filial FROM users WHERE rol != :superAdmin');
        $users->execute(['superAdmin' => Roles::SUPER_ADMIN]);
        $users = array_filter($users->fetchAll(), static fn (array $u): bool =>
            $filial === '' || ($filial === '__none' ? empty($u['filial']) : $u['filial'] === $filial));

        $readers = array_fill_keys(array_map('intval',
            $db->query('SELECT DISTINCT user_id FROM doc_reads')->fetchAll(\PDO::FETCH_COLUMN)), true);
        $cycleStarts = TestPolicy::cycleStarts($db);
        $takers = [];
        foreach ($db->query('SELECT user_id, attempted_at FROM test_attempts') as $a) {
            $uid = (int) $a['user_id'];
            if (!isset($cycleStarts[$uid]) || (string) $a['attempted_at'] >= $cycleStarts[$uid]) {
                $takers[$uid] = true;
            }
        }

        $deadline = TestPolicy::deadline($db);
        $deadlineUz = $deadline ? ' Topshirish muddati: ' . date('d.m.Y', strtotime($deadline)) . '.' : '';
        $deadlineRu = $deadline ? ' Срок сдачи: ' . date('d.m.Y', strtotime($deadline)) . '.' : '';
        $texts = [
            'test' => "Eslatma: siz hali «Korrupsiyaga qarshi kurashish» testini topshirmagansiz. Iltimos, testni topshiring.{$deadlineUz}\n"
                . "Напоминание: вы ещё не прошли тест «Противодействие коррупции». Пожалуйста, пройдите тест.{$deadlineRu}",
            'doc' => "Eslatma: iltimos, «Korrupsiyaga qarshi kurashish» bo'limidagi normativ hujjatlar bilan tanishing.\n"
                . "Напоминание: пожалуйста, ознакомьтесь с нормативными документами в разделе «Противодействие коррупции».",
        ];

        $result = [];
        foreach ($types as $type) {
            $logins = [];
            foreach ($users as $u) {
                $uid = (int) $u['id'];
                if (($type === 'test' && !isset($takers[$uid])) || ($type === 'doc' && !isset($readers[$uid]))) {
                    $logins[] = (string) $u['login'];
                }
            }
            if (!$preview && $logins) {
                self::insertForLogins($db, (int) $me['id'], $texts[$type], $logins);
            }
            $result[$type] = count($logins);
        }

        if (!$preview && array_sum($result) > 0) {
            $labels = ['test' => 'testni topshirmaganlar', 'doc' => 'hujjat bilan tanishmaganlar'];
            Audit::log($me, 'reminders_send', implode('; ', array_map(
                static fn (string $t): string => "{$labels[$t]}: {$result[$t]} ta", array_keys($result)
            )) . ($filial !== '' ? ' — filial: ' . ($filial === '__none' ? "ko'rsatilmagan" : (\App\Filials::labelUz($filial) ?? $filial)) : ''));
        }

        Response::success(['counts' => $result, 'preview' => $preview]);
    }

    public static function report(array $input): void
    {
        Auth::requireRole($input, Roles::NOTIFY_SEND);

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
                $totalStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE bolinma = :d AND rol != :superAdmin');
                $totalStmt->execute(['d' => $n['target_value'], 'superAdmin' => Roles::SUPER_ADMIN]);
                $totalTarget = (int) $totalStmt->fetchColumn();

                $readersStmt = $db->prepare(
                    'SELECT u.familiya, u.ism, u.otasining_ismi, nr.read_at
                     FROM notification_reads nr JOIN users u ON u.id = nr.user_id
                     WHERE nr.notification_id = :id AND u.bolinma = :d AND u.rol != :superAdmin
                     ORDER BY nr.read_at ASC'
                );
                $readersStmt->execute(['id' => $nid, 'd' => $n['target_value'], 'superAdmin' => Roles::SUPER_ADMIN]);
            } else {
                // send() allaqachon super-admin login'ini target_value'dan
                // chetlatadi, lekin bu yozuv shundan oldin yaratilgan bo'lishi
                // mumkinligi uchun bu yerda ham himoya sifatida qoldiramiz.
                $logins = array_values(array_filter(explode(',', (string) $n['target_value'])));
                $totalTarget = count($logins);

                if (count($logins) > 0) {
                    $placeholders = implode(',', array_fill(0, count($logins), '?'));
                    $readersStmt = $db->prepare(
                        "SELECT u.familiya, u.ism, u.otasining_ismi, nr.read_at
                         FROM notification_reads nr JOIN users u ON u.id = nr.user_id
                         WHERE nr.notification_id = ? AND u.login IN ($placeholders) AND u.rol != ?
                         ORDER BY nr.read_at ASC"
                    );
                    $readersStmt->execute(array_merge([$nid], $logins, [Roles::SUPER_ADMIN]));
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
