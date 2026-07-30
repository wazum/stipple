<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Sampler\SamplerInterface;
use Wazum\Stipple\Sampler\SamplerOptions;
use Wazum\Stipple\Stipple;

/**
 * Without this, piping to a file or running a Symfony Console command with --no-ansi leaves raw
 * escape codes in the output: Console strips its own markup when not decorated, but not ours.
 */
final class PlainOutputTest extends TestCase
{
    private const ICON = __DIR__.'/../../examples/icons/actions-heart.svg';

    #[Test]
    #[DataProvider('samplerProvider')]
    public function plainOutputContainsNoEscapeCodes(SamplerInterface $sampler): void
    {
        $output = Stipple::make(self::ICON)->height(4)->sampler($sampler)->decorated(false)->toString();

        self::assertStringNotContainsString("\e", $output);
        self::assertSame(4, substr_count($output, "\n"));
    }

    #[Test]
    #[DataProvider('samplerProvider')]
    public function plainOutputEqualsDecoratedOutputWithTheCodesRemoved(SamplerInterface $sampler): void
    {
        $base = Stipple::make(self::ICON)->height(4)->sampler($sampler);

        $stripped = preg_replace('/\e\[[0-9;]*m/', '', $base->color('#00ffff')->toString());
        self::assertIsString($stripped);

        self::assertSame($stripped, $base->decorated(false)->toString());
    }

    #[Test]
    public function colourIsIgnoredWhenNotDecorated(): void
    {
        $base = Stipple::make(self::ICON)->height(4)->sampler(new HalfBlockSampler())->decorated(false);

        self::assertSame($base->toString(), $base->color('#ff8700')->toString());
    }

    #[Test]
    public function decoratedIsTheDefault(): void
    {
        self::assertTrue((new SamplerOptions())->decorated);
        self::assertStringContainsString(
            "\e[",
            Stipple::make(self::ICON)->height(2)->toString(),
        );
    }

    #[Test]
    public function withDecoratedKeepsEveryOtherSetting(): void
    {
        $original = new SamplerOptions('#00ffff', 0.25);
        $plain = $original->withDecorated(false);

        self::assertFalse($plain->decorated);
        self::assertSame('#00ffff', $plain->foregroundHex);
        self::assertSame(0.25, $plain->threshold);
        self::assertTrue($original->decorated, 'The original must not change.');
    }

    #[Test]
    public function commandHasAPlainFlag(): void
    {
        $command = new \Wazum\Stipple\Console\StippleCommand();

        self::assertStringNotContainsString("\e", $command->render(['--plain', '--height=2', self::ICON]));
        self::assertStringContainsString('--plain', $command->usage());
    }

    /**
     * @return iterable<string, array{0: SamplerInterface}>
     */
    public static function samplerProvider(): iterable
    {
        yield 'braille' => [new BrailleSampler()];
        yield 'half-block' => [new HalfBlockSampler()];
    }
}
