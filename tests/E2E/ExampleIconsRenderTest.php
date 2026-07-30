<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Sampler\SamplerInterface;
use Wazum\Stipple\Stipple;

/**
 * The portable half of the real-icon coverage. IconGoldenTest pins exact output but only runs
 * where the recording's GD build is present; these assertions hold on any build, and are what
 * would catch an icon going blank, losing most of its shape, or breaking its row geometry.
 */
final class ExampleIconsRenderTest extends TestCase
{
    private const ICON_DIR = __DIR__.'/../../examples/icons';

    #[Test]
    #[DataProvider('iconAndSamplerProvider')]
    public function exampleIconRendersWithPlausibleGeometryAndInk(
        string $name,
        SamplerInterface $sampler,
        int $height,
    ): void {
        $icon = Stipple::make(self::ICON_DIR.'/'.$name.'.svg')
            ->height($height)
            ->sampler($sampler)
            ->toIcon();

        self::assertCount($height, $icon->rows, 'Row count must equal the requested height.');
        self::assertSame($height, $icon->heightCells);

        $inkCells = 0;
        foreach ($icon->rows as $row) {
            $visible = preg_replace('/\e\[[0-9;]*m/', '', $row);
            self::assertIsString($visible);
            self::assertSame(
                $icon->widthCells,
                mb_strlen($visible),
                'Every row must be exactly widthCells wide.',
            );
            $inkCells += mb_strlen((string) preg_replace('/[\s\x{2800}]/u', '', $visible));
        }

        $totalCells = $icon->widthCells * $icon->heightCells;
        self::assertGreaterThan(
            (int) ($totalCells * 0.05),
            $inkCells,
            sprintf('%s rendered almost nothing (%d of %d cells).', $name, $inkCells, $totalCells),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: SamplerInterface, 2: int}>
     */
    public static function iconAndSamplerProvider(): iterable
    {
        $files = glob(self::ICON_DIR.'/*.svg');
        self::assertIsArray($files);
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $name = basename($file, '.svg');
            yield $name.' braille' => [$name, new BrailleSampler(), 8];
            yield $name.' half-block' => [$name, new HalfBlockSampler(), 8];
        }
    }
}
