<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

final class HalfBlockSampler extends AbstractSampler
{
    private const CHAR_BLANK = ' ';
    private const CHAR_TOP = "\u{2580}";
    private const CHAR_BOTTOM = "\u{2584}";
    private const CHAR_FULL = "\u{2588}";

    private const PIXELS_PER_CELL_X = 1;
    private const PIXELS_PER_CELL_Y = 2;

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
        $foregroundSgr = $this->buildForegroundSgr($foregroundHex);

        $output = '';
        for ($cellRow = 0; $cellRow < $cellRows; $cellRow++) {
            $cellsString = '';
            $hasNonBlank = false;
            $topRow = self::PIXELS_PER_CELL_Y * $cellRow;
            $bottomRow = $topRow + 1;

            for ($column = 0; $column < $widthPx; $column++) {
                $topOn = $this->pixelOn($image, $column, $topRow, $widthPx, $heightPx, $threshold);
                $bottomOn = $this->pixelOn($image, $column, $bottomRow, $widthPx, $heightPx, $threshold);

                $character = match (true) {
                    $topOn && $bottomOn => self::CHAR_FULL,
                    $topOn && !$bottomOn => self::CHAR_TOP,
                    !$topOn && $bottomOn => self::CHAR_BOTTOM,
                    default => self::CHAR_BLANK,
                };

                if ($character !== self::CHAR_BLANK) {
                    $hasNonBlank = true;
                }
                $cellsString .= $character;
            }

            $output .= $hasNonBlank
                ? $foregroundSgr.$cellsString.self::RESET_SGR."\n"
                : $cellsString."\n";
        }

        return $output;
    }
}
