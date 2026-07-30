<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Stipple;
use Wazum\Stipple\SvgPreprocessor;

/**
 * meyfa/php-svg tokenises path data with a bare number regex, so it cannot read arc flags
 * written without separators — the form every SVG minifier emits — and it silently discards
 * the rest of the path when an argument count does not match. Normalising the data before
 * the rasterizer sees it is the only place this can be fixed.
 */
final class PathNormalisationTest extends TestCase
{
    private SvgPreprocessor $preprocessor;

    protected function setUp(): void
    {
        $this->preprocessor = new SvgPreprocessor();
    }

    #[Test]
    #[DataProvider('equivalentPathProvider')]
    public function minifiedPathRendersLikeItsExpandedForm(string $minified, string $expanded): void
    {
        self::assertSame(
            $this->render($expanded),
            $this->render($minified),
            'Minified path did not render identically to its expanded equivalent.',
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function equivalentPathProvider(): iterable
    {
        yield 'both arc flags packed together' => [
            'M18 10a8 8 0 11-16 0 8 8 0 0116 0z',
            'M18 10a8 8 0 1 1 -16 0 8 8 0 0 1 16 0z',
        ];
        yield 'flags packed with the x coordinate' => [
            'M2 8a6 6 0 016 0',
            'M2 8a6 6 0 0 1 6 0',
        ];
        yield 'uppercase exponent' => [
            'M0 0H1.4E1V1.4E1H0z',
            'M0 0H14V14H0z',
        ];
        yield 'command after a packed arc still parsed' => [
            'M1 1h6v6H1zA8 8 0 11-16 0M9 9h6v6H9z',
            'M1 1h6v6H1zA8 8 0 1 1 -16 0M9 9h6v6H9z',
        ];
        yield 'repeated arc argument sets' => [
            'M2 2a2 2 0 012 2a2 2 0 012 2',
            'M2 2a2 2 0 0 1 2 2a2 2 0 0 1 2 2',
        ];
    }

    #[Test]
    public function everyCommandInAMinifiedPathSurvives(): void
    {
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
            .'<path d="M1 1h6v6H1zA8 8 0 11-16 0M9 9h6v6H9z" fill="currentColor"/></svg>',
            null,
        );

        // The trailing subpath must still be present after the arc.
        self::assertMatchesRegularExpression('/d="[^"]*M\s*9[ ,]/', $result->svg);
    }

    #[Test]
    public function realWorldMinifiedIconRendersInk(): void
    {
        // Heroicons v1 solid "information-circle", verbatim.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">'
            .'<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0z'
            .'M9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>';

        $rendered = Stipple::makeFromString($svg)->height(8)->sampler(new HalfBlockSampler())->toString();
        $ink = preg_replace('/\e\[[0-9;]*m|\s/', '', $rendered);
        self::assertIsString($ink);

        // The disc fills most of an 8-row by 16-cell block; the truncated form managed 9 cells.
        self::assertGreaterThan(60, mb_strlen($ink));
    }

    private function render(string $pathData): string
    {
        return Stipple::makeFromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
            .'<path d="'.$pathData.'" fill="currentColor"/></svg>',
        )->height(8)->sampler(new HalfBlockSampler())->toString();
    }
}
