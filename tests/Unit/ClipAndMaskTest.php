<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidSvgException;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Stipple;

/**
 * The rasterizer ignores clip-path and mask entirely, so clipped content was drawn unclipped —
 * a clip that mattered turned its icon into a filled block, silently. Figma and Illustrator wrap
 * exports in a clip that covers the whole viewBox, which is a no-op and can simply be dropped;
 * a clip that genuinely clips cannot be honoured and is refused instead of rendered wrongly.
 */
final class ClipAndMaskTest extends TestCase
{
    private const OPEN = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">';

    #[Test]
    public function clipCoveringTheViewBoxIsDropped(): void
    {
        $unclipped = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $clipped = self::OPEN
            .'<defs><clipPath id="c"><rect width="16" height="16"/></clipPath></defs>'
            .'<rect x="2" y="2" width="12" height="12" fill="currentColor" clip-path="url(#c)"/></svg>';

        self::assertSame($this->render($unclipped), $this->render($clipped));
    }

    #[Test]
    public function figmaStyleGroupClipIsDropped(): void
    {
        $unclipped = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $clipped = self::OPEN
            .'<g clip-path="url(#clip0_1_2)">'
            .'<rect x="2" y="2" width="12" height="12" fill="currentColor"/>'
            .'</g>'
            .'<defs><clipPath id="clip0_1_2"><rect width="16" height="16" fill="white"/></clipPath></defs></svg>';

        self::assertSame($this->render($unclipped), $this->render($clipped));
    }

    #[Test]
    public function clipLargerThanTheViewBoxIsDropped(): void
    {
        $unclipped = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $clipped = self::OPEN
            .'<defs><clipPath id="c"><rect x="-4" y="-4" width="32" height="32"/></clipPath></defs>'
            .'<rect x="2" y="2" width="12" height="12" fill="currentColor" clip-path="url(#c)"/></svg>';

        self::assertSame($this->render($unclipped), $this->render($clipped));
    }

    #[Test]
    public function clipThatActuallyClipsIsRefused(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN
            .'<defs><clipPath id="c"><circle cx="8" cy="8" r="2"/></clipPath></defs>'
            .'<rect width="16" height="16" fill="currentColor" clip-path="url(#c)"/></svg>');
    }

    #[Test]
    public function partialRectClipIsRefused(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN
            .'<defs><clipPath id="c"><rect width="8" height="16"/></clipPath></defs>'
            .'<rect width="16" height="16" fill="currentColor" clip-path="url(#c)"/></svg>');
    }

    #[Test]
    public function maskIsRefused(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN
            .'<defs><mask id="m"><rect width="16" height="16" fill="#000"/></mask></defs>'
            .'<rect width="16" height="16" fill="currentColor" mask="url(#m)"/></svg>');
    }

    #[Test]
    public function danglingClipReferenceIsRefused(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN
            .'<rect width="16" height="16" fill="currentColor" clip-path="url(#missing)"/></svg>');
    }

    #[Test]
    public function textIsRefused(): void
    {
        // No font is registered with the rasterizer, so text renders as nothing at all.
        $this->expectException(InvalidSvgException::class);
        $this->render(self::OPEN.'<text x="2" y="12" font-size="10">A</text></svg>');
    }

    #[Test]
    public function titleAndDescAreNotMistakenForText(): void
    {
        $plain = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $described = self::OPEN
            .'<title>An icon</title><desc>Longer description</desc>'
            .'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';

        self::assertSame($this->render($plain), $this->render($described));
    }

    #[Test]
    public function clipPathNoneIsLeftAlone(): void
    {
        $plain = self::OPEN.'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $explicitNone = self::OPEN
            .'<rect x="2" y="2" width="12" height="12" fill="currentColor" clip-path="none"/></svg>';

        self::assertSame($this->render($plain), $this->render($explicitNone));
    }

    private function render(string $svg): string
    {
        return Stipple::makeFromString($svg)->height(8)->sampler(new HalfBlockSampler())->toString();
    }
}
