<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

abstract class AbstractSampler implements SamplerInterface
{
    protected const RESET_SGR = "\e[0m";
    protected const DEFAULT_FG_SGR = "\e[39m";

    abstract public function pixelsPerCellX(): int;

    abstract public function pixelsPerCellY(): int;

    abstract public function sample(\GdImage $image, ?string $foregroundHex, float $threshold): string;

    /**
     * Alpha-weighted Rec. 601 luminance, gated by $threshold. Both samplers use the
     * same rule: a pixel is "on" only if the visible luminance (luma × opacity)
     * is strictly positive AND meets the threshold.
     */
    final protected function pixelOn(
        \GdImage $image,
        int $x,
        int $y,
        int $widthPx,
        int $heightPx,
        float $threshold,
    ): bool {
        if ($x >= $widthPx || $y >= $heightPx) {
            return false;
        }

        $colorIndex = imagecolorat($image, $x, $y);
        if ($colorIndex === false) {
            return false;
        }

        $components = imagecolorsforindex($image, $colorIndex);
        $luminance = (0.299 * $components['red'] + 0.587 * $components['green'] + 0.114 * $components['blue']) / 255.0;
        $opacity = 1.0 - ($components['alpha'] / 127.0);
        $weight = $luminance * $opacity;

        return $weight > 0.0 && $weight >= $threshold;
    }

    final protected function buildForegroundSgr(?string $foregroundHex): string
    {
        if ($foregroundHex === null) {
            return self::DEFAULT_FG_SGR;
        }

        $red = (int) hexdec(substr($foregroundHex, 1, 2));
        $green = (int) hexdec(substr($foregroundHex, 3, 2));
        $blue = (int) hexdec(substr($foregroundHex, 5, 2));

        return "\e[38;2;{$red};{$green};{$blue}m";
    }
}
