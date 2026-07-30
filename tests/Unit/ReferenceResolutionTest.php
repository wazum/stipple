<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidSvgException;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Stipple;

/**
 * The rasterizer treats <use> and <switch> as no-ops, so sprite-sheet icons (Bootstrap Icons,
 * Font Awesome sprites) and Illustrator's SVG 1.1 export rendered as nothing at all. Both are
 * resolved in the preprocessor instead.
 */
final class ReferenceResolutionTest extends TestCase
{
    private const OPEN = '<svg xmlns="http://www.w3.org/2000/svg" '
        .'xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 16 16">';

    #[Test]
    public function useOfASymbolRendersLikeTheInlinedShape(): void
    {
        $inlined = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $viaUse = self::OPEN
            .'<defs><symbol id="box"><rect x="2" y="2" width="12" height="12" fill="currentColor"/></symbol></defs>'
            .'<use xlink:href="#box"/></svg>';

        self::assertSame($this->render($inlined), $this->render($viaUse));
    }

    #[Test]
    public function svg2HrefIsResolvedToo(): void
    {
        $inlined = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $viaUse = self::OPEN
            .'<defs><rect id="box" x="2" y="2" width="12" height="12" fill="currentColor"/></defs>'
            .'<use href="#box"/></svg>';

        self::assertSame($this->render($inlined), $this->render($viaUse));
    }

    #[Test]
    public function useOffsetIsApplied(): void
    {
        $shifted = self::OPEN.'<rect x="8" y="0" width="8" height="8" fill="currentColor"/></svg>';
        $viaUse = self::OPEN
            .'<defs><rect id="box" x="0" y="0" width="8" height="8" fill="currentColor"/></defs>'
            .'<use href="#box" x="8" y="0"/></svg>';

        self::assertSame($this->render($shifted), $this->render($viaUse));
    }

    #[Test]
    public function nestedUseIsResolved(): void
    {
        $inlined = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $viaUse = self::OPEN
            .'<defs>'
            .'<rect id="inner" x="2" y="2" width="12" height="12" fill="currentColor"/>'
            .'<g id="outer"><use href="#inner"/></g>'
            .'</defs>'
            .'<use href="#outer"/></svg>';

        self::assertSame($this->render($inlined), $this->render($viaUse));
    }

    #[Test]
    public function externalReferenceIsRejected(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN.'<use xlink:href="https://example.com/sprite.svg#box"/></svg>');
    }

    #[Test]
    public function danglingReferenceIsRejected(): void
    {
        // Silently rendering nothing is the failure mode this library keeps getting bitten by.
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN.'<use href="#nope"/></svg>');
    }

    #[Test]
    public function circularReferenceIsRejected(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN
            .'<defs><g id="a"><use href="#b"/></g><g id="b"><use href="#a"/></g></defs>'
            .'<use href="#a"/></svg>');
    }

    #[Test]
    public function switchRendersItsFirstViableChild(): void
    {
        $inlined = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $viaSwitch = self::OPEN
            .'<switch><g><rect x="2" y="2" width="12" height="12" fill="currentColor"/></g></switch></svg>';

        self::assertSame($this->render($inlined), $this->render($viaSwitch));
    }

    #[Test]
    public function switchSkipsChildrenRequiringUnsupportedExtensions(): void
    {
        $inlined = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $viaSwitch = self::OPEN
            .'<switch>'
            .'<g requiredExtensions="http://example.com/nope"><rect width="16" height="16" fill="currentColor"/></g>'
            .'<g><rect x="2" y="2" width="12" height="12" fill="currentColor"/></g>'
            .'</switch></svg>';

        self::assertSame($this->render($inlined), $this->render($viaSwitch));
    }

    private function render(string $svg): string
    {
        return Stipple::makeFromString($svg)->height(8)->sampler(new HalfBlockSampler())->toString();
    }
}
