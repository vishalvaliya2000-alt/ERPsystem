<?php

namespace App\Services;

class ColorPaletteService
{
    /**
     * Convert HEX color string to HSL [h (0-360), s (0-100), l (0-100)]
     */
    public static function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r:
                    $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $d + 2;
                    break;
                case $b:
                    $h = ($r - $g) / $d + 4;
                    break;
            }
            $h /= 6;
        }

        return [round($h * 360, 1), round($s * 100, 1), round($l * 100, 1)];
    }

    /**
     * Convert HSL to 6-character HEX color string
     */
    public static function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return sprintf('#%02x%02x%02x', round($r * 255), round($g * 255), round($b * 255));
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    /**
     * Generate complete 10-shade Tailwind color palette (50-900) from a base HEX color
     */
    public static function generateShades(string $baseHex): array
    {
        [$h, $s, $l] = self::hexToHsl($baseHex);

        $lightnessMap = [
            50 => 96,
            100 => 91,
            200 => 82,
            300 => 71,
            400 => 58,
            500 => 48,
            600 => 38,
            700 => 29,
            800 => 20,
            900 => 12,
        ];

        $shades = [];
        foreach ($lightnessMap as $weight => $targetL) {
            // Keep subtle saturation in light shades for richness
            $adjustedS = $weight <= 100 ? min($s, 85) : $s;
            $shades[$weight] = self::hslToHex($h, $adjustedS, $targetL);
        }

        return $shades;
    }

    /**
     * Extract dominant primary and vibrant accent colors from an image file using GD
     */
    public static function extractFromImage(string $filePath): array
    {
        if (! file_exists($filePath) || ! extension_loaded('gd')) {
            return [
                'primary_color' => '#ed1819',
                'accent_color' => '#f03b3c',
            ];
        }

        $info = @getimagesize($filePath);
        if (! $info) {
            return [
                'primary_color' => '#ed1819',
                'accent_color' => '#f03b3c',
            ];
        }

        $mime = $info['mime'];
        $src = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : null,
            default => null,
        };

        if (! $src) {
            return [
                'primary_color' => '#ed1819',
                'accent_color' => '#f03b3c',
            ];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $colorBuckets = [];
        $strideX = max(1, (int) ($w / 160));
        $strideY = max(1, (int) ($h / 160));

        for ($x = 0; $x < $w; $x += $strideX) {
            for ($y = 0; $y < $h; $y += $strideY) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                // Skip transparent pixels
                if ($alpha > 90) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                // Skip white, off-white, and pale washed-out backgrounds
                if (($r > 210 && $g > 210 && $b > 210) || ($r > 195 && $g > 195 && $b > 195 && abs($r - $g) < 15 && abs($g - $b) < 15)) {
                    continue;
                }

                // Quantize to step of 16 for clustering
                $qr = min(255, max(0, (int) (round($r / 16) * 16)));
                $qg = min(255, max(0, (int) (round($g / 16) * 16)));
                $qb = min(255, max(0, (int) (round($b / 16) * 16)));

                $hex = sprintf('#%02x%02x%02x', $qr, $qg, $qb);
                $colorBuckets[$hex] = ($colorBuckets[$hex] ?? 0) + 1;
            }
        }

        arsort($colorBuckets);

        // Filter extracted buckets to ensure we have vivid or solid colors
        $validColors = [];
        foreach (array_keys($colorBuckets) as $candidateHex) {
            [$ch, $cs, $cl] = self::hexToHsl($candidateHex);
            // Ignore near-white pastels and pale tints (lightness > 78%)
            if ($cl > 78) {
                continue;
            }
            $validColors[] = $candidateHex;
        }

        if (empty($validColors)) {
            return [
                'primary_color' => '#4f46e5',
                'accent_color' => '#6366f1',
            ];
        }

        // Primary color: the most dominant valid color
        $primary = $validColors[0];

        // Accent color: look for a distinct hue or a vibrant accent
        $accent = null;
        [$h1, $s1, $l1] = self::hexToHsl($primary);

        foreach (array_slice($validColors, 1) as $candidateHex) {
            [$h2, $s2, $l2] = self::hexToHsl($candidateHex);

            $hueDiff = abs($h1 - $h2);
            if ($hueDiff > 180) {
                $hueDiff = 360 - $hueDiff;
            }
            $lightDiff = abs($l1 - $l2);

            // Prefer saturated accent with hue separation or significant lightness contrast
            if (($s2 >= 20 && $hueDiff >= 25) || ($lightDiff >= 25 && $s2 >= 15)) {
                $accent = $candidateHex;
                break;
            }
        }

        // If primary is dark and accent is dark, find the most vibrant or lighter candidate
        if (! $accent || (self::hexToHsl($accent)[2] < 30 && $l1 < 30)) {
            foreach ($validColors as $candidateHex) {
                [$h2, $s2, $l2] = self::hexToHsl($candidateHex);
                if ($candidateHex !== $primary && ($s2 > 25 || $l2 > 45)) {
                    $accent = $candidateHex;
                    break;
                }
            }
        }

        if (! $accent) {
            $accent = count($validColors) > 1 ? $validColors[1] : self::hslToHex(($h1 + 180) % 360, max(50, $s1), 50);
        }

        return [
            'primary_color' => $primary,
            'accent_color' => $accent,
        ];
    }
}
