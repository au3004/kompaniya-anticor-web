<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use App\Validate;

final class SupportController
{
    public static function submit(array $input): void
    {
        $user = Auth::requireUser($input);
        $murojaat = Validate::requiredStr($input, 'murojaat', 4000);

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO support_requests (user_id, murojaat) VALUES (:user_id, :murojaat)');
        $stmt->execute(['user_id' => $user['id'], 'murojaat' => $murojaat]);

        Response::success();
    }
}
