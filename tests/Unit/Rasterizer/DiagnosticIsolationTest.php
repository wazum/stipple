<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit\Rasterizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Stipple;

/**
 * The output contract is "a string you echo to a terminal", so a PHP diagnostic printed
 * mid-render lands inside the rendered frame. meyfa/php-svg emits E_DEPRECATED for
 * fractional coordinates, which ordinary icons produce at most heights.
 */
final class DiagnosticIsolationTest extends TestCase
{
    #[Test]
    #[DataProvider('ordinaryIconProvider')]
    public function renderingEmitsNoDiagnostics(string $svg, int $height): void
    {
        /** @var list<string> $seen */
        $seen = [];
        set_error_handler(static function (int $severity, string $message) use (&$seen): bool {
            $seen[] = $severity.': '.$message;

            return true;
        });

        try {
            Stipple::makeFromString($svg)->height($height)->sampler(new HalfBlockSampler())->toString();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $seen, 'Rendering leaked PHP diagnostics into the caller.');
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function ordinaryIconProvider(): iterable
    {
        $rect = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect x="2" y="2" width="12" height="12" fill="currentColor"/></svg>';
        $rounded = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect x="1" y="1" width="14" height="14" rx="3" fill="currentColor"/></svg>';

        foreach ([1, 3, 5, 6, 7, 10] as $height) {
            yield "plain rect at height $height" => [$rect, $height];
            yield "rounded rect at height $height" => [$rounded, $height];
        }
    }

    /**
     * Swallowing noise must not swallow real trouble: a genuine warning still has to surface
     * as a StippleException rather than being printed or ignored.
     */
    #[Test]
    public function realRasterizerWarningsStillBecomeExceptions(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100000 1">'
            .'<rect width="100000" height="1" fill="#ffffff"/></svg>';

        $this->expectException(\Wazum\Stipple\Exception\StippleException::class);
        Stipple::makeFromString($svg)->height(256)->maxRasterDimension(PHP_INT_MAX)->toString();
    }

    #[Test]
    public function hostErrorHandlerIsRestoredAndStillReceivesItsOwnErrors(): void
    {
        /** @var list<string> $seen */
        $seen = [];
        set_error_handler(static function (int $severity, string $message) use (&$seen): bool {
            $seen[] = $message;

            return true;
        });

        try {
            Stipple::makeFromString(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
                .'<rect x="1" y="1" width="14" height="14" rx="3" fill="currentColor"/></svg>',
            )->height(3)->toString();

            trigger_error('host warning', \E_USER_WARNING);
            self::assertSame(['host warning'], $seen);
        } finally {
            restore_error_handler();
        }
    }
}
