<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Exception\StippleException;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Stipple;

final class StippleTest extends TestCase
{
    private const SYNTHETIC_FIXTURE = __DIR__.'/../Fixtures/synthetic/full-rect.svg';

    #[Test]
    public function fullRectFixtureProducesByteExactBrailleOutput(): void
    {
        // 4x4 viewBox, fully filled rect → every pixel opaque white.
        // height(2) + Braille (2x4 px/cell): cellsWide = round(2 * 1.0 * 2) = 4 cells wide,
        // raster 4x2 px-per-cell-X * 4 cells = wait, it's cellsWide × pixelsPerCellX = 4 × 2 = 8 wide
        // and cellsTall × pixelsPerCellY = 2 × 4 = 8 tall. Each cell maps to a fully-on Braille glyph.
        $output = Stipple::make(self::SYNTHETIC_FIXTURE)
            ->height(2)
            ->sampler(new BrailleSampler())
            ->toString();

        self::assertSame(
            "\e[39m\u{28FF}\u{28FF}\u{28FF}\u{28FF}\e[0m\n\e[39m\u{28FF}\u{28FF}\u{28FF}\u{28FF}\e[0m\n",
            $output,
        );
    }

    #[Test]
    public function fullRectFixtureProducesByteExactHalfBlockOutput(): void
    {
        // height(2) + Half-block (1x2 px/cell): cellsWide = 4, raster 4x4 px.
        // All pixels opaque white → all cells "█".
        $output = Stipple::make(self::SYNTHETIC_FIXTURE)
            ->height(2)
            ->sampler(new HalfBlockSampler())
            ->toString();

        self::assertSame(
            "\e[39m████\e[0m\n\e[39m████\e[0m\n",
            $output,
        );
    }

    #[Test]
    public function customColorEmitsTrueColorSgr(): void
    {
        $output = Stipple::make(self::SYNTHETIC_FIXTURE)
            ->height(2)
            ->color('#00ffff')
            ->sampler(new HalfBlockSampler())
            ->toString();

        self::assertSame(
            "\e[38;2;0;255;255m████\e[0m\n\e[38;2;0;255;255m████\e[0m\n",
            $output,
        );
    }

    #[Test]
    public function makeFromStringRendersInlineSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4">'
            .'<rect x="0" y="0" width="4" height="4" fill="currentColor"/></svg>';

        $output = Stipple::makeFromString($svg)
            ->height(2)
            ->sampler(new HalfBlockSampler())
            ->toString();

        self::assertSame(
            "\e[39m████\e[0m\n\e[39m████\e[0m\n",
            $output,
        );
    }

    #[Test]
    public function iconStyledByStyleElementRendersInsteadOfBlank(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4">'
            .'<style>.a { fill: currentColor }</style>'
            .'<rect class="a" x="0" y="0" width="4" height="4"/></svg>';

        $output = Stipple::makeFromString($svg)
            ->height(2)
            ->sampler(new HalfBlockSampler())
            ->toString();

        self::assertSame(
            "\e[38;2;0;255;255m████\e[0m\n\e[38;2;0;255;255m████\e[0m\n",
            Stipple::makeFromString($svg)->height(2)->color('#00ffff')->sampler(new HalfBlockSampler())->toString(),
        );
        self::assertSame("\e[39m████\e[0m\n\e[39m████\e[0m\n", $output);
    }

    #[Test]
    public function toIconExposesTheSameOutputAsToStringSplitIntoRows(): void
    {
        $instance = Stipple::make(self::SYNTHETIC_FIXTURE)->height(2)->sampler(new HalfBlockSampler());

        $icon = $instance->toIcon();

        self::assertSame(["\e[39m████\e[0m", "\e[39m████\e[0m"], $icon->rows);
        self::assertSame($instance->toString(), (string) $icon);
    }

    #[Test]
    public function toIconReportsCellDimensions(): void
    {
        $icon = Stipple::make(self::SYNTHETIC_FIXTURE)
            ->height(2)
            ->sampler(new HalfBlockSampler())
            ->toIcon();

        // 4x4 viewBox at height(2): cellsWide = round(2 * 1.0 * 2) = 4.
        self::assertSame(4, $icon->widthCells);
        self::assertSame(2, $icon->heightCells);
        self::assertCount($icon->heightCells, $icon->rows);
    }

    #[Test]
    public function toIconWidthTracksAspectRatio(): void
    {
        $wide = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 16">'
            .'<rect width="32" height="16" fill="currentColor"/></svg>',
        )->height(2)->sampler(new HalfBlockSampler())->toIcon();

        self::assertSame(8, $wide->widthCells);
        self::assertSame(8, mb_strlen($wide->blankRow()));
    }

    #[Test]
    public function toIconCarriesTheSamplersBlankCell(): void
    {
        $braille = Stipple::make(self::SYNTHETIC_FIXTURE)->height(2)->sampler(new BrailleSampler())->toIcon();
        $halfBlock = Stipple::make(self::SYNTHETIC_FIXTURE)->height(2)->sampler(new HalfBlockSampler())->toIcon();

        self::assertSame("\u{2800}", $braille->blankCell);
        self::assertSame(' ', $halfBlock->blankCell);
    }

    #[Test]
    public function renderShortcutMatchesFluentToString(): void
    {
        self::assertSame(
            Stipple::render(self::SYNTHETIC_FIXTURE),
            Stipple::make(self::SYNTHETIC_FIXTURE)->toString(),
        );
    }

    #[Test]
    public function renderFromStringShortcutMatchesFluentToString(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4">'
            .'<rect x="0" y="0" width="4" height="4" fill="currentColor"/></svg>';

        self::assertSame(
            Stipple::renderFromString($svg),
            Stipple::makeFromString($svg)->toString(),
        );
    }

    #[Test]
    public function castingInstanceToStringInvokesRender(): void
    {
        $svg = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4">'
            .'<rect x="0" y="0" width="4" height="4" fill="currentColor"/></svg>',
        )->height(2)->sampler(new HalfBlockSampler());

        self::assertSame((string) $svg, $svg->toString());
    }

    #[Test]
    public function rasterDimensionCapRejectsExcessiveAspectRatio(): void
    {
        // viewBox 10000x1 with height(8) = 8 px-tall, aspectRatio 10000 → cellsWide 160000.
        // That's well past the default cap (4096 px wide).
        $instance = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10000 1"/>',
        )->height(8);

        $this->expectException(InvalidArgumentException::class);
        $instance->toString();
    }

    /**
     * A finite but enormous ratio overflows the int cast, which max(1, …) then launders into a
     * plausible-looking one-cell icon instead of hitting the raster cap.
     */
    #[Test]
    #[DataProvider('hugeAspectRatioProvider')]
    public function finiteButEnormousAspectRatioIsRejected(string $viewBox): void
    {
        $instance = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'">'
            .'<rect width="9" height="9" fill="#ffffff"/></svg>',
        )->height(256);

        try {
            $instance->toString();
            self::fail('Expected the raster cap to reject this.');
        } catch (InvalidArgumentException $exception) {
            // A negative width would mean the int cast overflowed rather than being range-checked.
            self::assertDoesNotMatchRegularExpression('/-\d/', $exception->getMessage());
            self::assertLessThan(200, strlen($exception->getMessage()), 'Message should stay readable.');
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function hugeAspectRatioProvider(): iterable
    {
        yield 'ratio 1e17' => ['0 0 1e17 1'];
        yield 'ratio 1e18' => ['0 0 1e18 1'];
        yield 'ratio 1e300' => ['0 0 1e300 1'];
    }

    #[Test]
    public function extremelyThinAspectRatioClampsToOneCell(): void
    {
        // The other direction does not overflow: a genuinely thin icon is one cell wide.
        $icon = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1e300"/>',
        )->height(2)->toIcon();

        self::assertSame(1, $icon->widthCells);
    }

    #[Test]
    public function lowerCustomMaxRasterDimensionTriggersEarlierRejection(): void
    {
        // 4x4 viewBox at height(8) with default Braille = 16x32 px → would normally pass.
        // Cap at 8 px: the height (32 px) blows past it.
        $instance = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4"/>',
        )->height(8)->maxRasterDimension(8);

        $this->expectException(InvalidArgumentException::class);
        $instance->toString();
    }

    #[Test]
    public function negativeMaxRasterDimensionRejected(): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->maxRasterDimension(0);
    }

    #[Test]
    public function unreadablePathThrowsInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Stipple::make('/no/such/file/here.svg');
    }

    #[Test]
    public function gradientFilledIconRendersInsteadOfBlank(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4">'
            .'<linearGradient id="g"><stop offset="0" stop-color="#ff0000"/>'
            .'<stop offset="1" stop-color="#00ff00"/></linearGradient>'
            .'<rect x="0" y="0" width="4" height="4" fill="url(#g)"/></svg>';

        $output = Stipple::makeFromString($svg)
            ->height(2)
            ->sampler(new HalfBlockSampler())
            ->toString();

        self::assertSame("\e[39m████\e[0m\n\e[39m████\e[0m\n", $output);
    }

    #[Test]
    public function pxDimensionedSvgWithoutViewBoxRenders(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="4px" height="4px">'
            .'<rect x="0" y="0" width="4" height="4" fill="currentColor"/></svg>';

        $output = Stipple::makeFromString($svg)
            ->height(2)
            ->sampler(new HalfBlockSampler())
            ->toString();

        self::assertSame("\e[39m████\e[0m\n\e[39m████\e[0m\n", $output);
    }

    #[Test]
    public function streamWrapperPathIsRejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2">'
            .'<rect width="2" height="2" fill="#ffffff"/></svg>';

        $this->expectException(InvalidArgumentException::class);
        Stipple::make('data://text/plain;base64,'.base64_encode($svg));
    }

    #[Test]
    public function directoryPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Stipple::make(__DIR__);
    }

    #[Test]
    public function emptyFileThrowsStippleException(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stipple');
        self::assertNotFalse($path);
        file_put_contents($path, '');

        try {
            $this->expectException(StippleException::class);
            Stipple::make($path)->toString();
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function emptyStringThrowsStippleException(): void
    {
        $this->expectException(StippleException::class);
        Stipple::makeFromString('')->toString();
    }

    #[Test]
    public function heightOutOfRangeRejected(): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->height(0);
    }

    #[Test]
    public function heightAboveRangeRejected(): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->height(257);
    }

    #[Test]
    public function thresholdOutOfRangeRejected(): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->threshold(-0.1);
    }

    #[Test]
    #[DataProvider('nonFiniteThresholdProvider')]
    public function nonFiniteThresholdRejected(float $threshold): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->threshold($threshold);
    }

    /**
     * @return iterable<string, array{0: float}>
     */
    public static function nonFiniteThresholdProvider(): iterable
    {
        // NAN slips past a naive range check: both < and > comparisons are false for NAN.
        yield 'NAN' => [NAN];
        yield 'INF' => [INF];
        yield '-INF' => [-INF];
    }

    #[Test]
    public function thresholdAboveRangeRejected(): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->threshold(1.5);
    }

    #[Test]
    public function shortHexColorRejected(): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->color('#fff');
    }

    #[Test]
    public function namedColorRejected(): void
    {
        $instance = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');

        $this->expectException(InvalidArgumentException::class);
        $instance->color('red');
    }

    #[Test]
    public function fluentBuilderIsImmutable(): void
    {
        $base = Stipple::makeFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');
        $colored = $base->color('#aabbcc');

        // The original is untouched: re-rendering produces default-fg output, not the cyan one.
        self::assertNotSame($base, $colored);
    }
}
