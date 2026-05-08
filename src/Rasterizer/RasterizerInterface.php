<?php

declare(strict_types=1);

namespace Wazum\Stipple\Rasterizer;

use Wazum\Stipple\Exception\RasterizationFailedException;

interface RasterizerInterface
{
    /**
     * Rasterize an already-preprocessed SVG (colours resolved, scripts stripped) to a true-colour
     * GD image of exactly $widthPx x $heightPx with alpha preserved.
     *
     * @throws RasterizationFailedException on backend failure
     */
    public function rasterize(string $svg, int $widthPx, int $heightPx): \GdImage;
}
