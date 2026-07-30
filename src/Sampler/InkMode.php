<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

enum InkMode
{
    /**
     * A pixel is ink when it is opaque enough, whatever colour it is. Correct for the
     * single-colour icons this library exists to render: monochrome output has one ink
     * colour, so the declared paint carries no information — only its coverage does.
     */
    case Coverage;

    /**
     * A pixel is ink when brightness x opacity clears the threshold. Use for artwork that
     * draws light shapes on a dark background, where the colours carry the shape.
     */
    case Luminance;
}
