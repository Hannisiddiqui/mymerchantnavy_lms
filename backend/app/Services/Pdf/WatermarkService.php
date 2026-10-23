<?php

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Burns the student's name and login (email or phone) into a page image, repeated diagonally.
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
        $size = max(14, (int) ($width / 38));
        $font = $this->font();
        $color = imagecolorallocatealpha($image, 11, 30, 61, 105); // navy, ~18% opaque
        $stepX = (int) ($width / 2.2);
        $stepY = (int) ($height / 5);

        for ($y = (int) ($stepY / 2); $y < $height + $stepY; $y += $stepY) {
            for ($x = -$stepX / 2; $x < $width; $x += $stepX) {
                if ($font) {
                    imagettftext($image, $size, 30, (int) $x, $y, $color, $font, $text);
                } else {
                    imagestring($image, 5, (int) $x, $y, $text, $color); // plain bitmap font fallback
                }
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
