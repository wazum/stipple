<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidSvgException;
use Wazum\Stipple\SvgPreprocessor;

final class SvgPreprocessorTest extends TestCase
{
    private SvgPreprocessor $preprocessor;

    protected function setUp(): void
    {
        $this->preprocessor = new SvgPreprocessor();
    }

    // ---------- Pre-parse rejection ----------

    #[Test]
    public function doctypeDeclarationIsRejected(): void
    {
        $svg = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"/>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function entityDeclarationIsRejected(): void
    {
        $svg = '<?xml version="1.0"?><!ENTITY foo SYSTEM "file:///etc/passwd">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"/>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function billionLaughsPayloadIsRejected(): void
    {
        $svg = <<<'XML'
            <?xml version="1.0"?>
            <!DOCTYPE lolz [
              <!ENTITY lol "lol">
              <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
            ]>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">&lol2;</svg>
            XML;

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    // ---------- Disallowed elements ----------

    #[Test]
    public function scriptElementIsRejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<script>alert(1)</script></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function foreignObjectElementIsRejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function dataUriImageIsRejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<image href="data:image/png;base64,abc" width="16" height="16"/></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function fileUriImageIsRejected(): void
    {
        // SSRF / local file disclosure surface — meyfa/php-svg would file_get_contents() this.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<image href="file:///etc/passwd" width="16" height="16"/></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function remoteUriImageIsRejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<image href="https://evil.example.com/x.png" width="16" height="16"/></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function imageWithRelativeHrefIsAlsoRejected(): void
    {
        // Embedded raster is out of scope, period — even seemingly innocent relative paths.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<image href="some.png" width="16" height="16"/></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function imageWithXlinkHrefIsRejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 16 16">'
            .'<image xlink:href="data:image/png;base64,abc" width="16" height="16"/></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    // ---------- viewBox / dimensions ----------

    #[Test]
    public function viewBoxSpaceSeparatedParses(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"/>',
            null,
        );

        self::assertSame(1.0, $result->aspectRatio);
    }

    #[Test]
    public function viewBoxCommaSeparatedParses(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0,0,16,16"/>',
            null,
        );

        self::assertSame(1.0, $result->aspectRatio);
    }

    #[Test]
    public function viewBoxDecimalValuesParse(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 16.5"/>',
            null,
        );

        self::assertEqualsWithDelta(32.0 / 16.5, $result->aspectRatio, 1e-9);
    }

    #[Test]
    public function noViewBoxButWidthHeightAttributes(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="32"/>',
            null,
        );

        self::assertSame(2.0, $result->aspectRatio);
    }

    #[Test]
    public function noViewBoxNoDimensionsIsRejected(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean('<svg xmlns="http://www.w3.org/2000/svg"/>', null);
    }

    #[Test]
    #[DataProvider('invalidDimensionProvider')]
    public function zeroOrNegativeDimensionsAreRejected(string $svg): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidDimensionProvider(): iterable
    {
        yield 'zero width' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 16"/>'];
        yield 'zero height' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 0"/>'];
        yield 'negative width' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 -1 16"/>'];
        yield 'negative height' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 -1"/>'];
        yield 'viewBox with px unit' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16px 16"/>'];
        yield 'viewBox with garbage' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 sixteen 16"/>'];
        yield 'width overflow to INF' => ['<svg xmlns="http://www.w3.org/2000/svg" width="1e309" height="16"/>'];
        yield 'width with px suffix' => ['<svg xmlns="http://www.w3.org/2000/svg" width="64px" height="32px"/>'];
        yield 'viewBox min-x is garbage' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="abc 0 16 16"/>'];
        yield 'viewBox min-y is garbage' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 def 16 16"/>'];
        yield 'viewBox min-x overflow' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="1e309 0 16 16"/>'];
    }

    // ---------- currentColor (fill) ----------

    #[Test]
    public function doubleQuotedFillCurrentColorIsRewritten(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect fill="currentColor" width="16" height="16"/></svg>',
            null,
        );

        self::assertStringContainsString('fill="#ffffff"', $result->svg);
        self::assertStringNotContainsString('currentColor', $result->svg);
    }

    #[Test]
    public function singleQuotedFillCurrentColorIsRewritten(): void
    {
        $result = $this->preprocessor->clean(
            "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'>"
            ."<rect fill='currentColor' width='16' height='16'/></svg>",
            null,
        );

        self::assertStringNotContainsString('currentColor', $result->svg);
        self::assertStringContainsString('#ffffff', $result->svg);
    }

    #[Test]
    public function mixedCaseCurrentColorIsRewritten(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect fill="CurrentColor" width="16" height="16"/>'
            .'<rect fill="currentcolor" x="0" y="0" width="8" height="8"/></svg>',
            null,
        );

        self::assertStringNotContainsStringIgnoringCase('currentColor', $result->svg);
    }

    // ---------- currentColor (stroke) ----------

    #[Test]
    public function strokeCurrentColorIsRewritten(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<path stroke="currentColor" d="M0 0L16 16"/></svg>',
            null,
        );

        self::assertStringContainsString('stroke="#ffffff"', $result->svg);
    }

    // ---------- currentColor (style) ----------

    #[Test]
    public function styleFillCurrentColorIsRewritten(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect style="fill: currentColor" width="16" height="16"/></svg>',
            null,
        );

        self::assertStringNotContainsStringIgnoringCase('currentColor', $result->svg);
        self::assertStringContainsString('#ffffff', $result->svg);
    }

    #[Test]
    public function styleStrokeCurrentColorIsRewritten(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<path style="stroke: currentColor" d="M0 0L16 16"/></svg>',
            null,
        );

        self::assertStringNotContainsStringIgnoringCase('currentColor', $result->svg);
    }

    #[Test]
    public function styleWithOtherDeclarationsIsPreserved(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect style="fill: currentColor; opacity: 0.5" width="16" height="16"/></svg>',
            null,
        );

        self::assertStringNotContainsStringIgnoringCase('currentColor', $result->svg);
        self::assertStringContainsString('opacity', $result->svg);
        self::assertStringContainsString('0.5', $result->svg);
    }

    // ---------- accent var() ----------

    #[Test]
    public function varAccentWithExplicitAccentColor(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect fill="var(--icon-color-accent, #ff8700)" width="16" height="16"/></svg>',
            '#aabbcc',
        );

        self::assertStringContainsString('fill="#aabbcc"', $result->svg);
        self::assertStringNotContainsString('var(', $result->svg);
    }

    #[Test]
    public function varAccentWithNullUsesFallback(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect fill="var(--icon-color-accent, #ff8700)" width="16" height="16"/></svg>',
            null,
        );

        self::assertStringContainsString('fill="#ff8700"', $result->svg);
        self::assertStringNotContainsString('var(', $result->svg);
    }

    #[Test]
    public function varAccentInStyleAttributeIsRewritten(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect style="fill: var(--icon-color-accent, #ff8700)" width="16" height="16"/></svg>',
            '#aabbcc',
        );

        self::assertStringContainsString('#aabbcc', $result->svg);
        self::assertStringNotContainsString('var(', $result->svg);
    }

    #[Test]
    public function varAccentNoFallbackNoConfiguredColorRejects(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect fill="var(--icon-color-accent)" width="16" height="16"/></svg>';

        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    #[Test]
    public function otherVarCallsAreLeftAlone(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect fill="var(--something-else, red)" width="16" height="16"/></svg>',
            null,
        );

        self::assertStringContainsString('var(--something-else', $result->svg);
    }

    // ---------- aspect ratio ----------

    #[Test]
    public function aspectRatioForRectangularViewBox(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 16"/>',
            null,
        );

        self::assertSame(4.0, $result->aspectRatio);
    }

    // ---------- idempotency ----------

    #[Test]
    public function cleanIsIdempotentForCurrentColorIcon(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<g fill="currentColor"><path d="M8 1v14M1 8h14" stroke="currentColor" stroke-width="2"/></g></svg>';

        $first = $this->preprocessor->clean($svg, null);
        $second = $this->preprocessor->clean($first->svg, null);

        self::assertSame($first->svg, $second->svg);
    }

}
