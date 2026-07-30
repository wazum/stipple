<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

use Wazum\Stipple\Exception\InvalidArgumentException;

abstract class AbstractSampler implements SamplerInterface
{
    protected const RESET_SGR = "\e[0m";
    protected const DEFAULT_FG_SGR = "\e[39m";

    abstract public function pixelsPerCellX(): int;

    abstract public function pixelsPerCellY(): int;

    abstract public function sample(\GdImage $image, SamplerOptions $options): string;

    /**
     * Call once per sample(), never per pixel: pixelOn() assumes packed true-colour ints.
     */
    final protected function assertTrueColorImage(\GdImage $image): void
    {
        if (!imageistruecolor($image)) {
            throw new InvalidArgumentException(
                'Sampling requires a true-colour image; got a palette image.',
            );
        }
    }

    /**
     * Whether a pixel counts as ink, per the configured InkMode and threshold. A pixel is
     * never ink unless its weight is strictly positive, so a threshold of 0.0 still means
     * "any visible coverage" rather than "everything".
     */
    final protected function pixelOn(
        \GdImage $image,
        int $x,
        int $y,
        int $widthPx,
        int $heightPx,
        SamplerOptions $options,
    ): bool {
        if ($x >= $widthPx || $y >= $heightPx) {
            return false;
        }

        $rgba = imagecolorat($image, $x, $y);
        if ($rgba === false) {
            return false;
        }

        // Hand-unpacking 0xAARRGGBB avoids an imagecolorsforindex() array per pixel.
        $opacity = 1.0 - ((($rgba >> 24) & 0x7F) / 127.0);

        $weight = match ($options->inkMode) {
            InkMode::Coverage => $opacity,
            InkMode::Luminance => $opacity * (
                0.299 * (($rgba >> 16) & 0xFF)
                + 0.587 * (($rgba >> 8) & 0xFF)
                + 0.114 * ($rgba & 0xFF)
            ) / 255.0,
        };

        return $weight > 0.0 && $weight >= $options->threshold;
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
