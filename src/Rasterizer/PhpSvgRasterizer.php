<?php

declare(strict_types=1);

namespace Wazum\Stipple\Rasterizer;

use SVG\SVG;
use Wazum\Stipple\Exception\RasterizationFailedException;

final class PhpSvgRasterizer implements RasterizerInterface
{
    public function rasterize(string $svg, int $widthPx, int $heightPx): \GdImage
    {
        if ($widthPx <= 0 || $heightPx <= 0) {
            throw new RasterizationFailedException(sprintf(
                'Target dimensions must be positive; got %dx%d.',
                $widthPx,
                $heightPx,
            ));
        }

        // meyfa/php-svg leaks SimpleXML warnings on malformed input. Convert every
        // non-fatal severity to an exception so callers get a single
        // RasterizationFailedException instead of a warning-plus-failure pair
        // noisy enough to fail PHPUnit's failOnWarning.
        $errorMask = \E_WARNING | \E_NOTICE | \E_DEPRECATED
            | \E_USER_WARNING | \E_USER_NOTICE | \E_USER_DEPRECATED;
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, $errorMask);

        try {
            $document = SVG::fromString($svg);
        } catch (\Throwable $cause) {
            throw new RasterizationFailedException(
                'meyfa/php-svg failed to parse the SVG: '.$cause->getMessage(),
                previous: $cause,
            );
        } finally {
            restore_error_handler();
        }

        if ($document === null) {
            throw new RasterizationFailedException('meyfa/php-svg returned a null document.');
        }

        try {
            /** @var \GdImage $image meyfa/php-svg still types this as resource (legacy GD); on PHP 8+ it is always GdImage. */
            $image = $document->toRasterImage($widthPx, $heightPx);
        } catch (\Throwable $cause) {
            throw new RasterizationFailedException(
                'meyfa/php-svg failed to rasterize: '.$cause->getMessage(),
                previous: $cause,
            );
        }

        if (!$image instanceof \GdImage) {
            throw new RasterizationFailedException('meyfa/php-svg returned a non-GdImage value.');
        }

        // meyfa/php-svg returns a true-colour image with alpha already saved.
        // We re-assert the GD flags so consumers can rely on alpha reads.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }
}
