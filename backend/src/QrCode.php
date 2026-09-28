<?php
declare(strict_types=1);

namespace App;

/**
 * Minimal QR-kod generatori (ISO/IEC 18004): bayt rejimi, xatolikni tuzatish
 * darajasi M, 1–10-versiyalar (~210 baytgacha — havola uchun yetarli).
 * Natija — modullar matritsasi (true = qora), chizish chaqiruvchining ishi.
 */
final class QrCode
{
    /** Versiya => [bloklar soni, har bir blokdagi EC kodso'zlar, jami kodso'zlar] (M darajasi). */
    private const BLOCKS_M = [
        1 => [1, 10, 26], 2 => [1, 16, 44], 3 => [1, 26, 70], 4 => [2, 18, 100], 5 => [2, 24, 134],
        6 => [4, 16, 172], 7 => [4, 18, 196], 8 => [4, 22, 242], 9 => [5, 22, 292], 10 => [5, 26, 346],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private int $size;
    /** @var bool[][] [y][x] */
    private array $modules = [];
    /** @var bool[][] [y][x] */
    private array $isFunction = [];

    /** @return bool[][] [y][x] — true qora modul */
    public static function matrix(string $data): array
    {
        $qr = new self();
        return $qr->encode($data);
    }

    private function encode(string $data): array
    {
        $len = strlen($data);
        $version = null;
        foreach (self::BLOCKS_M as $v => [$blocks, $ecLen, $total]) {
            $capacityBits = ($total - $blocks * $ecLen) * 8;
            $countBits = $v <= 9 ? 8 : 16;
            if (4 + $countBits + $len * 8 <= $capacityBits) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new \InvalidArgumentException('QR uchun matn juda uzun');
        }

        [$numBlocks, $ecLen, $totalCodewords] = self::BLOCKS_M[$version];
        $dataCodewords = $totalCodewords - $numBlocks * $ecLen;

        // Bit oqimi: rejim (0100 — bayt), uzunlik, ma'lumot, terminator, to'ldirish.
        $bits = [];
        $push = static function (int $value, int $n) use (&$bits): void {
            for ($i = $n - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $push(0b0100, 4);
        $push($len, $version <= 9 ? 8 : 16);
        for ($i = 0; $i < $len; $i++) {
            $push(ord($data[$i]), 8);
        }
        $capacityBits = $dataCodewords * 8;
        $push(0, min(4, $capacityBits - count($bits)));
        $push(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacityBits; $pad ^= 0xEC ^ 0x11) {
            $push($pad, 8);
        }
        $codewords = [];
        foreach (array_chunk($bits, 8) as $byteBits) {
            $b = 0;
            foreach ($byteBits as $bit) {
                $b = ($b << 1) | $bit;
            }
            $codewords[] = $b;
        }

        $allCodewords = self::addEccAndInterleave($codewords, $numBlocks, $ecLen, $totalCodewords);

        $this->size = $version * 4 + 17;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = $this->modules;
        $this->drawFunctionPatterns($version);
        $this->drawCodewords($allCodewords);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penalty();
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
            $this->applyMask($mask); // XOR — qayta qo'llash bekor qiladi
        }
        $this->applyMask($bestMask);
        $this->drawFormatBits($bestMask);

        return $this->modules;
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(int $version): void
    {
        $size = $this->size;
        for ($i = 0; $i < $size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                        $dist = max(abs($dx), abs($dy));
                        $this->set($x, $y, $dist !== 2 && $dist !== 4);
                    }
                }
            }
        }
        $pos = self::ALIGNMENT[$version];
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0); // joyni band qilish; haqiqiy qiymat niqob tanlangach yoziladi
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (0b00 << 3) | $mask; // M darajasi = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;
        $size = $this->size;

        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $size - 15 + $i, $bit($i));
        }
        $this->set(8, $size - 8, true);
    }

    private function drawCodewords(array $codewords): void
    {
        $size = $this->size;
        $totalBits = count($codewords) * 8;
        $i = 0;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Standart jarima qoidalari (1–4) — eng "o'qilishi oson" niqobni tanlash uchun. */
    private function penalty(): int
    {
        $size = $this->size;
        $m = $this->modules;
        $result = 0;

        $lines = [];
        for ($i = 0; $i < $size; $i++) {
            $row = [];
            $col = [];
            for ($j = 0; $j < $size; $j++) {
                $row[] = $m[$i][$j];
                $col[] = $m[$j][$i];
            }
            $lines[] = $row;
            $lines[] = $col;
        }
        $finderA = [true, false, true, true, true, false, true, false, false, false, false];
        $finderB = array_reverse($finderA);
        foreach ($lines as $line) {
            $run = 1;
            for ($j = 1; $j <= $size; $j++) {
                if ($j < $size && $line[$j] === $line[$j - 1]) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $result += 3 + ($run - 5);
                }
                $run = 1;
            }
            // Chetdan tashqarini (sokin zona) oq deb hisoblaymiz.
            $padded = array_merge(array_fill(0, 4, false), $line, array_fill(0, 4, false));
            $n = count($padded);
            for ($j = 0; $j + 11 <= $n; $j++) {
                $slice = array_slice($padded, $j, 11);
                if ($slice === $finderA || $slice === $finderB) {
                    $result += 40;
                }
            }
        }

        $dark = 0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($m[$y][$x]) {
                    $dark++;
                }
                if ($x < $size - 1 && $y < $size - 1) {
                    $c = $m[$y][$x];
                    if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                        $result += 3;
                    }
                }
            }
        }
        $total = $size * $size;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $result + max(0, $k) * 10;
    }

    private static function addEccAndInterleave(array $data, int $numBlocks, int $ecLen, int $rawCodewords): array
    {
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);
        $divisor = self::rsDivisor($ecLen);

        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortBlockLen - $ecLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $ecc = self::rsRemainder($dat, $divisor);
            if ($i < $numShortBlocks) {
                $dat[] = 0;
            }
            $blocks[] = array_merge($dat, $ecc);
        }

        $result = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $shortBlockLen - $ecLen || $j >= $numShortBlocks) {
                    $result[] = $block[$i];
                }
            }
        }
        return $result;
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::gfMul($coef, $factor);
            }
        }
        return $result;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }
}
