<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Stipple;

/**
 * Monochrome output has exactly one ink colour, so the paint an icon declares carries no
 * information — only its coverage does. currentColor and url(#…) are already normalised on
 * that reasoning; these cover the rest, because black or absent fill is the most common
 * convention in the major icon sets.
 */
final class DarkPaintTest extends TestCase
{
    #[Test]
    #[DataProvider('darkPaintProvider')]
    public function darkPaintStillRendersInk(string $svg): void
    {
        $output = Stipple::makeFromString($svg)
            ->height(4)
            ->sampler(new HalfBlockSampler())
            ->toString();

        self::assertNotSame('', self::inkOf($output), 'Icon rendered no ink at all.');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function darkPaintProvider(): iterable
    {
        $open = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">';
        $box = static fn (string $fill): string => $open
            .'<rect x="2" y="2" width="12" height="12"'.$fill.'/></svg>';

        yield 'no fill attribute (spec default black)' => [$box('')];
        yield 'fill="#000000"' => [$box(' fill="#000000"')];
        yield 'fill="black"' => [$box(' fill="black"')];
        yield 'fill="#333333"' => [$box(' fill="#333333"')];
        yield 'fill="#5f6368" (Material Symbols)' => [$box(' fill="#5f6368"')];
        yield 'fill="red"' => [$box(' fill="red"')];
        yield 'style="fill:#000"' => [$box(' style="fill:#000"')];
        yield 'inherited from dark group' => [$open
            .'<g fill="#000000"><rect x="2" y="2" width="12" height="12"/></g></svg>'];
        yield 'style element with dark fill' => [$open
            .'<style>.a { fill: #000 }</style>'
            .'<rect class="a" x="2" y="2" width="12" height="12"/></svg>'];
        yield 'stroke only, dark' => [$open
            .'<path d="M2 8H14" stroke="#000000" stroke-width="4"/></svg>'];
    }

    #[Test]
    public function transparentAreasStayBlank(): void
    {
        // Normalising paint must not turn "nothing drawn" into ink.
        $output = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"/>',
        )->height(4)->sampler(new HalfBlockSampler())->toString();

        self::assertSame('', self::inkOf($output));
    }

    #[Test]
    public function partialCoverageStillDistinguishesShape(): void
    {
        // A half-covered canvas must not become a solid block: coverage still matters.
        $output = Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect x="0" y="0" width="8" height="16" fill="#000000"/></svg>',
        )->height(4)->sampler(new HalfBlockSampler())->toString();

        $rows = explode("\n", rtrim($output, "\n"));
        self::assertSame(4, count($rows));
        foreach ($rows as $row) {
            $visible = preg_replace('/\e\[[0-9;]*m/', '', $row);
            self::assertIsString($visible);
            self::assertStringContainsString('█', $visible, 'Left half should be filled.');
            self::assertStringContainsString(' ', $visible, 'Right half should stay blank.');
        }
    }

    private static function inkOf(string $output): string
    {
        $stripped = preg_replace('/\e\[[0-9;]*m|[\s\x{2800}]/u', '', $output);
        self::assertIsString($stripped);

        return $stripped;
    }
}
