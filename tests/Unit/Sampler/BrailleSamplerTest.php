<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit\Sampler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Sampler\BrailleSampler;

final class BrailleSamplerTest extends TestCase
{
    private const OPAQUE = 0;

    #[Test]
    public function pixelsPerCellAdvertises2x4(): void
    {
        $sampler = new BrailleSampler();

        self::assertSame(2, $sampler->pixelsPerCellX());
        self::assertSame(4, $sampler->pixelsPerCellY());
    }

    #[Test]
    public function fullyTransparent2x4ImageEmitsBlankBrailleWithNoSgr(): void
    {
        self::assertSame(
            "\u{2800}\n",
            (new BrailleSampler())->sample($this->fillTransparent(2, 4), null, 0.5),
        );
    }

    #[Test]
    public function fullyOpaqueWhite2x4ImageEmitsFullBrailleWithDefaultColor(): void
    {
        self::assertSame(
            "\e[39m\u{28FF}\e[0m\n",
            (new BrailleSampler())->sample($this->fillOpaqueWhite(2, 4), null, 0.5),
        );
    }

    #[Test]
    public function fullyOpaqueWhiteWithCustomColor(): void
    {
        self::assertSame(
            "\e[38;2;0;255;255m\u{28FF}\e[0m\n",
            (new BrailleSampler())->sample($this->fillOpaqueWhite(2, 4), '#00ffff', 0.5),
        );
    }

    #[Test]
    #[DataProvider('singleDotProvider')]
    public function singleDotMapsToCorrectCodepoint(int $pixelX, int $pixelY, int $expectedCodepoint): void
    {
        $image = $this->fillTransparent(2, 4);
        $this->setPixel($image, $pixelX, $pixelY, [255, 255, 255, self::OPAQUE]);

        self::assertSame(
            sprintf("\e[39m%s\e[0m\n", mb_chr($expectedCodepoint, 'UTF-8')),
            (new BrailleSampler())->sample($image, null, 0.5),
        );
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: int}>
     */
    public static function singleDotProvider(): iterable
    {
        yield 'dot 1 = (col 0, row 0)' => [0, 0, 0x2801];
        yield 'dot 2 = (col 0, row 1)' => [0, 1, 0x2802];
        yield 'dot 3 = (col 0, row 2)' => [0, 2, 0x2804];
        yield 'dot 4 = (col 1, row 0)' => [1, 0, 0x2808];
        yield 'dot 5 = (col 1, row 1)' => [1, 1, 0x2810];
        yield 'dot 6 = (col 1, row 2)' => [1, 2, 0x2820];
        yield 'dot 7 = (col 0, row 3)' => [0, 3, 0x2840];
        yield 'dot 8 = (col 1, row 3)' => [1, 3, 0x2880];
    }

    #[Test]
    public function dots125CombinationProducesU2813(): void
    {
        // Dots 1, 2, 5 → bits 0x01 | 0x02 | 0x10 = 0x13 → U+2813 = "⠓".
        $image = $this->fillTransparent(2, 4);
        $this->setPixel($image, 0, 0, [255, 255, 255, self::OPAQUE]);
        $this->setPixel($image, 0, 1, [255, 255, 255, self::OPAQUE]);
        $this->setPixel($image, 1, 1, [255, 255, 255, self::OPAQUE]);

        self::assertSame("\e[39m\u{2813}\e[0m\n", (new BrailleSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function pureBlackOpaquePixelIsOff(): void
    {
        $image = $this->fillTransparent(2, 4);
        $this->setPixel($image, 0, 0, [0, 0, 0, self::OPAQUE]);

        self::assertSame("\u{2800}\n", (new BrailleSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function thresholdOneOnlyFullWhiteOpaquePasses(): void
    {
        $sampler = new BrailleSampler();

        $whiteImage = $this->fillOpaqueWhite(2, 4);
        self::assertSame("\e[39m\u{28FF}\e[0m\n", $sampler->sample($whiteImage, null, 1.0));

        $almostWhite = $this->fillTransparent(2, 4);
        for ($x = 0; $x < 2; $x++) {
            for ($y = 0; $y < 4; $y++) {
                $this->setPixel($almostWhite, $x, $y, [254, 254, 254, self::OPAQUE]);
            }
        }
        self::assertSame("\u{2800}\n", $sampler->sample($almostWhite, null, 1.0));
    }

    #[Test]
    public function twoCellTallOutputProducesTwoLines(): void
    {
        // 2x8 image: top cell all on, bottom cell all off.
        $image = $this->fillTransparent(2, 8);
        for ($x = 0; $x < 2; $x++) {
            for ($y = 0; $y < 4; $y++) {
                $this->setPixel($image, $x, $y, [255, 255, 255, self::OPAQUE]);
            }
        }

        self::assertSame(
            "\e[39m\u{28FF}\e[0m\n\u{2800}\n",
            (new BrailleSampler())->sample($image, null, 0.5),
        );
    }

    #[Test]
    public function twoCellWideOutputRendersBothCellsInOneRow(): void
    {
        // 4x4 image: left cell all on, right cell all off.
        $image = $this->fillTransparent(4, 4);
        for ($x = 0; $x < 2; $x++) {
            for ($y = 0; $y < 4; $y++) {
                $this->setPixel($image, $x, $y, [255, 255, 255, self::OPAQUE]);
            }
        }

        self::assertSame(
            "\e[39m\u{28FF}\u{2800}\e[0m\n",
            (new BrailleSampler())->sample($image, null, 0.5),
        );
    }

    #[Test]
    public function imageSmallerThanOneCellPadsMissingPixelsAsOff(): void
    {
        // 1x1 image: only dot 1 position has a pixel.
        $image = $this->fillTransparent(1, 1);
        $this->setPixel($image, 0, 0, [255, 255, 255, self::OPAQUE]);

        self::assertSame("\e[39m\u{2801}\e[0m\n", (new BrailleSampler())->sample($image, null, 0.5));
    }

    private function fillTransparent(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        if ($transparent === false) {
            self::fail('imagecolorallocatealpha failed');
        }
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $transparent);

        return $image;
    }

    private function fillOpaqueWhite(int $width, int $height): \GdImage
    {
        $image = $this->fillTransparent($width, $height);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 0);
        if ($white === false) {
            self::fail('imagecolorallocatealpha failed');
        }
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);

        return $image;
    }

    /**
     * @param array{int, int, int, int} $rgba
     */
    private function setPixel(\GdImage $image, int $x, int $y, array $rgba): void
    {
        [$red, $green, $blue, $alpha] = $rgba;
        $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
        if ($color === false) {
            self::fail('imagecolorallocatealpha failed');
        }
        imagesetpixel($image, $x, $y, $color);
    }
}
