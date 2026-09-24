<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Filials;
use App\Util;

/**
 * Testdan o'tish balini qo'lga kiritgan xodimga beriladigan sertifikat (PDF).
 * Xodimda doim faqat bitta amaldagi sertifikat bo'ladi — eng so'nggi
 * muvaffaqiyatli urinishga tegishli. Yangi muvaffaqiyatli urinish yoki
 * F.I.Sh/filial o'zgarishi sertifikatni qayta yaratadi (sana doim testdan
 * o'tilgan kun). Fayllar backend/certificates/ ichida (public/ dan tashqarida)
 * saqlanadi va faqat certificate-download.php orqali beriladi.
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
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB';

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
        return self::latestPassingAttempt($db, $userId) !== null;
    }

    private static function latestPassingAttempt(\PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare(
            'SELECT id, attempted_at FROM test_attempts
             WHERE user_id = :uid AND passed = 1
             ORDER BY attempted_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Xodimning amaldagi sertifikati (kerak bo'lsa shu yerda yaratib/yangilab).
     * Testdan hali muvaffaqiyatli o'tmagan bo'lsa null. $user — users jadvalidagi
     * to'liq qator (familiya, ism, otasining_ismi, filial).
     */
    public static function ensureForUser(\PDO $db, array $user): ?array
    {
        $userId = (int) $user['id'];
        $attempt = self::latestPassingAttempt($db, $userId);
        if (!$attempt) {
            return null;
        }

        Util::ensureSchema($db, self::DDL);
        $fish = Util::fullName($user);
        $filial = ($user['filial'] ?? null) ?: null;
        $dir = self::certificatesDir();

        $stmt = $db->prepare('SELECT * FROM certificates WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $existing = $stmt->fetch() ?: null;

        if (
            $existing
            && (int) $existing['test_attempt_id'] === (int) $attempt['id']
            && $existing['fish'] === $fish
            && $existing['filial'] === $filial
            && is_file($dir . '/' . $existing['file_name'])
        ) {
            return $existing;
        }

        $fileName = bin2hex(random_bytes(16)) . '.pdf';
        $issuedAt = (string) $attempt['attempted_at'];
        self::renderPdf($dir . '/' . $fileName, $fish, Filials::labelUz($filial), $issuedAt, (int) $attempt['id']);

        try {
            $upsert = $db->prepare(
                'INSERT INTO certificates (user_id, test_attempt_id, fish, filial, file_name, issued_at)
                 VALUES (:uid, :aid, :fish, :filial, :file, :issued)
                 ON DUPLICATE KEY UPDATE test_attempt_id = :aid2, fish = :fish2, filial = :filial2,
                                         file_name = :file2, issued_at = :issued2'
            );
            $upsert->execute([
                'uid' => $userId,
                'aid' => $attempt['id'], 'aid2' => $attempt['id'],
                'fish' => $fish, 'fish2' => $fish,
                'filial' => $filial, 'filial2' => $filial,
                'file' => $fileName, 'file2' => $fileName,
                'issued' => $issuedAt, 'issued2' => $issuedAt,
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
        ];
    }

    private static function renderPdf(string $path, string $fish, ?string $filialLabel, string $issuedAt, int $number): void
    {
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', dirname(__DIR__, 2) . '/fonts/');
        }
        require_once dirname(__DIR__) . '/Vendor/tfpdf/ttfonts.php';
        require_once dirname(__DIR__) . '/Vendor/tfpdf/tfpdf.php';

        $navy = [22, 50, 92];
        $azure = [0, 163, 224];
        $grey = [95, 105, 120];

        $pdf = new \tFPDF('L', 'mm', 'A4');
        $pdf->SetTitle('Sertifikat — ' . $fish, true);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage();
        // Oila nomini o'zgartirmang: tFPDF shrift keshi (fonts/unifont/*.mtx.php)
        // birinchi ishlatilgan oila nomini saqlab qoladi va keyin shuni majburlaydi.
        $pdf->AddFont('DejaVuSerif', '', 'DejaVuSerif.ttf', true);
        $pdf->AddFont('DejaVuSerif', 'B', 'DejaVuSerif-Bold.ttf', true);

        $pageW = 297;

        $pdf->SetDrawColor(...$azure);
        $pdf->SetLineWidth(2);
        $pdf->Rect(10, 10, 277, 190);
        $pdf->SetDrawColor(...$navy);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(15, 15, 267, 180);

        $pdf->SetTextColor(...$navy);
        $pdf->SetFont('DejaVuSerif', 'B', 42);
        $pdf->SetXY(0, 36);
        $pdf->Cell($pageW, 18, 'SERTIFIKAT', 0, 0, 'C');

        $pdf->SetDrawColor(...$azure);
        $pdf->SetLineWidth(0.8);
        $pdf->Line($pageW / 2 - 35, 60, $pageW / 2 + 35, 60);

        $pdf->SetTextColor(...$grey);
        $pdf->SetFont('DejaVuSerif', '', 15);
        $pdf->SetXY(0, 72);
        $pdf->Cell($pageW, 8, 'Ushbu sertifikat', 0, 0, 'C');

        // Uzun F.I.Sh sahifadan chiqib ketmasligi uchun shrift o'lchami moslashtiriladi.
        $nameSize = 30;
        $pdf->SetFont('DejaVuSerif', 'B', $nameSize);
        while ($nameSize > 14 && $pdf->GetStringWidth($fish) > 245) {
            $nameSize--;
            $pdf->SetFont('DejaVuSerif', 'B', $nameSize);
        }
        $pdf->SetTextColor(...$navy);
        $pdf->SetXY(0, 84);
        $pdf->Cell($pageW, 14, $fish, 0, 0, 'C');

        $bodyY = 110;
        if ($filialLabel !== null) {
            $pdf->SetTextColor(...$azure);
            $pdf->SetFont('DejaVuSerif', '', 16);
            $pdf->SetXY(0, 101);
            $pdf->Cell($pageW, 8, $filialLabel, 0, 0, 'C');
            $bodyY = 118;
        }

        $pdf->SetTextColor(...$grey);
        $pdf->SetFont('DejaVuSerif', '', 14);
        $pdf->SetXY(38, $bodyY);
        $pdf->MultiCell(
            $pageW - 76,
            8,
            "korrupsiyaga qarshi kurashish bo'yicha ichki normativ hujjatlar yuzasidan o'tkazilgan testni muvaffaqiyatli topshirganligini tasdiqlaydi.",
            0,
            'C'
        );

        $pdf->SetDrawColor(...$grey);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(35, 170, 262, 170);

        $pdf->SetFont('DejaVuSerif', '', 12);
        $pdf->SetTextColor(...$grey);
        $pdf->SetXY(35, 174);
        $pdf->Cell(110, 7, 'Berilgan sana: ' . date('d.m.Y', strtotime($issuedAt)), 0, 0, 'L');
        $pdf->SetXY(152, 174);
        $pdf->Cell(110, 7, '№ ' . str_pad((string) $number, 6, '0', STR_PAD_LEFT), 0, 0, 'R');

        $pdf->Output('F', $path);
    }
}
