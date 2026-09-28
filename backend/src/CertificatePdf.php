<?php
declare(strict_types=1);

namespace App;

if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', dirname(__DIR__) . '/fonts/');
}
require_once __DIR__ . '/Vendor/tfpdf/ttfonts.php';
require_once __DIR__ . '/Vendor/tfpdf/tfpdf.php';

/**
 * Sertifikat dizayni (A4 albom) — "sertifikat_shablon.html" maketining PDF
 * nusxasi. Koordinatalar maketdan brauzerda o'lchab olingan (mm, yuqori-chap
 * burchakdan). Dizayn almashtirilsa, faqat shu fayl (va
 * backend/certificate_assets/) o'zgaradi — ma'lumotlarni
 * CertificateController tayyorlab beradi.
 *
 * Shrift: Poppins (OFL). Poppins'da kirill harflari yo'q — bunday matn
 * (masalan kirillcha F.I.Sh) avtomatik DejaVu Sans bilan yoziladi.
 */
final class CertificatePdf extends \tFPDF
{
    /** Dizayn o'zgarsa oshiriladi — mavjud sertifikatlar qayta yaratiladi. */
    public const VERSION = 2;

    private const BLUE = [0, 67, 145];      // #004391
    private const INK = [22, 34, 58];       // #16223A
    private const MUTED = [90, 106, 131];   // #5A6A83
    private const TINT = [237, 242, 249];   // #EDF2F9
    private const LINE = [201, 214, 232];   // #C9D6E8

    private const PT = 25.4 / 72;           // 1pt mm'da

    // Asosiy ustun: chap tasmadan keyin, ichki chekinishlar bilan.
    private const X0 = 91.0;
    private const X1 = 279.0;

    /** Og'irlik => [Poppins oilasi, fayl, zaxira (DejaVu) oilasi, fayl] */
    private const FONTS = [
        'light' => ['PoppinsLight', 'Poppins-Light.ttf', 'DejaVuSans', 'DejaVuSans.ttf'],
        'regular' => ['PoppinsRegular', 'Poppins-Regular.ttf', 'DejaVuSans', 'DejaVuSans.ttf'],
        'medium' => ['PoppinsMedium', 'Poppins-Medium.ttf', 'DejaVuSans', 'DejaVuSans.ttf'],
        'bold' => ['PoppinsBold', 'Poppins-Bold.ttf', 'DejaVuSansBold', 'DejaVuSans-Bold.ttf'],
    ];

    /**
     * $d kalitlari: number, fish, filial (?string), bolinma (?string), ball,
     * date, validUntil (?string), signers — [['title' => ..., 'name' => ..., 'qr' => url], ...] (1–2 ta).
     */
    public static function render(array $d): string
    {
        $pdf = new self('L', 'mm', 'A4');
        $pdf->SetTitle('Sertifikat — ' . $d['fish'], true);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage();
        // Oila nomlarini o'zgartirmang: tFPDF shrift keshi (fonts/unifont/*.mtx.php)
        // birinchi ishlatilgan oila nomini saqlab qoladi.
        foreach (self::FONTS as [$family, $file, $fbFamily, $fbFile]) {
            $pdf->AddFont($family, '', $file, true);
            $pdf->AddFont($fbFamily, '', $fbFile, true);
        }
        $pdf->draw($d);
        return $pdf->Output('S');
    }

    private function draw(array $d): void
    {
        $assets = dirname(__DIR__) . '/certificate_assets';

        // Chap tasma (gradient + signal yoylari) va oq logotip.
        $this->Image($assets . '/band.jpg', 0, 0, 74, 210);
        $this->Image($assets . '/logo_white.png', 11, 188.35, 50);

        // Fondagi juda och yoylar (maketda #004391, shaffofligi 3–4.5%).
        foreach ([[28.5, 0.045], [49.4, 0.04], [70.3, 0.035], [91.2, 0.03]] as [$r, $alpha]) {
            $this->SetDrawColor(...self::blend(self::BLUE, $alpha));
            $this->SetLineWidth(6.65);
            $this->circle(262, 185, $r);
        }

        // Yuqori qator: qalqon belgisi + kurs nomi, o'ngda sertifikat raqami.
        $this->shield(91, 15, 11);
        $this->drawText(105.49, 18.79, "O‘quv kursi", 'light', 8.5, self::MUTED);
        $this->drawText(105.49, 24.29, 'Korrupsiyaga qarshi kurashish', 'bold', 12.5, self::BLUE);
        $this->drawText(self::X1, 18.30, 'Sertifikat raqami', 'light', 8.5, self::MUTED, 'R');
        $this->drawText(self::X1, 23.57, $d['number'], 'medium', 10.5, self::INK, 'R');

        $this->drawText(self::X0, 50.98, 'Sertifikat', 'bold', 40, self::BLUE);
        $this->drawText(self::X0, 63.17, 'Ushbu sertifikat', 'light', 10.5, self::MUTED);

        // F.I.Sh: bir qatorga sig'maguncha 25pt → 16pt; baribir uzun bo'lsa — ikki qator.
        $width = self::X1 - self::X0;
        $size = 25.0;
        while ($size > 16 && $this->width($d['fish'], 'medium', $size) > $width) {
            $size -= 0.5;
        }
        $lines = $this->wrap([[$d['fish'], 'medium', self::INK]], $size, $width);
        $lineH = 1.15 * $size * self::PT;
        $y = 66.65;
        foreach ($lines as $line) {
            $this->runs(self::X0, self::baseline($y, $lineH, $size), $line, $size);
            $y += $lineH;
        }
        $y += 3.5;
        $this->SetDrawColor(...self::BLUE);
        $this->SetLineWidth(0.4);
        $this->Line(self::X0, $y + 0.2, self::X1, $y + 0.2);
        $y += 0.4 + 3.5;

        // Filial / Bo'linma.
        $rows = [];
        if (!empty($d['filial'])) {
            $rows[] = ['Filial', $d['filial']];
        }
        if (!empty($d['bolinma'])) {
            $rows[] = ["Bo‘linma", $d['bolinma']];
        }
        if ($rows) {
            $labelW = 0.0;
            foreach ($rows as [$label]) {
                $labelW = max($labelW, $this->width($label, 'light', 9.5));
            }
            $valueX = self::X0 + $labelW + 4;
            $rowH = 4.76;
            foreach ($rows as $i => [$label, $value]) {
                $valueLines = $this->wrap([[$value, 'medium', self::INK]], 9.5, self::X1 - $valueX);
                $this->drawText(self::X0, self::baseline($y, $rowH, 9.5), $label, 'light', 9.5, self::MUTED);
                foreach ($valueLines as $line) {
                    $this->runs($valueX, self::baseline($y, $rowH, 9.5), $line, 9.5);
                    $y += $rowH;
                }
                if ($i < count($rows) - 1) {
                    $y += 1;
                }
            }
        }

        // Asosiy matn.
        $y += 5;
        $paragraph = [
            ["«O‘zbektelekom» AK tomonidan tashkil etilgan ", 'regular', self::INK],
            ["«Korrupsiyaga qarshi kurashish»", 'bold', self::BLUE],
            [" o‘quv kursini muvaffaqiyatli tamomlagani va yakuniy testdan o‘tgani uchun berildi.", 'regular', self::INK],
        ];
        $lineH = 1.55 * 11 * self::PT;
        foreach ($this->wrap($paragraph, 11, 185) as $line) {
            $this->runs(self::X0, self::baseline($y, $lineH, 11), $line, 11);
            $y += $lineH;
        }

        // Ball va sana kartochkalari.
        $y += 6;
        $x = self::X0;
        $facts = [["To‘plangan ball", $d['ball']], ['Berilgan sana', $d['date']]];
        if (!empty($d['validUntil'])) {
            $facts[] = ['Amal qiladi', $d['validUntil'] . ' gacha'];
        }
        foreach ($facts as [$label, $value]) {
            $boxW = max(40.0, max($this->width($label, 'light', 8), $this->width($value, 'bold', 15)) + 10);
            $this->SetFillColor(...self::TINT);
            $this->roundedRect($x, $y, $boxW, 17.11, 2);
            $this->drawText($x + 5, $y + 6.10, $label, 'light', 8, self::MUTED);
            $this->drawText($x + 5, $y + 12.52, $value, 'bold', 15, self::BLUE);
            $x += $boxW + 4;
        }

        // Imzolar (sahifa pastiga mahkamlangan).
        $this->SetDrawColor(...self::LINE);
        $this->SetLineWidth(0.25);
        $this->Line(self::X0, 170.87, self::X1, 170.87);
        foreach (array_values($d['signers']) as $i => $signer) {
            $sx = self::X0 + $i * 99;
            $this->qr($sx, 176, 21, $signer['qr']);
            $name = trim((string) ($signer['name'] ?? ''));
            if ($name !== '') {
                $this->drawText($sx + 24.99, 185.10, $signer['title'], 'light', 8, self::MUTED);
                $this->drawText($sx + 24.99, 189.86, $name, 'medium', 10, self::INK);
            } else {
                $this->drawText($sx + 24.99, 187.60, $signer['title'], 'light', 8, self::MUTED);
            }
        }
    }

    // ------------------------------------------------------------------
    // Matn yordamchilari
    // ------------------------------------------------------------------

    /** Brauzer qator qutisi (yuqori, balandlik) bo'yicha matn tayanch chizig'i. Poppins: ascent 1.05em, descent 0.35em. */
    private static function baseline(float $top, float $lineH, float $sizePt): float
    {
        $em = $sizePt * self::PT;
        return $top + ($lineH - 1.4 * $em) / 2 + 1.05 * $em;
    }

    private static function normalize(string $s): string
    {
        // O'zbek lotin yozuvidagi ʻ/ʼ belgilari Poppins'da yo'q — ko'rinishi bir xil ‘/’ ga almashtiramiz.
        return strtr($s, ["\u{02BB}" => "\u{2018}", "\u{02BC}" => "\u{2019}", "`" => "\u{2018}"]);
    }

    /** Poppins barcha belgilarni o'z ichiga olsa uni, aks holda DejaVu Sans'ni tanlaydi. */
    private function useFont(string $weight, float $size, string $text): void
    {
        [$family, , $fallback] = self::FONTS[$weight];
        $this->SetFont($family, '', $size);
        $cw = $this->CurrentFont['cw'];
        foreach ($this->UTF8StringToArray($text) as $code) {
            if ($code > 32 && (!isset($cw[2 * $code + 1]) || ($cw[2 * $code] === "\0" && $cw[2 * $code + 1] === "\0"))) {
                $this->SetFont($fallback, '', $size);
                return;
            }
        }
    }

    private function width(string $text, string $weight, float $size): float
    {
        $text = self::normalize($text);
        $this->useFont($weight, $size, $text);
        return $this->GetStringWidth($text);
    }

    private function drawText(float $x, float $baseline, string $text, string $weight, float $size, array $color, string $align = 'L'): void
    {
        $text = self::normalize($text);
        $this->useFont($weight, $size, $text);
        $this->SetTextColor(...$color);
        if ($align === 'R') {
            $x -= $this->GetStringWidth($text);
        }
        $this->Text($x, $baseline, $text);
    }

    /**
     * Bir necha uslubdagi bo'laklarni ($runs: [matn, og'irlik, rang]) so'zlar
     * bo'yicha $maxW kenglikdagi qatorlarga ajratadi.
     * @return array<int, array<int, array{0:string,1:string,2:array}>>
     */
    private function wrap(array $runs, float $size, float $maxW): array
    {
        $words = [];
        foreach ($runs as [$text, $weight, $color]) {
            foreach (preg_split('/(?<= )/u', self::normalize($text)) ?: [] as $word) {
                if ($word !== '') {
                    $words[] = [$word, $weight, $color];
                }
            }
        }
        $lines = [];
        $line = [];
        $lineW = 0.0;
        foreach ($words as $word) {
            $w = $this->width($word[0], $word[1], $size);
            $trimmedW = $this->width(rtrim($word[0]), $word[1], $size);
            if ($line && $lineW + $trimmedW > $maxW) {
                $lines[] = $line;
                $line = [];
                $lineW = 0.0;
            }
            $line[] = $word;
            $lineW += $w;
        }
        if ($line) {
            $lines[] = $line;
        }
        return $lines;
    }

    private function runs(float $x, float $baseline, array $line, float $size): void
    {
        foreach ($line as [$text, $weight, $color]) {
            $this->drawText($x, $baseline, $text, $weight, $size, $color);
            $x += $this->width($text, $weight, $size);
        }
    }

    // ------------------------------------------------------------------
    // Grafika yordamchilari (tFPDF'da yo'q primitivlar)
    // ------------------------------------------------------------------

    private static function blend(array $rgb, float $alpha): array
    {
        return array_map(static fn (int $c): int => (int) round(255 - (255 - $c) * $alpha), $rgb);
    }

    private function pt(float $x, float $y): string
    {
        return sprintf('%.3F %.3F', $x * $this->k, ($this->h - $y) * $this->k);
    }

    /** Aylana (faqat chiziq) — 4 ta Bezier egri chizig'i bilan. */
    private function circle(float $cx, float $cy, float $r): void
    {
        $c = 0.5523 * $r;
        $this->_out($this->pt($cx + $r, $cy) . ' m');
        $this->_out($this->pt($cx + $r, $cy - $c) . ' ' . $this->pt($cx + $c, $cy - $r) . ' ' . $this->pt($cx, $cy - $r) . ' c');
        $this->_out($this->pt($cx - $c, $cy - $r) . ' ' . $this->pt($cx - $r, $cy - $c) . ' ' . $this->pt($cx - $r, $cy) . ' c');
        $this->_out($this->pt($cx - $r, $cy + $c) . ' ' . $this->pt($cx - $c, $cy + $r) . ' ' . $this->pt($cx, $cy + $r) . ' c');
        $this->_out($this->pt($cx + $c, $cy + $r) . ' ' . $this->pt($cx + $r, $cy + $c) . ' ' . $this->pt($cx + $r, $cy) . ' c');
        $this->_out('S');
    }

    /** Burchaklari yumaloq to'ldirilgan to'rtburchak. */
    private function roundedRect(float $x, float $y, float $w, float $h, float $r): void
    {
        $c = 0.5523 * $r;
        $this->_out($this->pt($x + $r, $y) . ' m');
        $this->_out($this->pt($x + $w - $r, $y) . ' l');
        $this->_out($this->pt($x + $w - $r + $c, $y) . ' ' . $this->pt($x + $w, $y + $r - $c) . ' ' . $this->pt($x + $w, $y + $r) . ' c');
        $this->_out($this->pt($x + $w, $y + $h - $r) . ' l');
        $this->_out($this->pt($x + $w, $y + $h - $r + $c) . ' ' . $this->pt($x + $w - $r + $c, $y + $h) . ' ' . $this->pt($x + $w - $r, $y + $h) . ' c');
        $this->_out($this->pt($x + $r, $y + $h) . ' l');
        $this->_out($this->pt($x + $r - $c, $y + $h) . ' ' . $this->pt($x, $y + $h - $r + $c) . ' ' . $this->pt($x, $y + $h - $r) . ' c');
        $this->_out($this->pt($x, $y + $r) . ' l');
        $this->_out($this->pt($x, $y + $r - $c) . ' ' . $this->pt($x + $r - $c, $y) . ' ' . $this->pt($x + $r, $y) . ' c');
        $this->_out('f');
    }

    /** Maketdagi qalqon + belgi (viewBox 44×44). */
    private function shield(float $x, float $y, float $size): void
    {
        $s = $size / 44;
        $p = fn (float $px, float $py): string => $this->pt($x + $px * $s, $y + $py * $s);

        $this->SetFillColor(...self::BLUE);
        $this->_out($p(22, 3) . ' m ' . $p(38, 9) . ' l ' . $p(38, 21) . ' l');
        $this->_out($p(38, 31) . ' ' . $p(31, 38) . ' ' . $p(22, 41) . ' c');
        $this->_out($p(13, 38) . ' ' . $p(6, 31) . ' ' . $p(6, 21) . ' c');
        $this->_out($p(6, 9) . ' l h f');

        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(3.4 * $s);
        $this->_out('1 J 1 j');
        $this->_out($p(14.5, 22) . ' m ' . $p(20, 27.5) . ' l ' . $p(30, 16.5) . ' l S');
        $this->_out('0 J 0 j');
    }

    /** QR-kod (vektor): qora modullar qator bo'yicha birlashtirilib chiziladi. */
    private function qr(float $x, float $y, float $size, string $data): void
    {
        $m = QrCode::matrix($data);
        $n = count($m);
        $cell = $size / $n;
        $this->SetFillColor(...self::INK);
        foreach ($m as $row => $cols) {
            $start = null;
            for ($col = 0; $col <= $n; $col++) {
                $dark = $col < $n && $cols[$col];
                if ($dark && $start === null) {
                    $start = $col;
                } elseif (!$dark && $start !== null) {
                    // Qo'shni modullar orasida ingichka oq chiziq qolmasligi uchun ozgina ustma-ust.
                    $this->Rect($x + $start * $cell, $y + $row * $cell, ($col - $start) * $cell, $cell + 0.01, 'F');
                    $start = null;
                }
            }
        }
    }
}
