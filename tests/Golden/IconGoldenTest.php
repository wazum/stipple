<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Golden;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Sampler\SamplerInterface;
use Wazum\Stipple\Stipple;

/**
 * Records how every bundled example icon actually renders, so that any change to the
 * pipeline shows up as a reviewable picture rather than a number. ESC is written as a
 * literal "\e" to keep the files diffable while staying byte-exact.
 *
 * Re-record intentional changes with: STIPPLE_UPDATE_GOLDEN=1 vendor/bin/phpunit
 */
final class IconGoldenTest extends TestCase
{
    private const ICON_DIR = __DIR__.'/../../examples/icons';
    private const GOLDEN_DIR = __DIR__.'/expected';

    #[Test]
    #[DataProvider('iconProvider')]
    public function iconRendersAsRecorded(string $name): void
    {
        $actual = $this->renderReport($name);
        $goldenFile = self::GOLDEN_DIR.'/'.$name.'.txt';

        if (getenv('STIPPLE_UPDATE_GOLDEN') === '1') {
            file_put_contents($goldenFile, $actual);
        }

        self::assertFileExists(
            $goldenFile,
            sprintf('No golden file for "%s". Re-record with STIPPLE_UPDATE_GOLDEN=1.', $name),
        );

        $expected = file_get_contents($goldenFile);
        self::assertIsString($expected);
        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function iconProvider(): iterable
    {
        $files = glob(self::ICON_DIR.'/*.svg');
        self::assertIsArray($files);
        self::assertNotSame([], $files, 'No example icons found.');

        foreach ($files as $file) {
            $name = basename($file, '.svg');
            yield $name => [$name];
        }
    }

    private function renderReport(string $name): string
    {
        $path = self::ICON_DIR.'/'.$name.'.svg';

        $report = '';
        foreach (self::combinations() as $label => [$sampler, $height]) {
            $rendered = Stipple::make($path)->height($height)->sampler($sampler)->toString();
            $report .= sprintf("== %s ==\n%s\n", $label, str_replace("\e", '\e', rtrim($rendered, "\n")));
        }

        return $report;
    }

    /**
     * @return iterable<string, array{0: SamplerInterface, 1: int}>
     */
    private static function combinations(): iterable
    {
        yield 'braille height 4' => [new BrailleSampler(), 4];
        yield 'braille height 8' => [new BrailleSampler(), 8];
        yield 'half-block height 8' => [new HalfBlockSampler(), 8];
    }
}
