<?php

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Burns two kinds of marks into a page image, so a screenshot or photo of a page always carries where it came from:
 *  - the student's name and login (email or phone): a few light diagonal marks;
 *  - "Created by - MyMerchantNavy.com": one larger diagonal mark across the middle of the page.
 * Both are faint (about 9-10% opaque), so the text under them stays easy to read.
 */
class WatermarkService
{
    public const BRAND = 'Created by - MyMerchantNavy.com';

    /** @return string PNG bytes */
    public function apply(string $pngPath, string $text): string
    {
        $image = imagecreatefrompng($pngPath) ?: throw new RuntimeException('Could not read page image.');
        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $size = max(12, (int) ($width / 50));
        $font = $this->font();
        $color = imagecolorallocatealpha($image, 11, 30, 61, 116); // navy, about 9% opaque

        // Four marks per page, staggered around the brand mark in the middle.
        foreach ([[0.05, 0.28], [0.45, 0.18], [0.05, 0.82], [0.45, 0.96]] as [$fx, $fy]) {
            $x = (int) ($width * $fx);
            $y = (int) ($height * $fy);
            if ($font) {
                imagettftext($image, $size, 30, $x, $y, $color, $font, $text);
            } else {
                imagestring($image, 5, $x, $y, $text, $color); // plain bitmap font fallback
            }
        }

        $this->brandMark($image, $width, $height, $font);

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return ob_get_clean();
    }

    /** "Created by - MyMerchantNavy.com", one line at 30 degrees, centred on the page (brand blue, about 10% opaque). */
    private function brandMark(\GdImage $image, int $width, int $height, ?string $font): void
    {
        $color = imagecolorallocatealpha($image, 29, 78, 216, 115);

        if (! $font) {
            $textWidth = imagefontwidth(5) * strlen(self::BRAND);
            imagestring($image, 5, (int) (($width - $textWidth) / 2), (int) ($height / 2), self::BRAND, $color); // bitmap fallback

            return;
        }

        $angle = 30;
        [$cos, $sin] = [cos(deg2rad($angle)), sin(deg2rad($angle))];
        $size = max(16, $width / 22);

        // Measure the unrotated text, and shrink it if its slanted width would not fit comfortably on the page.
        $measure = function (float $size) use ($font): array {
            $box = imagettfbbox($size, 0, $font, self::BRAND);

            return [$box[2] - $box[0], $box[1] - $box[7]];
        };
        [$textWidth, $textHeight] = $measure($size);
        if ($textWidth * $cos > $width * 0.85) {
            $size *= ($width * 0.85) / ($textWidth * $cos);
            [$textWidth, $textHeight] = $measure($size);
        }

        // imagettftext() places the baseline start; offset it so the middle of the text lands on the page centre.
        $dx = $textWidth / 2 * $cos - $textHeight / 2 * $sin;
        $dy = -$textWidth / 2 * $sin - $textHeight / 2 * $cos;
        imagettftext($image, $size, $angle, (int) ($width / 2 - $dx), (int) ($height / 2 - $dy), $color, $font, self::BRAND);
    }

    /** First usable TrueType font: LMS_WATERMARK_FONT, then common system fonts. */
    private function font(): ?string
    {
        $candidates = array_filter([
            config('lms.watermark_font'),
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/google-droid/DroidSans.ttf', // Hostinger shared hosting
            '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
            '/Library/Fonts/Arial.ttf',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
