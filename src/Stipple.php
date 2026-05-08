<?php

declare(strict_types=1);

namespace Wazum\Stipple;

use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Rasterizer\PhpSvgRasterizer;
use Wazum\Stipple\Rasterizer\RasterizerInterface;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\SamplerInterface;

final class Stipple
{
    public const DEFAULT_MAX_RASTER_DIMENSION = 4096;
    private const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /** @var int<1, 256> */
    private int $heightCells = 8;

    private ?string $foregroundHex = null;
    private ?string $accentHex = null;
    private float $threshold = 0.5;
    private int $maxRasterDimension = self::DEFAULT_MAX_RASTER_DIMENSION;

    private ?RasterizerInterface $rasterizer = null;
    private ?SamplerInterface $sampler = null;

    private function __construct(private readonly string $svg)
    {
    }

    public static function render(string $path): string
    {
        return self::make($path)->toString();
    }

    public static function renderFromString(string $svg): string
    {
        return self::makeFromString($svg)->toString();
    }

    public static function make(string $path): self
    {
        $svg = @file_get_contents($path);
        if ($svg === false) {
            throw new InvalidArgumentException(sprintf('Cannot read SVG from path: %s', $path));
        }

        return new self($svg);
    }

    public static function makeFromString(string $svg): self
    {
        return new self($svg);
    }

    public function height(int $cells): self
    {
        if ($cells < 1 || $cells > 256) {
            throw new InvalidArgumentException(sprintf('Height must be in [1, 256]; got %d.', $cells));
        }

        $clone = clone $this;
        $clone->heightCells = $cells;

        return $clone;
    }

    public function color(?string $hex): self
    {
        if ($hex !== null && preg_match(self::HEX_PATTERN, $hex) !== 1) {
            throw new InvalidArgumentException(sprintf('Color must be a 6-digit hex like "#aabbcc"; got %s.', $hex));
        }

        $clone = clone $this;
        $clone->foregroundHex = $hex === null ? null : strtolower($hex);

        return $clone;
    }

    public function accent(?string $hex): self
    {
        if ($hex !== null && preg_match(self::HEX_PATTERN, $hex) !== 1) {
            throw new InvalidArgumentException(sprintf('Accent must be a 6-digit hex like "#aabbcc"; got %s.', $hex));
        }

        $clone = clone $this;
        $clone->accentHex = $hex === null ? null : strtolower($hex);

        return $clone;
    }

    public function threshold(float $luminance): self
    {
        if ($luminance < 0.0 || $luminance > 1.0) {
            throw new InvalidArgumentException(sprintf('Threshold must be in [0.0, 1.0]; got %s.', (string) $luminance));
        }

        $clone = clone $this;
        $clone->threshold = $luminance;

        return $clone;
    }

    public function maxRasterDimension(int $pixels): self
    {
        if ($pixels < 1) {
            throw new InvalidArgumentException(sprintf('maxRasterDimension must be a positive integer; got %d.', $pixels));
        }

        $clone = clone $this;
        $clone->maxRasterDimension = $pixels;

        return $clone;
    }

    public function rasterizer(RasterizerInterface $rasterizer): self
    {
        $clone = clone $this;
        $clone->rasterizer = $rasterizer;

        return $clone;
    }

    public function sampler(SamplerInterface $sampler): self
    {
        $clone = clone $this;
        $clone->sampler = $sampler;

        return $clone;
    }

    public function toString(): string
    {
        $sampler = $this->sampler ?? new BrailleSampler();
        $rasterizer = $this->rasterizer ?? new PhpSvgRasterizer();
        $preprocessor = new SvgPreprocessor();

        $cleaned = $preprocessor->clean($this->svg, $this->accentHex);

        // Cell display aspect is roughly 1:2 (width:height); doubling the
        // cell-width count derived from the SVG's aspect ratio keeps the icon
        // visually undistorted at the terminal.
        $cellsWide = max(1, (int) round($this->heightCells * $cleaned->aspectRatio * 2));

        $widthPx = $cellsWide * $sampler->pixelsPerCellX();
        $heightPx = $this->heightCells * $sampler->pixelsPerCellY();

        if ($widthPx > $this->maxRasterDimension || $heightPx > $this->maxRasterDimension) {
            throw new InvalidArgumentException(sprintf(
                'Computed raster dimensions %dx%d exceed maxRasterDimension (%d). '.
                'Reduce height(), pre-crop the SVG, or raise the cap via maxRasterDimension().',
                $widthPx,
                $heightPx,
                $this->maxRasterDimension,
            ));
        }

        $image = $rasterizer->rasterize($cleaned->svg, $widthPx, $heightPx);

        return $sampler->sample($image, $this->foregroundHex, $this->threshold);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
