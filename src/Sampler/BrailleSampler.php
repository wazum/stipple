<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

final class BrailleSampler extends AbstractSampler
{
    private const BRAILLE_BLOCK_START = 0x2800;
    private const PIXELS_PER_CELL_X = 2;
    private const PIXELS_PER_CELL_Y = 4;

    /**
     * Bit value for each (column, row) within a 2x4 braille cell.
     * Numbering follows Unicode U+2800: column 0 holds dots 1/2/3/7,
     * column 1 holds dots 4/5/6/8 — dots 7 and 8 sit at the bottom even
     * though their bit values (0x40, 0x80) are not contiguous with 1..6,
     * because the 8-dot block was built on top of the original 6-dot one.
     *
     * @var array<int, array<int, int>>
     */
    private const DOT_BITS = [
        0 => [0 => 0x01, 1 => 0x02, 2 => 0x04, 3 => 0x40],
        1 => [0 => 0x08, 1 => 0x10, 2 => 0x20, 3 => 0x80],
    ];

    public function pixelsPerCellX(): int
    {
        return self::PIXELS_PER_CELL_X;
    }

    public function pixelsPerCellY(): int
    {
        return self::PIXELS_PER_CELL_Y;
    }

    public function sample(\GdImage $image, ?string $foregroundHex, float $threshold): string
    {
        $widthPx = imagesx($image);
        $heightPx = imagesy($image);

        $cellRows = (int) ceil($heightPx / self::PIXELS_PER_CELL_Y);
        $cellCols = (int) ceil($widthPx / self::PIXELS_PER_CELL_X);
        $foregroundSgr = $this->buildForegroundSgr($foregroundHex);

        $output = '';
        for ($cellRow = 0; $cellRow < $cellRows; $cellRow++) {
            $cellsString = '';
            $rowHasDot = false;

            for ($cellCol = 0; $cellCol < $cellCols; $cellCol++) {
                $bits = $this->bitsForCell(
                    $image,
                    $cellCol * self::PIXELS_PER_CELL_X,
                    $cellRow * self::PIXELS_PER_CELL_Y,
                    $widthPx,
                    $heightPx,
                    $threshold,
                );

                $cellsString .= mb_chr(self::BRAILLE_BLOCK_START + $bits, 'UTF-8');
                $rowHasDot = $rowHasDot || $bits !== 0;
            }

            $output .= $rowHasDot
                ? $foregroundSgr.$cellsString.self::RESET_SGR."\n"
                : $cellsString."\n";
        }

        return $output;
    }

    private function bitsForCell(
        \GdImage $image,
        int $cellOriginX,
        int $cellOriginY,
        int $widthPx,
        int $heightPx,
        float $threshold,
    ): int {
        $bits = 0;
        for ($column = 0; $column < self::PIXELS_PER_CELL_X; $column++) {
            for ($row = 0; $row < self::PIXELS_PER_CELL_Y; $row++) {
                if (!$this->pixelOn($image, $cellOriginX + $column, $cellOriginY + $row, $widthPx, $heightPx, $threshold)) {
                    continue;
                }
                $bits |= self::DOT_BITS[$column][$row];
            }
        }

        return $bits;
    }
}
