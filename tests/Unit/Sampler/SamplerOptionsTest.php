<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit\Sampler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Sampler\InkMode;
use Wazum\Stipple\Sampler\SamplerOptions;

final class SamplerOptionsTest extends TestCase
{
    #[Test]
    public function defaultsToTerminalForegroundAndMidThreshold(): void
    {
        $options = new SamplerOptions();

        self::assertNull($options->foregroundHex);
        self::assertSame(0.5, $options->threshold);
    }

    #[Test]
    public function hexIsNormalisedToLowercase(): void
    {
        self::assertSame('#aabbcc', (new SamplerOptions('#AaBbCc'))->foregroundHex);
    }

    #[Test]
    #[DataProvider('malformedHexProvider')]
    public function malformedHexIsRejected(string $hex): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SamplerOptions($hex);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedHexProvider(): iterable
    {
        yield 'not hex at all' => ['nonsense'];
        yield 'missing hash' => ['00ffff'];
        yield 'short form' => ['#fff'];
        yield 'named colour' => ['red'];
        yield 'empty string' => [''];
        yield 'trailing garbage' => ['#00ffffzz'];
    }

    #[Test]
    #[DataProvider('invalidThresholdProvider')]
    public function invalidThresholdIsRejected(float $threshold): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SamplerOptions(null, $threshold);
    }

    /**
     * @return iterable<string, array{0: float}>
     */
    public static function invalidThresholdProvider(): iterable
    {
        yield 'below range' => [-0.1];
        yield 'above range' => [1.5];
        yield 'NAN' => [NAN];
        yield 'INF' => [INF];
    }

    #[Test]
    public function inkModeDefaultsToCoverage(): void
    {
        self::assertSame(InkMode::Coverage, (new SamplerOptions())->inkMode);
    }

    /**
     * Each wither must carry every other setting across, or adding an option in 1.x would make
     * Stipple::color()/threshold() silently discard it.
     */
    #[Test]
    public function withersPreserveEveryOtherSetting(): void
    {
        $original = new SamplerOptions('#00ffff', 0.25, InkMode::Luminance);

        $recoloured = $original->withForegroundHex('#ff8700');
        self::assertSame('#ff8700', $recoloured->foregroundHex);
        self::assertSame(0.25, $recoloured->threshold);
        self::assertSame(InkMode::Luminance, $recoloured->inkMode);

        $rethresholded = $original->withThreshold(0.9);
        self::assertSame('#00ffff', $rethresholded->foregroundHex);
        self::assertSame(0.9, $rethresholded->threshold);
        self::assertSame(InkMode::Luminance, $rethresholded->inkMode);

        $remoded = $original->withInkMode(InkMode::Coverage);
        self::assertSame('#00ffff', $remoded->foregroundHex);
        self::assertSame(0.25, $remoded->threshold);
        self::assertSame(InkMode::Coverage, $remoded->inkMode);
    }

    #[Test]
    public function withersValidateAndLeaveTheOriginalUntouched(): void
    {
        $original = new SamplerOptions('#00ffff', 0.25);

        try {
            $original->withThreshold(NAN);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // expected
        }

        self::assertSame(0.25, $original->threshold);
        self::assertSame('#00ffff', $original->foregroundHex);
    }

    #[Test]
    public function boundsAreInclusive(): void
    {
        self::assertSame(0.0, (new SamplerOptions(null, 0.0))->threshold);
        self::assertSame(1.0, (new SamplerOptions(null, 1.0))->threshold);
    }
}
