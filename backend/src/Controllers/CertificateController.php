<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\CertificatePdf;
use App\Config;
use App\Database;
use App\Filials;
use App\Response;
use App\Roles;
use App\Util;
use App\Validate;

/**
 * Testdan o'tish balini qo'lga kiritgan xodimga beriladigan sertifikat (PDF).
 * Xodimda doim faqat bitta amaldagi sertifikat bo'ladi — eng yuqori ball
 * olingan muvaffaqiyatli urinishga tegishli (teng bo'lsa — keyingisi).
 * Sertifikatga chiqadigan har qanday ma'lumot (F.I.Sh, filial, bo'linma,
 * imzo qo'yuvchilar, dizayn versiyasi, tekshiruv havolasi) o'zgarsa, u keyingi
 * ochilishda qayta yaratiladi (sana doim o'sha urinish kuni). Dizaynning
 * o'zi App\CertificatePdf'da. Fayllar backend/certificates/ ichida (public/
 * dan tashqarida) saqlanadi va faqat certificate-download.php orqali beriladi.
 *
 * QR-kodlar certificate-verify.php?t=<token> sahifasiga olib boradi — u yerda
 * sertifikat haqiqiyligi (raqami, F.I.Sh, filiali, sanasi) tekshiriladi.
 */
final class CertificateController
{
    private const DDL = 'CREATE TABLE IF NOT EXISTS certificates (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        user_id          INT NOT NULL UNIQUE,
        test_attempt_id  INT NOT NULL,
        fish             VARCHAR(500) NOT NULL,
        filial           VARCHAR(50) NULL,
        file_name        VARCHAR(64) NOT NULL,
        issued_at        DATETIME NOT NULL,
        render_sig       CHAR(40) NULL,
        verify_token     CHAR(24) NULL UNIQUE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB';

    // Jadval shu ustunlarsiz yaratilgan bazalar uchun.
    private const ALTER_DDL = 'ALTER TABLE certificates
        ADD COLUMN IF NOT EXISTS render_sig CHAR(40) NULL AFTER issued_at,
        ADD COLUMN IF NOT EXISTS verify_token CHAR(24) NULL UNIQUE AFTER render_sig';

    public const DEFAULT_FILIAL_TITLE = 'Filial direktori';
    public const DEFAULT_KOMPLAENS_TITLE = 'Komplaens departamenti direktori';

    /** Namuna (preview) sertifikatining QR tokeni — tekshiruv sahifasi uni "namuna" deb ko'rsatadi. */
    public const SAMPLE_TOKEN = 'namuna';

    public static function certificatesDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/certificates';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public static function hasPassed(\PDO $db, int $userId): bool
    {
        return self::bestPassingAttempt($db, $userId) !== null;
    }

    private static function bestPassingAttempt(\PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare(
            'SELECT id, points, max_points, attempted_at FROM test_attempts
             WHERE user_id = :uid AND passed = 1
             ORDER BY percent DESC, attempted_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Xodimning amaldagi sertifikati (kerak bo'lsa shu yerda yaratib/yangilab).
     * Testdan hali muvaffaqiyatli o'tmagan bo'lsa null. $user — users jadvalidagi
     * to'liq qator (familiya, ism, otasining_ismi, filial, bolinma).
     */
    public static function ensureForUser(\PDO $db, array $user): ?array
    {
        $userId = (int) $user['id'];
        $attempt = self::bestPassingAttempt($db, $userId);
        if (!$attempt) {
            return null;
        }

        Util::ensureSchema($db, self::DDL);
        Util::ensureSchema($db, self::ALTER_DDL);
        $fish = Util::fullName($user);
        $filial = ($user['filial'] ?? null) ?: null;
        $dir = self::certificatesDir();

        $stmt = $db->prepare('SELECT * FROM certificates WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $existing = $stmt->fetch() ?: null;

        // Tekshiruv tokeni xodimga bir marta beriladi va qayta yaratishda saqlanadi.
        $token = ($existing['verify_token'] ?? null) ?: bin2hex(random_bytes(12));
        $issuedAt = (string) $attempt['attempted_at'];
        $data = self::pdfData($db, [
            'number' => self::number($issuedAt, (int) $attempt['id']),
            'fish' => $fish,
            'filial' => $filial,
            'bolinma' => trim((string) ($user['bolinma'] ?? '')),
            'ball' => (int) $attempt['points'] . ' / ' . (int) $attempt['max_points'],
            'date' => date('d.m.Y', strtotime($issuedAt)),
        ], $token);
        $sig = sha1((string) json_encode([CertificatePdf::VERSION, $data]));

        if (
            $existing
            && (int) $existing['test_attempt_id'] === (int) $attempt['id']
            && $existing['render_sig'] === $sig
            && is_file($dir . '/' . $existing['file_name'])
        ) {
            return $existing;
        }

        $fileName = bin2hex(random_bytes(16)) . '.pdf';
        if (file_put_contents($dir . '/' . $fileName, CertificatePdf::render($data)) === false) {
            throw new \RuntimeException("Sertifikat faylini saqlab bo'lmadi");
        }

        try {
            $upsert = $db->prepare(
                'INSERT INTO certificates (user_id, test_attempt_id, fish, filial, file_name, issued_at, render_sig, verify_token)
                 VALUES (:uid, :aid, :fish, :filial, :file, :issued, :sig, :token)
                 ON DUPLICATE KEY UPDATE test_attempt_id = :aid2, fish = :fish2, filial = :filial2,
                                         file_name = :file2, issued_at = :issued2, render_sig = :sig2,
                                         verify_token = :token2'
            );
            $upsert->execute([
                'uid' => $userId,
                'aid' => $attempt['id'], 'aid2' => $attempt['id'],
                'fish' => $fish, 'fish2' => $fish,
                'filial' => $filial, 'filial2' => $filial,
                'file' => $fileName, 'file2' => $fileName,
                'issued' => $issuedAt, 'issued2' => $issuedAt,
                'sig' => $sig, 'sig2' => $sig,
                'token' => $token, 'token2' => $token,
            ]);
        } catch (\Throwable $e) {
            @unlink($dir . '/' . $fileName);
            throw $e;
        }

        if ($existing && $existing['file_name'] !== $fileName) {
            @unlink($dir . '/' . $existing['file_name']);
        }

        return [
            'user_id' => $userId,
            'test_attempt_id' => (int) $attempt['id'],
            'fish' => $fish,
            'filial' => $filial,
            'file_name' => $fileName,
            'issued_at' => $issuedAt,
            'render_sig' => $sig,
            'verify_token' => $token,
        ];
    }

    /** Admin uchun namuna — imzo qo'yuvchilar va dizayn qanday chiqishini ko'rish. */
    public static function previewPdf(\PDO $db, ?string $filial): string
    {
        $filial = Filials::isValid((string) $filial) ? $filial : Filials::MARKAZIY;
        return CertificatePdf::render(self::pdfData($db, [
            'number' => self::number(date('Y-m-d'), 0),
            'fish' => 'Karimova Dilnoza Rustamovna',
            'filial' => $filial,
            'bolinma' => "Mijozlarga xizmat ko‘rsatish bo‘limi",
            'ball' => '92 / 100',
            'date' => date('d.m.Y'),
        ], self::SAMPLE_TOKEN));
    }

    /** Sertifikat raqami: AK-<yil>-<urinish raqami, 6 xona>. */
    public static function number(string $issuedAt, int $attemptId): string
    {
        return 'AK-' . date('Y', strtotime($issuedAt)) . '-' . str_pad((string) $attemptId, 6, '0', STR_PAD_LEFT);
    }

    /** $base'dagi filial kalitini nomiga almashtirib, imzo qo'yuvchilar va QR havolalarini qo'shadi. */
    private static function pdfData(\PDO $db, array $base, string $token): array
    {
        $settings = self::loadSettings($db);
        $filialKey = $base['filial'];
        $signers = [];
        if ($filialKey !== null && isset($settings['filials'][$filialKey])) {
            $signers[] = $settings['filials'][$filialKey] + ['qr' => self::verifyUrl($token, 1)];
        }
        $signers[] = $settings['komplaens'] + ['qr' => self::verifyUrl($token, 2)];

        $base['filial'] = Filials::labelUz($filialKey);
        $base['signers'] = $signers;
        return $base;
    }

    public static function verifyUrl(string $token, int $signer): string
    {
        $base = rtrim((string) Config::get('PUBLIC_BASE_URL', ''), '/');
        if ($base === '' && !empty($_SERVER['HTTP_HOST'])) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            $base = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $dir;
        }
        return $base . '/certificate-verify.php?t=' . rawurlencode($token) . '&s=' . $signer;
    }

    // ------------------------------------------------------------------
    // Imzo qo'yuvchilar (filial rahbarlari va komplaens direktori)
    // ------------------------------------------------------------------

    /**
     * @return array{komplaens: array{title:string,name:string}, filials: array<string, array{title:string,name:string}>}
     */
    public static function loadSettings(\PDO $db): array
    {
        $values = [];
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'cert\\_%'");
            foreach ($stmt->fetchAll() as $row) {
                $values[$row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (\Throwable $e) {
            // Jadval bo'lmasa — standart qiymatlar.
        }

        $pick = static fn (string $key, string $default): string => ($values[$key] ?? '') !== '' ? $values[$key] : $default;
        $result = [
            'komplaens' => [
                'title' => $pick('cert_komplaens_title', self::DEFAULT_KOMPLAENS_TITLE),
                'name' => $values['cert_komplaens_name'] ?? '',
            ],
            'filials' => [],
        ];
        foreach (Filials::ALL as $key) {
            $result['filials'][$key] = [
                'title' => $pick("cert_{$key}_title", self::DEFAULT_FILIAL_TITLE),
                'name' => $values["cert_{$key}_name"] ?? '',
            ];
        }
        return $result;
    }

    public static function getSettings(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);
        Response::success(self::loadSettings(Database::connection()));
    }

    public static function saveSettings(array $input): void
    {
        Auth::requireRole($input, Roles::ANTICOR_MANAGE);

        $rows = [];
        $komplaens = is_array($input['komplaens'] ?? null) ? $input['komplaens'] : [];
        $rows['cert_komplaens_title'] = Validate::str($komplaens, 'title', 100);
        $rows['cert_komplaens_name'] = Validate::str($komplaens, 'name', 100);
        $filials = is_array($input['filials'] ?? null) ? $input['filials'] : [];
        foreach (Filials::ALL as $key) {
            $item = is_array($filials[$key] ?? null) ? $filials[$key] : [];
            $rows["cert_{$key}_title"] = Validate::str($item, 'title', 100);
            $rows["cert_{$key}_name"] = Validate::str($item, 'name', 100);
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        $db->beginTransaction();
        try {
            foreach ($rows as $key => $value) {
                $value = trim($value);
                $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Response::success(self::loadSettings($db));
    }

    /**
     * Tekshiruv sahifasi uchun: token bo'yicha amaldagi sertifikat va uning
     * egasi (xodim hali ham testdan o'tgan bo'lsa), aks holda null.
     * @return array{certificate: array, user: array}|null
     */
    public static function findByToken(\PDO $db, string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{24}$/', $token)) {
            return null;
        }
        try {
            $stmt = $db->prepare(
                'SELECT u.* FROM certificates c JOIN users u ON u.id = c.user_id
                 WHERE c.verify_token = :t LIMIT 1'
            );
            $stmt->execute(['t' => $token]);
            $user = $stmt->fetch();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$user || $user['rol'] === Roles::SUPER_ADMIN) {
            return null;
        }
        // Hozirgi holat bo'yicha (masalan test natijalari o'chirilgan bo'lsa — endi amal qilmaydi).
        $certificate = self::ensureForUser($db, $user);
        return $certificate ? ['certificate' => $certificate, 'user' => $user] : null;
    }
}
