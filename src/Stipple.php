<?php

declare(strict_types=1);

namespace Wazum\Stipple;

use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Rasterizer\PhpSvgRasterizer;
use Wazum\Stipple\Rasterizer\RasterizerInterface;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\InkMode;
use Wazum\Stipple\Sampler\SamplerInterface;
use Wazum\Stipple\Sampler\SamplerOptions;

final class Stipple
{
    public const DEFAULT_MAX_RASTER_DIMENSION = 4096;

    /** Generous for an icon; guards against reading an arbitrarily large file into memory. */
    private const MAX_INPUT_BYTES = 4 * 1024 * 1024;
    private const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /** @var int<1, 256> */
    private int $heightCells = 8;

    private ?string $accentHex = null;
    private int $maxRasterDimension = self::DEFAULT_MAX_RASTER_DIMENSION;

    private SamplerOptions $samplerOptions;
    private ?RasterizerInterface $rasterizer = null;
    private ?SamplerInterface $sampler = null;

    private function __construct(private readonly string $svg)
    {
        $this->samplerOptions = new SamplerOptions();
    }

    public static function render(string $path): string
    {
        return self::make($path)->toString();
    }

    public static function renderFromString(string $svg): string
    {
        return self::makeFromString($svg)->toString();
    }

    /**
     * Local filesystem paths only. Stream wrappers (http://, data://, …) are refused because
     * allow_url_fopen is on by default; fetch such SVG yourself and use makeFromString().
     */
    public static function make(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Not a readable local file: %s', self::describePath($path)));
        }

        // Reading first and asking later would let a huge file exhaust memory before any check.
        $size = @filesize($path);
        if ($size !== false && $size > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException(sprintf(
                'SVG at %s is %d bytes, over the %d byte limit for an icon.',
                self::describePath($path),
                $size,
                self::MAX_INPUT_BYTES,
            ));
        }

        $svg = @file_get_contents($path, false, null, 0, self::MAX_INPUT_BYTES + 1);
        if ($svg === false) {
            throw new InvalidArgumentException(sprintf('Cannot read SVG from path: %s', self::describePath($path)));
        }
        if (strlen($svg) > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException(sprintf(
                'SVG at %s exceeds the %d byte limit for an icon.',
                self::describePath($path),
                self::MAX_INPUT_BYTES,
            ));
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
        $clone = clone $this;
        $clone->samplerOptions = $this->samplerOptions->withForegroundHex($hex);

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
        $clone = clone $this;
        $clone->samplerOptions = $this->samplerOptions->withThreshold($luminance);

        return $clone;
    }

    /**
     * Coverage (the default) inks any sufficiently opaque pixel whatever its colour;
     * Luminance weights by brightness, for light-on-dark artwork.
     */
    public function inkMode(InkMode $mode): self
    {
        $clone = clone $this;
        $clone->samplerOptions = $this->samplerOptions->withInkMode($mode);

        return $clone;
    }

    /**
     * Replaces every sampler setting at once; the individual withers are additive.
     */
    public function samplerOptions(SamplerOptions $options): self
    {
        $clone = clone $this;
        $clone->samplerOptions = $options;

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
        return $this->toIcon()->toString();
    }

    /**
     * The rendered rows plus their cell dimensions, for laying icons out next to other output.
     * toString() is this, joined.
     */
    public function toIcon(): RenderedIcon
    {
        $sampler = $this->sampler ?? new BrailleSampler();
        $rasterizer = $this->rasterizer ?? new PhpSvgRasterizer();
        $preprocessor = new SvgPreprocessor();

        $cleaned = $preprocessor->clean($this->svg, $this->accentHex);

        // Cell display aspect is roughly 1:2 (width:height); doubling the
        // cell-width count derived from the SVG's aspect ratio keeps the icon
        // visually undistorted at the terminal.
        $cellsWideExact = $this->heightCells * $cleaned->aspectRatio * 2;

        // Checked as floats: casting an out-of-range value to int is undefined, and max(1, …)
        // would then launder the garbage into a plausible-looking one-cell icon.
        $widthPxExact = $cellsWideExact * $sampler->pixelsPerCellX();
        $heightPx = $this->heightCells * $sampler->pixelsPerCellY();

        if (!is_finite($widthPxExact)
            || $widthPxExact > $this->maxRasterDimension
            || $heightPx > $this->maxRasterDimension) {
            throw new InvalidArgumentException(sprintf(
                'Computed raster dimensions %sx%d exceed maxRasterDimension (%d). '.
                'Reduce height(), pre-crop the SVG, or raise the cap via maxRasterDimension().',
                $this->describeWidth($widthPxExact),
                $heightPx,
                $this->maxRasterDimension,
            ));
        }

        $cellsWide = max(1, (int) round($cellsWideExact));
        $widthPx = $cellsWide * $sampler->pixelsPerCellX();

        $image = $rasterizer->rasterize($cleaned->svg, $widthPx, $heightPx);
        $sampled = $sampler->sample($image, $this->samplerOptions);

        return new RenderedIcon(
            $sampled === '' ? [] : explode("\n", rtrim($sampled, "\n")),
            $cellsWide,
            $this->heightCells,
            $sampler->blankCell(),
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Casting to int is only safe in range, and %f on an astronomical ratio would print hundreds
     * of digits into the message.
     */
    private function describeWidth(float $widthPx): string
    {
        return match (true) {
            !is_finite($widthPx) => 'infinite',
            $widthPx > (float) \PHP_INT_MAX => sprintf('%.3E', $widthPx),
            default => sprintf('%d', (int) $widthPx),
        };
    }

    /**
     * Filenames are attacker-controlled when a CLI enumerates an untrusted directory, and these
     * messages get printed to a terminal, so control sequences must not survive into them.
     */
    private static function describePath(string $path): string
    {
        $escaped = preg_replace('/[\x00-\x1F\x7F]/', '?', $path);

        return $escaped ?? '(unprintable path)';
    }
}
