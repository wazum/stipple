<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit\Rasterizer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\RasterizationFailedException;
use Wazum\Stipple\Rasterizer\PhpSvgRasterizer;

final class PhpSvgRasterizerTest extends TestCase
{
    #[Test]
    public function returnsGdImageOfExactRequestedDimensions(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect x="0" y="0" width="16" height="16" fill="#ffffff"/></svg>';

        $image = (new PhpSvgRasterizer())->rasterize($svg, 8, 16);

        self::assertSame(8, imagesx($image));
        self::assertSame(16, imagesy($image));
    }

    #[Test]
    public function pureWhiteFilledRectRastersAsMostlyOpaqueWhite(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect x="0" y="0" width="16" height="16" fill="#ffffff"/></svg>';

        $image = (new PhpSvgRasterizer())->rasterize($svg, 8, 8);

        $opaqueWhitePixels = 0;
        $totalPixels = 0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $totalPixels++;
                if ($rgba['red'] >= 240 && $rgba['green'] >= 240 && $rgba['blue'] >= 240 && $rgba['alpha'] === 0) {
                    $opaqueWhitePixels++;
                }
            }
        }

        self::assertGreaterThanOrEqual(0.95, $opaqueWhitePixels / $totalPixels);
    }

    #[Test]
    public function emptySvgRastersAsFullyTransparent(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"/>';

        $image = (new PhpSvgRasterizer())->rasterize($svg, 4, 4);

        for ($y = 0; $y < 4; $y++) {
            for ($x = 0; $x < 4; $x++) {
                $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                self::assertSame(127, $rgba['alpha'], "Pixel ($x, $y) is not fully transparent");
            }
        }
    }

    #[Test]
    public function nonPositiveDimensionsThrow(): void
    {
        $rasterizer = new PhpSvgRasterizer();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"/>';

        $this->expectException(RasterizationFailedException::class);
        $rasterizer->rasterize($svg, 0, 8);
    }

    #[Test]
    public function negativeDimensionsThrow(): void
    {
        $rasterizer = new PhpSvgRasterizer();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"/>';

        $this->expectException(RasterizationFailedException::class);
        $rasterizer->rasterize($svg, 8, -1);
    }

    #[Test]
    public function malformedSvgThrows(): void
    {
        $rasterizer = new PhpSvgRasterizer();

        $this->expectException(RasterizationFailedException::class);
        $rasterizer->rasterize('not even xml', 8, 8);
    }
}
