<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

use Wazum\Stipple\Exception\InvalidArgumentException;

abstract class AbstractSampler implements SamplerInterface
{
    protected const RESET_SGR = "\e[0m";
    protected const DEFAULT_FG_SGR = "\e[39m";
    private const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    abstract public function pixelsPerCellX(): int;

    abstract public function pixelsPerCellY(): int;

    abstract public function sample(\GdImage $image, ?string $foregroundHex, float $threshold): string;

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

        $rgba = imagecolorat($image, $x, $y);
        if ($rgba === false) {
            return false;
        }

        // Hand-unpacking 0xAARRGGBB avoids an imagecolorsforindex() array per pixel.
        $luminance = (
            0.299 * (($rgba >> 16) & 0xFF)
            + 0.587 * (($rgba >> 8) & 0xFF)
            + 0.114 * ($rgba & 0xFF)
        ) / 255.0;
        $opacity = 1.0 - ((($rgba >> 24) & 0x7F) / 127.0);
        $weight = $luminance * $opacity;

        return $weight > 0.0 && $weight >= $threshold;
    }

    /**
     * Re-validated rather than trusting the facade, because samplers are a public extension
     * point: hexdec() on a malformed value emits deprecations and yields a wrong colour.
     *
     * @throws InvalidArgumentException when $foregroundHex is not a 6-digit hex colour
     */
    final protected function buildForegroundSgr(?string $foregroundHex): string
    {
        if ($foregroundHex === null) {
            return self::DEFAULT_FG_SGR;
        }

        if (preg_match(self::HEX_PATTERN, $foregroundHex) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Foreground must be a 6-digit hex like "#aabbcc"; got %s.',
                $foregroundHex,
            ));
        }

        $red = (int) hexdec(substr($foregroundHex, 1, 2));
        $green = (int) hexdec(substr($foregroundHex, 3, 2));
        $blue = (int) hexdec(substr($foregroundHex, 5, 2));

        return "\e[38;2;{$red};{$green};{$blue}m";
    }
}
