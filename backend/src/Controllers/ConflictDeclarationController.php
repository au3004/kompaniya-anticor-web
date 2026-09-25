<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\RateLimit;
use App\Response;
use App\Roles;
use App\Util;
use App\Validate;

/**
 * "Manfaatlar to'qnashuvi" deklaratsiyasi — xodim E-IMZO bosqichida
 * "imzolash"ni bosgandan so'ng shu yerga yoziladi.
 *
 * DIQQAT: hozircha haqiqiy E-IMZO (elektron raqamli imzo) integratsiyasi
 * yo'q — sertifikat tanlash, Verification ID va imzolash jarayonining o'zi
 * frontend'da namuna (mock) sifatida to'qilgan. Bu yerga yoziladigan
 * verification_id ham shunga mos ravishda haqiqiy kriptografik imzo emas,
 * faqat mijoz tomonidan yuborilgan namuna qiymat sifatida saqlanadi.
 */
final class ConflictDeclarationController
{
    private const DDL = "CREATE TABLE IF NOT EXISTS declarations (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        user_id       INT NOT NULL,
        submitted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB";

    private const ALTER_DDL = "ALTER TABLE declarations
        ADD COLUMN IF NOT EXISTS ref_id VARCHAR(40) NULL AFTER user_id,
        ADD COLUMN IF NOT EXISTS has_conflict TINYINT(1) NULL AFTER ref_id,
        ADD COLUMN IF NOT EXISTS verification_id VARCHAR(64) NULL AFTER has_conflict,
        ADD COLUMN IF NOT EXISTS payload LONGTEXT NULL AFTER verification_id";

    private const MAX_PAYLOAD_BYTES = 200 * 1024;

    private static function ensure(\PDO $db): void
    {
        Util::ensureSchema($db, self::DDL);
        Util::ensureSchema($db, self::ALTER_DDL);
    }

    /** Xodim E-IMZO bosqichida "imzoladi"dan so'ng deklaratsiyani serverga yuboradi. */
    public static function submit(array $input): void
    {
        $user = Auth::requireUser($input);
        RateLimit::enforce('submitDeclaration', 10, 3600);

        $db = Database::connection();
        self::ensure($db);

        $refId = Validate::requiredStr($input, 'refId', 40);
        $verificationId = Validate::requiredStr($input, 'verificationId', 64);
        $hasConflict = Validate::bool($input, 'hasConflict');

        $payloadRaw = (string) ($input['payload'] ?? '');
        if ($payloadRaw === '' || strlen($payloadRaw) > self::MAX_PAYLOAD_BYTES) {
            Response::error("Deklaratsiya ma'lumotlari noto'g'ri", 'VALIDATION_ERROR', 422);
        }
        // Faqat haqiqiy JSON ekanligini tekshiramiz — boshqa turdagi matn saqlab qolinmasin.
        json_decode($payloadRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::error("Deklaratsiya ma'lumotlari JSON emas", 'VALIDATION_ERROR', 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO declarations (user_id, ref_id, has_conflict, verification_id, payload)
             VALUES (:user_id, :ref_id, :has_conflict, :verification_id, :payload)'
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'ref_id' => $refId,
            'has_conflict' => $hasConflict ? 1 : 0,
            'verification_id' => $verificationId,
            'payload' => $payloadRaw,
        ]);

        Response::success(['id' => (int) $db->lastInsertId()]);
    }

    /** Admin panel: "Manfaatlar to'qnashuvi" ro'yxati (jadval ko'rinishida). */
    public static function adminList(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_VIEW);
        $db = Database::connection();
        self::ensure($db);

        $stmt = $db->query(
            'SELECT d.id, d.ref_id, d.has_conflict, d.verification_id, d.submitted_at,
                    u.familiya, u.ism, u.otasining_ismi, u.lavozim, u.bolinma, u.telefon
             FROM declarations d
             JOIN users u ON u.id = d.user_id
             ORDER BY d.submitted_at DESC'
        );

        $rows = array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'refId' => $r['ref_id'],
            'hasConflict' => $r['has_conflict'] === null ? null : (bool) $r['has_conflict'],
            'verificationId' => $r['verification_id'],
            'submittedAt' => date('Y-m-d H:i', strtotime((string) $r['submitted_at'])),
            'fullName' => trim(implode(' ', array_filter([$r['familiya'], $r['ism'], $r['otasining_ismi']]))),
            'lavozim' => $r['lavozim'],
            'bolinma' => $r['bolinma'],
            'telefon' => $r['telefon'],
        ], $stmt->fetchAll());

        Response::success(['declarations' => $rows]);
    }

    /** Admin panel: bitta deklaratsiyaning to'liq ma'lumotini ko'rish ("Fayl" ustunidagi ko'rish havolasi). */
    public static function adminGetOne(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_VIEW);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error("ID noto'g'ri", 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        self::ensure($db);

        $stmt = $db->prepare(
            'SELECT d.id, d.ref_id, d.has_conflict, d.verification_id, d.payload, d.submitted_at,
                    u.familiya, u.ism, u.otasining_ismi, u.lavozim, u.bolinma, u.telefon
             FROM declarations d
             JOIN users u ON u.id = d.user_id
             WHERE d.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Topilmadi', 'NOT_FOUND', 404);
        }

        Response::success([
            'declaration' => [
                'id' => (int) $row['id'],
                'refId' => $row['ref_id'],
                'hasConflict' => $row['has_conflict'] === null ? null : (bool) $row['has_conflict'],
                'verificationId' => $row['verification_id'],
                'submittedAt' => date('Y-m-d H:i', strtotime((string) $row['submitted_at'])),
                'fullName' => trim(implode(' ', array_filter([$row['familiya'], $row['ism'], $row['otasining_ismi']]))),
                'lavozim' => $row['lavozim'],
                'bolinma' => $row['bolinma'],
                'telefon' => $row['telefon'],
                'payload' => json_decode((string) $row['payload'], true),
            ],
        ]);
    }

    /** Admin panel: deklaratsiyani ro'yxatdan (bazadan) o'chiradi. */
    public static function adminDelete(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        $id = Validate::int($input, 'id');
        if (!$id) {
            Response::error("ID noto'g'ri", 'VALIDATION_ERROR', 422);
        }

        $db = Database::connection();
        self::ensure($db);

        $stmt = $db->prepare('DELETE FROM declarations WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success();
    }
}
