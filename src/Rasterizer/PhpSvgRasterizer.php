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

        $this->installDiagnosticHandler();

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

        // The handler has to span rasterization too: php-svg emits E_DEPRECATED for the
        // fractional coordinates that ordinary icons produce at most heights, and with
        // display_errors on that text lands in the middle of the rendered frame.
        $this->installDiagnosticHandler();

        try {
            /** @var \GdImage $image meyfa/php-svg still types this as resource (legacy GD); on PHP 8+ it is always GdImage. */
            $image = $document->toRasterImage($widthPx, $heightPx);
        } catch (\Throwable $cause) {
            throw new RasterizationFailedException(
                'meyfa/php-svg failed to rasterize: '.$cause->getMessage(),
                previous: $cause,
            );
        } finally {
            restore_error_handler();
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

    /**
     * Deprecations and notices from the dependency are noise the caller cannot act on, so they
     * are dropped rather than printed into the output. Warnings mean something actually went
     * wrong, so they become a RasterizationFailedException via the surrounding catch.
     */
    private function installDiagnosticHandler(): void
    {
        $mask = \E_WARNING | \E_NOTICE | \E_DEPRECATED
            | \E_USER_WARNING | \E_USER_NOTICE | \E_USER_DEPRECATED;

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (($severity & (\E_DEPRECATED | \E_USER_DEPRECATED | \E_NOTICE | \E_USER_NOTICE)) !== 0) {
                return true;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, $mask);
    }
}
