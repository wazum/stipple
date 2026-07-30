<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit\Sampler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidArgumentException;
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
    public function boundsAreInclusive(): void
    {
        self::assertSame(0.0, (new SamplerOptions(null, 0.0))->threshold);
        self::assertSame(1.0, (new SamplerOptions(null, 1.0))->threshold);
    }
}
