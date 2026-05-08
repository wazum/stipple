<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

interface SamplerInterface
{
    /**
     * Width in pixels each output cell consumes from the rasterized image.
     * The facade uses this to size the rasterization so the sampler grid
     * fills the output cell-block exactly.
     */
    public function pixelsPerCellX(): int;

    public function pixelsPerCellY(): int;

    /**
     * Convert a rasterized image into a monochrome ANSI string. Each row ends with
     * "\n"; non-blank rows are wrapped in <fg-SGR>…\e[0m so callers can echo the
     * result directly. $foregroundHex is null for the terminal default fg, otherwise
     * a 6-digit "#rrggbb" emitted as a 24-bit truecolor SGR.
     */
    public function sample(\GdImage $image, ?string $foregroundHex, float $threshold): string;
}
