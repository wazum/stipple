<?php

declare(strict_types=1);

namespace Wazum\Stipple;

/**
 * @internal not covered by the backward compatibility promise
 */
final readonly class PreprocessedSvg
{
    public function __construct(
        public string $svg,
        public float $aspectRatio,
    ) {
    }
}
