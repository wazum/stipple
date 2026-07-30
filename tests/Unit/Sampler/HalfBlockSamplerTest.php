<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit\Sampler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Sampler\HalfBlockSampler;

final class HalfBlockSamplerTest extends TestCase
{
    private const OPAQUE = 0;
    private const TRANSPARENT = 127;

    #[Test]
    public function pixelsPerCellAdvertises1x2(): void
    {
        $sampler = new HalfBlockSampler();

        self::assertSame(1, $sampler->pixelsPerCellX());
        self::assertSame(2, $sampler->pixelsPerCellY());
    }

    #[Test]
    public function fullyTransparent1x2ImageEmitsBlankRowWithNoSgr(): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [0, 0, 0, self::TRANSPARENT]],
            [0, 1, [0, 0, 0, self::TRANSPARENT]],
        ]);

        self::assertSame(" \n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function fullyOpaqueWhite1x2ImageWithDefaultColor(): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [255, 255, 255, self::OPAQUE]],
        ]);

        self::assertSame("\e[39m█\e[0m\n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function fullyOpaqueWhite1x2ImageWithCustomColor(): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [255, 255, 255, self::OPAQUE]],
        ]);

        self::assertSame("\e[38;2;0;255;255m█\e[0m\n", (new HalfBlockSampler())->sample($image, '#00ffff', 0.5));
    }

    /**
     * Every other sampler test uses greyscale, where a swapped channel or a misread alpha
     * byte would go unnoticed. Columns: opaque red (luma 0.299), green (0.587),
     * blue (0.114), half-transparent green (0.587 x 0.504 = 0.296).
     */
    #[Test]
    public function asymmetricChannelsAreWeightedPerRec601(): void
    {
        $pixels = [];
        foreach ([[255, 0, 0, self::OPAQUE], [0, 255, 0, self::OPAQUE], [0, 0, 255, self::OPAQUE], [0, 255, 0, 63]] as $column => $rgba) {
            $pixels[] = [$column, 0, $rgba];
            $pixels[] = [$column, 1, $rgba];
        }

        $sampler = new HalfBlockSampler();

        // Blue alone falls below 0.2; at 0.35 only green survives.
        self::assertSame("\e[39m██ █\e[0m\n", $sampler->sample($this->imageOf(4, 2, $pixels), null, 0.2));
        self::assertSame("\e[39m █  \e[0m\n", $sampler->sample($this->imageOf(4, 2, $pixels), null, 0.35));
    }

    #[Test]
    public function blankCellIsASpace(): void
    {
        self::assertSame(' ', (new HalfBlockSampler())->blankCell());
    }

    #[Test]
    public function paletteImageIsRejected(): void
    {
        // imagecolorat() returns a palette index here, not packed ARGB, so sampling it
        // would silently mis-read every pixel.
        $image = imagecreate(2, 2);
        self::assertNotFalse($image);
        imagecolorallocate($image, 255, 255, 255);

        $this->expectException(InvalidArgumentException::class);
        (new HalfBlockSampler())->sample($image, null, 0.5);
    }

    #[Test]
    #[DataProvider('malformedForegroundHexProvider')]
    public function malformedForegroundHexIsRejected(string $hex): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [255, 255, 255, self::OPAQUE]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new HalfBlockSampler())->sample($image, $hex, 0.5);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedForegroundHexProvider(): iterable
    {
        yield 'not hex at all' => ['nonsense'];
        yield 'missing hash' => ['00ffff'];
        yield 'short form' => ['#fff'];
        yield 'named colour' => ['red'];
        yield 'empty string' => [''];
        yield 'trailing garbage' => ['#00ffffzz'];
    }

    #[Test]
    public function topOpaqueBottomTransparentRendersUpperHalfBlock(): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [0, 0, 0, self::TRANSPARENT]],
        ]);

        self::assertSame("\e[39m▀\e[0m\n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function topTransparentBottomOpaqueRendersLowerHalfBlock(): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [0, 0, 0, self::TRANSPARENT]],
            [0, 1, [255, 255, 255, self::OPAQUE]],
        ]);

        self::assertSame("\e[39m▄\e[0m\n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function pureBlackOpaquePixelIsOffAtDefaultThreshold(): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [0, 0, 0, self::OPAQUE]],
            [0, 1, [0, 0, 0, self::OPAQUE]],
        ]);

        self::assertSame(" \n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function fiftyPercentOpaqueWhiteAtThresholdHalfIsBoundaryOn(): void
    {
        // gdAlpha 63 → opacity = 1 - 63/127 = 64/127 ≈ 0.504 → weight ≈ 0.504 ≥ 0.5 → ON.
        $image = $this->imageOf(1, 2, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [255, 255, 255, 63]],
        ]);

        self::assertSame("\e[39m█\e[0m\n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function midGreyOpaqueAtThresholdHalfIsOff(): void
    {
        // luminance = 127/255 ≈ 0.498 < 0.5 → OFF.
        $image = $this->imageOf(1, 2, [
            [0, 0, [127, 127, 127, self::OPAQUE]],
            [0, 1, [127, 127, 127, self::OPAQUE]],
        ]);

        self::assertSame(" \n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function thresholdZeroTreatsAnyVisiblePixelAsOn(): void
    {
        $image = $this->imageOf(1, 2, [
            [0, 0, [1, 1, 1, self::OPAQUE]],
            [0, 1, [1, 1, 1, self::OPAQUE]],
        ]);

        self::assertSame("\e[39m█\e[0m\n", (new HalfBlockSampler())->sample($image, null, 0.0));
    }

    #[Test]
    public function thresholdOneOnlyFullWhiteOpaquePasses(): void
    {
        $sampler = new HalfBlockSampler();

        $whiteImage = $this->imageOf(1, 2, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [255, 255, 255, self::OPAQUE]],
        ]);
        self::assertSame("\e[39m█\e[0m\n", $sampler->sample($whiteImage, null, 1.0));

        $almostWhite = $this->imageOf(1, 2, [
            [0, 0, [254, 254, 254, self::OPAQUE]],
            [0, 1, [254, 254, 254, self::OPAQUE]],
        ]);
        self::assertSame(" \n", $sampler->sample($almostWhite, null, 1.0));
    }

    #[Test]
    public function checkerPatternIn2x4ImageProducesExactByteString(): void
    {
        $image = $this->imageOf(2, 4, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [1, 0, [0, 0, 0, self::TRANSPARENT]],
            [0, 1, [0, 0, 0, self::TRANSPARENT]],
            [1, 1, [255, 255, 255, self::OPAQUE]],
            [0, 2, [255, 255, 255, self::OPAQUE]],
            [1, 2, [0, 0, 0, self::TRANSPARENT]],
            [0, 3, [0, 0, 0, self::TRANSPARENT]],
            [1, 3, [255, 255, 255, self::OPAQUE]],
        ]);

        self::assertSame(
            "\e[39m▀▄\e[0m\n\e[39m▀▄\e[0m\n",
            (new HalfBlockSampler())->sample($image, null, 0.5),
        );
    }

    #[Test]
    public function blankOnlyRowsEmitNoSgrInTallerImage(): void
    {
        $image = $this->imageOf(2, 4, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [1, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [255, 255, 255, self::OPAQUE]],
            [1, 1, [255, 255, 255, self::OPAQUE]],
            [0, 2, [0, 0, 0, self::TRANSPARENT]],
            [1, 2, [0, 0, 0, self::TRANSPARENT]],
            [0, 3, [0, 0, 0, self::TRANSPARENT]],
            [1, 3, [0, 0, 0, self::TRANSPARENT]],
        ]);

        self::assertSame("\e[39m██\e[0m\n  \n", (new HalfBlockSampler())->sample($image, null, 0.5));
    }

    #[Test]
    public function oddHeightImageTreatsMissingBottomRowAsTransparent(): void
    {
        $image = $this->imageOf(1, 3, [
            [0, 0, [255, 255, 255, self::OPAQUE]],
            [0, 1, [255, 255, 255, self::OPAQUE]],
            [0, 2, [255, 255, 255, self::OPAQUE]],
        ]);

        self::assertSame(
            "\e[39m█\e[0m\n\e[39m▀\e[0m\n",
            (new HalfBlockSampler())->sample($image, null, 0.5),
        );
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     * @param list<array{int, int, array{int<0, 255>, int<0, 255>, int<0, 255>, int<0, 127>}}> $pixels list of [x, y, [r, g, b, gdAlpha(0..127)]]
     */
    private function imageOf(int $width, int $height, array $pixels): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        if ($transparent === false) {
            self::fail('imagecolorallocatealpha failed');
        }
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $transparent);

        foreach ($pixels as [$x, $y, $rgba]) {
            [$red, $green, $blue, $alpha] = $rgba;
            $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
            if ($color === false) {
                self::fail('imagecolorallocatealpha failed');
            }
            imagesetpixel($image, $x, $y, $color);
        }

        return $image;
    }
}
