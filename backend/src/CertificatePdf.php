<?php
declare(strict_types=1);

namespace App;

if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', dirname(__DIR__) . '/fonts/');
}
require_once __DIR__ . '/Vendor/tfpdf/ttfonts.php';
require_once __DIR__ . '/Vendor/tfpdf/tfpdf.php';

/**
 * Sertifikat dizayni (A4 albom) — "sertifikat_shablon_tola_jonli.html" maketining
 * PDF nusxasi. Koordinatalar maketdan brauzerda o'lchab olingan (mm, yuqori-chap
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
    public const VERSION = 3;

    private const BLUE = [0, 67, 145];      // #004391
    private const ACCENT = [46, 134, 224];  // #2E86E0
    private const INK = [20, 32, 58];       // #14203A
    private const MUTED = [98, 113, 138];   // #62718A
    private const RULE = [211, 221, 235];   // #D3DDEB

    private const PT = 25.4 / 72;           // 1pt mm'da

    // Asosiy ustun: chap tasma (76 mm) va ichki chekinishlardan (18 mm) keyin.
    private const X0 = 94.0;
    private const X1 = 279.0;

    // Sarlavha qatori (yuqorida) va imzolar (pastda) orasidagi qism — mazmun
    // shu oraliqda vertikal markazlanadi (maketdagi .body).
    private const BODY_TOP = 30.19;
    private const BODY_BOTTOM = 162.74;

    /** Kichik bosh harfli yorliqlar (.label) harf oralig'i, em. */
    private const LABEL_TRACKING = 0.12;

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

        // Chap tasma (gradient + tolalar naqshi), oq logotip va fondagi juda och
        // to'lqinlar (6% shaffoflik oq fonga oldindan qo'shilgan).
        $this->Image($assets . '/band.jpg', 0, 0, 76, 210);
        $this->Image($assets . '/logo_white.png', 12, 186.65, 48);
        $this->Image($assets . '/waves_bg.png', 178.66, 90, 110.34, 112);

        // Yuqori qator: qalqon belgisi + kurs nomi, o'ngda sertifikat raqami.
        $this->shield(self::X0, 16.34, 9.5);
        $this->label(106.49, 19.05, "O‘quv kursi", 7.5);
        $this->drawText(106.49, 24.74, 'Korrupsiyaga qarshi kurashish', 'bold', 11.5, self::BLUE);
        $this->label(self::X1, 18.91, 'Sertifikat raqami', 7.5, 'R');
        $this->drawText(self::X1, 24.60, $d['number'], 'medium', 11, self::INK, 'R', 0.03);

        // --- Mazmun bloklari: avval o'lchanadi, keyin markazlab chiziladi ---
        $blocks = [];

        $blocks[] = [16.93, function (float $y): void {
            $this->drawText(self::X0, self::baseline($y, 16.93, 48), 'Sertifikat', 'bold', 48, self::BLUE, 'L', -0.015);
        }];
        $blocks[] = [7.0, null];
        $blocks[] = [4.23, function (float $y): void {
            $this->label(self::X0, $y + 2.91, 'Ushbu sertifikat', 7.5);
        }];
        $blocks[] = [1.5, null];

        // F.I.Sh: bir qatorga sig'maguncha 28pt → 18pt; baribir uzun bo'lsa — bir necha qator.
        $width = self::X1 - self::X0;
        $size = 28.0;
        while ($size > 18 && $this->width($d['fish'], 'medium', $size) > $width) {
            $size -= 1;
        }
        $nameLines = $this->wrap([[$d['fish'], 'medium', self::INK]], $size, $width);
        $nameLh = 1.18 * $size * self::PT;
        $blocks[] = [count($nameLines) * $nameLh + 3 + 0.35, function (float $y) use ($nameLines, $nameLh, $size): void {
            foreach ($nameLines as $line) {
                $this->runs(self::X0, self::baseline($y, $nameLh, $size), $line, $size);
                $y += $nameLh;
            }
            $this->gradientRule(self::X0, $y + 3, self::X1 - self::X0, 0.35);
        }];

        // Filial / Bo'linma.
        $rows = [];
        foreach ([['Filial', $d['filial'] ?? ''], ["Bo‘linma", $d['bolinma'] ?? '']] as [$label, $value]) {
            if (trim((string) $value) !== '') {
                $rows[] = [$label, $this->wrap([[(string) $value, 'medium', self::INK]], 9.5, self::X1 - self::X0 - 22)];
            }
        }
        if ($rows) {
            $rowLh = 1.4 * 9.5 * self::PT;
            $orgH = 1.2 * (count($rows) - 1);
            foreach ($rows as [, $lines]) {
                $orgH += count($lines) * $rowLh;
            }
            $blocks[] = [3.5, null];
            $blocks[] = [$orgH, function (float $y) use ($rows, $rowLh): void {
                foreach ($rows as [$label, $lines]) {
                    $this->drawText(self::X0, self::baseline($y, $rowLh, 9.5), $label, 'light', 9.5, self::MUTED);
                    foreach ($lines as $line) {
                        $this->runs(self::X0 + 22, self::baseline($y, $rowLh, 9.5), $line, 9.5);
                        $y += $rowLh;
                    }
                    $y += 1.2;
                }
            }];
        }

        // Asosiy matn.
        $paragraph = $this->wrap([
            ["«O‘zbektelekom» AK tomonidan tashkil etilgan ", 'regular', self::INK],
            ["«Korrupsiyaga qarshi kurashish»", 'bold', self::BLUE],
            [" o‘quv kursini muvaffaqiyatli tamomlagani va yakuniy testdan o‘tgani uchun berildi.", 'regular', self::INK],
        ], 11, 168);
        $textLh = 1.6 * 11 * self::PT;
        $blocks[] = [6.0, null];
        $blocks[] = [count($paragraph) * $textLh, function (float $y) use ($paragraph, $textLh): void {
            foreach ($paragraph as $line) {
                $this->runs(self::X0, self::baseline($y, $textLh, 11), $line, 11);
                $y += $textLh;
            }
        }];

        // Ball, sana (va amal qilish muddati) kartochkalari.
        $facts = [["To‘plangan ball", $d['ball']], ['Berilgan sana', $d['date']]];
        if (!empty($d['validUntil'])) {
            $facts[] = ['Amal qilish muddati', $d['validUntil']];
        }
        $blocks[] = [6.0, null];
        $blocks[] = [17.47, function (float $y) use ($facts): void {
            $x = self::X0;
            foreach ($facts as [$label, $value]) {
                $inner = max($this->labelWidth($label, 7), $this->width((string) $value, 'bold', 16));
                $boxW = max(46.0, 1 + 4.5 + $inner + 4.5);
                $this->factBox($x, $y, $boxW, 17.47);
                $this->label($x + 5.5, $y + 5.64, $label, 7);
                $this->drawText($x + 5.5, $y + 12.79, (string) $value, 'bold', 16, self::BLUE);
                $x += $boxW + 4;
            }
        }];

        $total = array_sum(array_column($blocks, 0));
        $y = self::BODY_TOP + max(0.0, (self::BODY_BOTTOM - self::BODY_TOP - $total) / 2);
        foreach ($blocks as [$h, $paint]) {
            if ($paint !== null) {
                $paint($y);
            }
            $y += $h;
        }

        // Imzolar (sahifa pastiga mahkamlangan): ikki ustun, oralig'i 12 mm.
        $this->SetDrawColor(...self::RULE);
        $this->SetLineWidth(0.25);
        $this->Line(self::X0, 166.865, self::X1, 166.865);
        $colW = (self::X1 - self::X0 - 12) / 2;
        $textW = $colW - 22 - 4.5;
        $labelLh = 1.45 * 7 * self::PT;
        $nameLh = 1.3 * 10.5 * self::PT;
        foreach (array_values($d['signers']) as $i => $signer) {
            $sx = self::X0 + $i * ($colW + 12);
            $this->qr($sx + 1, 173, 20, $signer['qr']);

            $titleLines = $this->wrap([[self::upper((string) $signer['title']), 'regular', self::MUTED]], 7, $textW, self::LABEL_TRACKING);
            $name = trim((string) ($signer['name'] ?? ''));
            $nameLines = $name !== '' ? $this->wrap([[$name, 'medium', self::INK]], 10.5, $textW) : [];
            $h = count($titleLines) * $labelLh + ($nameLines ? 1.2 + count($nameLines) * $nameLh : 0);
            $ty = 172 + (22 - $h) / 2;
            foreach ($titleLines as $line) {
                $this->runs($sx + 26.5, self::baseline($ty, $labelLh, 7), $line, 7, self::LABEL_TRACKING);
                $ty += $labelLh;
            }
            $ty += 1.2;
            foreach ($nameLines as $line) {
                $this->runs($sx + 26.5, self::baseline($ty, $nameLh, 10.5), $line, 10.5);
                $ty += $nameLh;
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

    /** $tracking — CSS letter-spacing (em): har bir belgidan keyin qo'shiladi. */
    private function width(string $text, string $weight, float $size, float $tracking = 0.0): float
    {
        $text = self::normalize($text);
        $this->useFont($weight, $size, $text);
        return $this->GetStringWidth($text) + mb_strlen($text) * $tracking * $size * self::PT;
    }

    private function drawText(float $x, float $baseline, string $text, string $weight, float $size, array $color, string $align = 'L', float $tracking = 0.0): void
    {
        if ($align === 'R') {
            $x -= $this->width($text, $weight, $size, $tracking);
        }
        $text = self::normalize($text);
        $this->useFont($weight, $size, $text);
        $this->SetTextColor(...$color);
        if ($tracking != 0.0) {
            // Tc — PDF belgilar oralig'i (pt), CSS letter-spacing kabi har bir belgiga.
            $this->_out(sprintf('%.3F Tc', $tracking * $size));
        }
        $this->Text($x, $baseline, $text);
        if ($tracking != 0.0) {
            $this->_out('0 Tc');
        }
    }

    private static function upper(string $s): string
    {
        return mb_strtoupper(self::normalize($s), 'UTF-8');
    }

    /** Maketdagi .label: kichik, bosh harflar, keng harf oralig'i, kulrang. */
    private function label(float $x, float $baseline, string $text, float $size, string $align = 'L'): void
    {
        $this->drawText($x, $baseline, self::upper($text), 'regular', $size, self::MUTED, $align, self::LABEL_TRACKING);
    }

    private function labelWidth(string $text, float $size): float
    {
        return $this->width(self::upper($text), 'regular', $size, self::LABEL_TRACKING);
    }

    /**
     * Bir necha uslubdagi bo'laklarni ($runs: [matn, og'irlik, rang]) so'zlar
     * bo'yicha $maxW kenglikdagi qatorlarga ajratadi.
     * @return array<int, array<int, array{0:string,1:string,2:array}>>
     */
    private function wrap(array $runs, float $size, float $maxW, float $tracking = 0.0): array
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
            $w = $this->width($word[0], $word[1], $size, $tracking);
            $trimmedW = $this->width(rtrim($word[0]), $word[1], $size, $tracking);
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

    private function runs(float $x, float $baseline, array $line, float $size, float $tracking = 0.0): void
    {
        foreach ($line as [$text, $weight, $color]) {
            $this->drawText($x, $baseline, $text, $weight, $size, $color, 'L', $tracking);
            $x += $this->width($text, $weight, $size, $tracking);
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

    private static function mix(array $from, array $to, float $t): array
    {
        return array_map(static fn (int $a, int $b): int => (int) round($a + ($b - $a) * $t), $from, $to);
    }

    /**
     * F.I.Sh ostidagi chiziq: chapdan o'ngga #004391 → #2E86E0 (60%) → shaffof
     * (oq fonda — oqqa). PDF'da gradient yo'q — ingichka bo'laklar bilan.
     */
    private function gradientRule(float $x, float $y, float $w, float $h): void
    {
        $steps = (int) ceil($w / 0.5);
        $seg = $w / $steps;
        for ($i = 0; $i < $steps; $i++) {
            $t = ($i + 0.5) / $steps;
            $rgb = $t <= 0.6
                ? self::mix(self::BLUE, self::ACCENT, $t / 0.6)
                : self::blend(self::ACCENT, 1 - ($t - 0.6) / 0.4);
            $this->SetFillColor(...$rgb);
            $this->Rect($x + $i * $seg, $y, $seg + 0.02, $h, 'F');
        }
    }

    /**
     * Ball/sana kartochkasi: 120° gradient fon (#E6EFFB → #F4F8FD), o'ng
     * burchaklari yumaloq (1.6 mm), chapda 1 mm #2E86E0 chiziq.
     */
    private function factBox(float $x, float $y, float $w, float $h): void
    {
        $from = [230, 239, 251];
        $to = [244, 248, 253];
        $this->_out('q');
        $this->roundedPath($x, $y, $w, $h, 0, 1.6);
        $this->_out('W n');
        // CSS gradient chizig'i: yo'nalish (sin120°, −cos120°), uzunligi |w·sin|+|h·cos|.
        [$dx, $dy] = [sin(deg2rad(120)), -cos(deg2rad(120))];
        $len = abs($w * $dx) + abs($h * $dy);
        [$cx, $cy] = [$x + $w / 2, $y + $h / 2];
        $steps = 48;
        $reach = $w + $h;
        for ($i = 0; $i < $steps; $i++) {
            $o0 = ($i / $steps - 0.5) * $len - ($i === 0 ? $reach : 0);
            $o1 = (($i + 1) / $steps - 0.5) * $len + ($i === $steps - 1 ? $reach : 0.05);
            $this->SetFillColor(...self::mix($from, $to, ($i + 0.5) / $steps));
            $corner = fn (float $o, float $n): string => $this->pt($cx + $dx * $o - $dy * $n, $cy + $dy * $o + $dx * $n);
            $this->_out($corner($o0, -$reach) . ' m ' . $corner($o1, -$reach) . ' l ' . $corner($o1, $reach) . ' l ' . $corner($o0, $reach) . ' l h f');
        }
        $this->_out('Q');
        $this->SetFillColor(...self::ACCENT);
        $this->Rect($x, $y, 1, $h, 'F');
    }

    /** To'rtburchak yo'li: chap burchaklar radiusi $rl, o'ng burchaklar — $rr (yo'l chiziladi, bo'yalmaydi). */
    private function roundedPath(float $x, float $y, float $w, float $h, float $rl, float $rr): void
    {
        $k = 0.5523;
        $this->_out($this->pt($x + $rl, $y) . ' m');
        $this->_out($this->pt($x + $w - $rr, $y) . ' l');
        if ($rr > 0) {
            $this->_out($this->pt($x + $w - $rr + $k * $rr, $y) . ' ' . $this->pt($x + $w, $y + $rr - $k * $rr) . ' ' . $this->pt($x + $w, $y + $rr) . ' c');
        }
        $this->_out($this->pt($x + $w, $y + $h - $rr) . ' l');
        if ($rr > 0) {
            $this->_out($this->pt($x + $w, $y + $h - $rr + $k * $rr) . ' ' . $this->pt($x + $w - $rr + $k * $rr, $y + $h) . ' ' . $this->pt($x + $w - $rr, $y + $h) . ' c');
        }
        $this->_out($this->pt($x + $rl, $y + $h) . ' l');
        if ($rl > 0) {
            $this->_out($this->pt($x + $rl - $k * $rl, $y + $h) . ' ' . $this->pt($x, $y + $h - $rl + $k * $rl) . ' ' . $this->pt($x, $y + $h - $rl) . ' c');
        }
        $this->_out($this->pt($x, $y + $rl) . ' l');
        if ($rl > 0) {
            $this->_out($this->pt($x, $y + $rl - $k * $rl) . ' ' . $this->pt($x + $rl - $k * $rl, $y) . ' ' . $this->pt($x + $rl, $y) . ' c');
        }
        $this->_out('h');
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
