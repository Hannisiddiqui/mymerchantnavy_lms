<?php

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Burns the student's name and login (email or phone) into a page image: a few light diagonal marks.
 * A screenshot or photo of the page therefore always carries who it came from.
 */
class WatermarkService
{
    /** @return string PNG bytes */
    public function apply(string $pngPath, string $text): string
    {
        $image = imagecreatefrompng($pngPath) ?: throw new RuntimeException('Could not read page image.');
        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $size = max(12, (int) ($width / 50));
        $font = $this->font();
        $color = imagecolorallocatealpha($image, 11, 30, 61, 116); // navy, about 9% opaque: readable page, still visible on a screenshot

        // Four marks per page, staggered, so the text under them stays easy to read.
        foreach ([[0.05, 0.28], [0.40, 0.50], [0.05, 0.72], [0.40, 0.94]] as [$fx, $fy]) {
            $x = (int) ($width * $fx);
            $y = (int) ($height * $fy);
            if ($font) {
                imagettftext($image, $size, 30, $x, $y, $color, $font, $text);
            } else {
                imagestring($image, 5, $x, $y, $text, $color); // plain bitmap font fallback
            }
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return ob_get_clean();
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
