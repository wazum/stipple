<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\RenderedIcon;

final class RenderedIconTest extends TestCase
{
    #[Test]
    public function toStringJoinsRowsEachEndingInNewline(): void
    {
        $icon = new RenderedIcon(['ab', 'cd'], 2, 2, ' ');

        self::assertSame("ab\ncd\n", $icon->toString());
    }

    /**
     * Under strict_types a Stringable is not accepted where a string is declared, so the
     * explicit accessor is what callers actually need; the cast just delegates to it.
     */
    #[Test]
    public function castingDelegatesToToString(): void
    {
        $icon = new RenderedIcon(['ab', 'cd'], 2, 2, ' ');

        self::assertSame($icon->toString(), (string) $icon);
    }

    #[Test]
    public function noRowsStringifyToEmptyString(): void
    {
        $icon = new RenderedIcon([], 0, 0, ' ');

        self::assertSame('', (string) $icon);
    }

    #[Test]
    public function rowReturnsTheRequestedLine(): void
    {
        $icon = new RenderedIcon(['ab', 'cd'], 2, 2, ' ');

        self::assertSame('ab', $icon->row(0));
        self::assertSame('cd', $icon->row(1));
    }

    #[Test]
    public function rowBeyondTheLastLineReturnsABlankRow(): void
    {
        $icon = new RenderedIcon(['ab', 'cd'], 2, 2, ' ');

        self::assertSame('  ', $icon->row(2));
        self::assertSame('  ', $icon->row(-1));
    }

    /**
     * Braille blanks are U+2800, not spaces — padding with spaces would break the column
     * alignment the Braille sampler goes out of its way to preserve.
     */
    #[Test]
    public function blankRowRepeatsTheSamplersBlankCell(): void
    {
        $icon = new RenderedIcon(['x'], 3, 1, "\u{2800}");

        self::assertSame("\u{2800}\u{2800}\u{2800}", $icon->blankRow());
        self::assertSame("\u{2800}\u{2800}\u{2800}", $icon->row(9));
    }
}
