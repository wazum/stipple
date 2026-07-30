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
     * The glyph an all-off cell renders as. Callers padding or aligning output must use this
     * rather than a space — Braille blanks are U+2800, which some fonts size differently.
     */
    public function blankCell(): string;

    /**
     * Convert a rasterized true-colour image into a monochrome ANSI string. Each row ends
     * with "\n"; non-blank rows are wrapped in <fg-SGR>…\e[0m so callers can echo the
     * result directly.
     */
    public function sample(\GdImage $image, SamplerOptions $options): string;
}
