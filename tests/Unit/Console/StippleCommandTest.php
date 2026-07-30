<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Console\StippleCommand;
use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Exception\InvalidSvgException;

final class StippleCommandTest extends TestCase
{
    private const ICON = __DIR__.'/../../../examples/icons/actions-heart.svg';

    private StippleCommand $command;

    protected function setUp(): void
    {
        $this->command = new StippleCommand();
    }

    #[Test]
    public function rendersAFilePath(): void
    {
        $output = $this->command->render([self::ICON]);

        self::assertStringEndsWith("\n", $output);
        self::assertSame(8, substr_count($output, "\n"), 'Default height is 8 rows.');
    }

    #[Test]
    public function heightOptionChangesRowCount(): void
    {
        self::assertSame(4, substr_count($this->command->render(['--height=4', self::ICON]), "\n"));
        self::assertSame(2, substr_count($this->command->render(['--height', '2', self::ICON]), "\n"));
    }

    #[Test]
    public function halfBlockSamplerIsSelectable(): void
    {
        $output = $this->command->render(['--sampler=half-block', '--height=4', self::ICON]);

        self::assertStringContainsString('█', $output);
        self::assertStringNotContainsString('⣿', $output);
    }

    #[Test]
    public function colourOptionEmitsTruecolorSgr(): void
    {
        self::assertStringContainsString(
            "\e[38;2;0;255;255m",
            $this->command->render(['--color=#00ffff', '--height=2', self::ICON]),
        );
    }

    /**
     * Proves the flag is actually wired through: a black icon is ink under the default coverage
     * rule and nothing at all under luminance.
     */
    #[Test]
    public function inkModeOptionIsWiredThrough(): void
    {
        $blackIcon = tempnam(sys_get_temp_dir(), 'stipple').'.svg';
        file_put_contents($blackIcon, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" fill="#000000"/></svg>');

        try {
            $coverage = $this->command->render(['--height=2', $blackIcon]);
            $luminance = $this->command->render(['--ink=luminance', '--height=2', $blackIcon]);

            self::assertStringContainsString('⣿', $coverage);
            self::assertStringNotContainsString('⣿', $luminance);
        } finally {
            unlink($blackIcon);
        }
    }

    #[Test]
    public function thresholdAndAccentAreAccepted(): void
    {
        $output = $this->command->render(['--threshold=0.25', '--accent=#ff8700', '--height=2', self::ICON]);

        self::assertStringEndsWith("\n", $output);
    }

    #[Test]
    public function dashReadsFromStandardInput(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4">'
            .'<rect width="4" height="4" fill="currentColor"/></svg>';

        $output = $this->command->render(['--sampler=half-block', '--height=2', '-'], $svg);

        self::assertSame("\e[39m████\e[0m\n\e[39m████\e[0m\n", $output);
    }

    #[Test]
    public function missingPathIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->command->render(['--height=4']);
    }

    #[Test]
    public function unknownOptionIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->command->render(['--nope', self::ICON]);
    }

    #[Test]
    public function unknownSamplerIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->command->render(['--sampler=quadrant', self::ICON]);
    }

    #[Test]
    public function libraryFailuresPropagateAsStippleExceptions(): void
    {
        $broken = tempnam(sys_get_temp_dir(), 'stipple').'.svg';
        file_put_contents($broken, '<svg><text>hi</text></svg>');

        try {
            $this->expectException(InvalidSvgException::class);
            $this->command->render([$broken]);
        } finally {
            unlink($broken);
        }
    }

    #[Test]
    public function usageMentionsEveryOption(): void
    {
        $usage = $this->command->usage();

        foreach (['--height', '--sampler', '--color', '--accent', '--threshold', '--ink'] as $option) {
            self::assertStringContainsString($option, $usage);
        }
    }
}
