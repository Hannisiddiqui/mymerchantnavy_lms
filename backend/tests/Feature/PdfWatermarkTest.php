<?php

namespace Tests\Feature;

use App\Services\Pdf\PdfProcessor;
use App\Services\Pdf\WatermarkService;
use Tests\TestCase;

/** The marks burned into every page image served to a student. */
class PdfWatermarkTest extends TestCase
{
    private function whitePage(int $width = 900, int $height = 1270): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wm').'.png';
        $page = imagecreatetruecolor($width, $height);
        imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));
        imagepng($page, $path);

        return $path;
    }

    /** Share of non-white pixels inside a box of the image. */
    private function inkIn(\GdImage $image, int $x0, int $y0, int $x1, int $y1): int
    {
        $ink = 0;
        for ($y = $y0; $y < $y1; $y++) {
            for ($x = $x0; $x < $x1; $x++) {
                $ink += (imagecolorat($image, $x, $y) & 0xFFFFFF) !== 0xFFFFFF ? 1 : 0;
            }
        }

        return $ink;
    }

    public function test_brand_mark_is_burned_into_the_middle_of_the_page(): void
    {
        $path = $this->whitePage();
        $png = (new WatermarkService)->apply($path, 'Aarav Sharma · aarav@example.com');
        unlink($path);

        $image = imagecreatefromstring($png);
        $this->assertSame(900, imagesx($image));
        $this->assertSame('Created by - MyMerchantNavy.com', WatermarkService::BRAND);

        // The brand mark crosses the centre of the page (the student's own marks are kept away from it)
        $this->assertGreaterThan(50, $this->inkIn($image, 400, 560, 500, 710), 'the brand mark should cross the middle of the page');
        // and the marks stay faint: nothing on the page is darker than a light tint
        $darkest = 255;
        for ($y = 0; $y < 1270; $y += 3) {
            for ($x = 0; $x < 900; $x += 3) {
                $darkest = min($darkest, imagecolorat($image, $x, $y) & 0xFF);
            }
        }
        $this->assertGreaterThan(150, $darkest, 'watermarks must stay light so the text under them is easy to read');
    }

    public function test_marks_scale_with_the_page_so_sharper_images_look_the_same(): void
    {
        $small = $this->whitePage(910, 1287);
        $large = $this->whitePage(1654, 2339);
        $watermark = new WatermarkService;

        $a = imagecreatefromstring($watermark->apply($small, 'Name'));
        $b = imagecreatefromstring($watermark->apply($large, 'Name'));
        unlink($small);
        unlink($large);

        // Same page, two resolutions: the brand mark crosses the middle of both
        $this->assertGreaterThan(50, $this->inkIn($a, 410, 570, 500, 720));
        $this->assertGreaterThan(50, $this->inkIn($b, 740, 1050, 910, 1300));
    }

    public function test_pages_are_rendered_sharp(): void
    {
        // ~1650px wide for A4: crisp on a 3x phone screen and when zoomed
        $this->assertGreaterThanOrEqual(180, PdfProcessor::DPI);
    }
}
