<?php

declare(strict_types=1);

namespace Wazum\Stipple\Sampler;

use Wazum\Stipple\Exception\InvalidArgumentException;

/**
 * Sampling settings, validated on construction so a sampler can never be handed an
 * unusable colour or threshold. Grouped into one object so later options do not have to
 * change SamplerInterface::sample()'s signature.
 */
final readonly class SamplerOptions
{
    private const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public ?string $foregroundHex;

    /**
     * @param ?string $foregroundHex 6-digit "#rrggbb", or null for the terminal default
     * @param float   $threshold     cutoff in [0.0, 1.0], applied to whichever $inkMode measures
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?string $foregroundHex = null,
        public float $threshold = 0.5,
        public InkMode $inkMode = InkMode::Coverage,
    ) {
        if ($foregroundHex !== null && preg_match(self::HEX_PATTERN, $foregroundHex) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Foreground must be a 6-digit hex like "#aabbcc"; got %s.',
                $foregroundHex,
            ));
        }

        // NAN passes both range comparisons, and casting it into a message warns on PHP 8.5.
        if (!is_finite($threshold)) {
            throw new InvalidArgumentException('Threshold must be a finite number in [0.0, 1.0].');
        }
        if ($threshold < 0.0 || $threshold > 1.0) {
            throw new InvalidArgumentException(sprintf(
                'Threshold must be in [0.0, 1.0]; got %s.',
                (string) $threshold,
            ));
        }

        $this->foregroundHex = $foregroundHex === null ? null : strtolower($foregroundHex);
    }

    public function withForegroundHex(?string $foregroundHex): self
    {
        return new self($foregroundHex, $this->threshold, $this->inkMode);
    }

    public function withThreshold(float $threshold): self
    {
        return new self($this->foregroundHex, $threshold, $this->inkMode);
    }

    public function withInkMode(InkMode $inkMode): self
    {
        return new self($this->foregroundHex, $this->threshold, $inkMode);
    }
}
