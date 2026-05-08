<?php

declare(strict_types=1);

namespace Wazum\Stipple;

use Wazum\Stipple\Exception\InvalidSvgException;

final class SvgPreprocessor
{
    private const CURRENT_COLOR_REPLACEMENT = '#ffffff';
    private const ACCENT_VAR_PATTERN = '/var\(\s*--icon-color-accent\s*(?:,\s*([^)]+))?\s*\)/i';

    public function clean(string $svg, ?string $accent): PreprocessedSvg
    {
        $this->rejectDoctypeAndEntities($svg);

        // XXE defence is layered: the DOCTYPE/ENTITY pre-scan rejects any document that
        // could declare an external entity, and LIBXML_NONET blocks network resolution
        // for any that slipped through. We deliberately do not touch the global libxml
        // entity loader — through PHP 8.4 it cannot be saved/restored portably (the
        // function still returns bool), so installing one would silently clobber a
        // resolver the host app may have set.
        $previousErrorState = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML($svg, \LIBXML_NONET);
            if ($loaded === false) {
                throw new InvalidSvgException(sprintf(
                    'Failed to parse SVG: %s',
                    $this->firstLibxmlError() ?? 'unknown libxml error',
                ));
            }
            libxml_clear_errors();

            $root = $document->documentElement;
            if ($root === null || $root->localName !== 'svg') {
                throw new InvalidSvgException('Document root is not <svg>.');
            }

            $this->rejectDisallowedElements($document);
            $this->applyColorSubstitutions($document, $accent);

            $aspectRatio = $this->resolveAspectRatio($root);

            $serialized = $document->saveXML($root);
            if ($serialized === false) {
                throw new InvalidSvgException('Failed to serialize cleaned SVG.');
            }

            return new PreprocessedSvg($serialized, $aspectRatio);
        } finally {
            libxml_use_internal_errors($previousErrorState);
        }
    }

    private function rejectDoctypeAndEntities(string $svg): void
    {
        if (preg_match('/<!DOCTYPE/i', $svg) === 1) {
            throw new InvalidSvgException('SVG contains a DOCTYPE declaration; refusing to parse (XXE attack surface).');
        }
        if (preg_match('/<!ENTITY/i', $svg) === 1) {
            throw new InvalidSvgException('SVG contains an ENTITY declaration; refusing to parse (XXE attack surface).');
        }
    }

    private function rejectDisallowedElements(\DOMDocument $document): void
    {
        if ($document->getElementsByTagName('script')->length > 0) {
            throw new InvalidSvgException('SVG contains a <script> element.');
        }
        if ($document->getElementsByTagName('foreignObject')->length > 0) {
            throw new InvalidSvgException('SVG contains a <foreignObject> element.');
        }

        // Embedded raster images are out of scope for this package. We reject every
        // <image> element rather than just data: URIs because meyfa/php-svg will
        // file_get_contents() any non-data href (file://, http://, ...) which is an
        // SSRF / local file disclosure surface.
        if ($document->getElementsByTagName('image')->length > 0) {
            throw new InvalidSvgException('SVG contains an <image> element; embedded raster is not supported.');
        }
    }

    private function applyColorSubstitutions(\DOMDocument $document, ?string $accent): void
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            foreach (['fill', 'stroke'] as $attribute) {
                if (!$element->hasAttribute($attribute)) {
                    continue;
                }
                $original = $element->getAttribute($attribute);
                $rewritten = $this->rewriteAccentVar($original, $accent);
                if (strcasecmp(trim($rewritten), 'currentColor') === 0) {
                    $rewritten = self::CURRENT_COLOR_REPLACEMENT;
                }
                if ($rewritten !== $original) {
                    $element->setAttribute($attribute, $rewritten);
                }
            }

            if ($element->hasAttribute('style')) {
                $original = $element->getAttribute('style');
                $rewritten = $this->rewriteCurrentColorInStyle(
                    $this->rewriteAccentVar($original, $accent),
                );
                if ($rewritten !== $original) {
                    $element->setAttribute('style', $rewritten);
                }
            }
        }
    }

    private function rewriteCurrentColorInStyle(string $style): string
    {
        $declarations = array_filter(array_map('trim', explode(';', $style)), static fn (string $part): bool => $part !== '');
        $rewritten = [];
        foreach ($declarations as $declaration) {
            if (!str_contains($declaration, ':')) {
                $rewritten[] = $declaration;
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $property = trim($property);
            $value = trim($value);

            if (in_array(strtolower($property), ['fill', 'stroke'], true) && strcasecmp($value, 'currentColor') === 0) {
                $rewritten[] = $property.': '.self::CURRENT_COLOR_REPLACEMENT;
                continue;
            }
            $rewritten[] = $property.': '.$value;
        }

        return implode('; ', $rewritten);
    }

    private function rewriteAccentVar(string $value, ?string $accent): string
    {
        $result = preg_replace_callback(
            self::ACCENT_VAR_PATTERN,
            static function (array $matches) use ($accent): string {
                if ($accent !== null) {
                    return $accent;
                }
                $fallback = isset($matches[1]) ? trim($matches[1]) : '';
                if ($fallback === '') {
                    throw new InvalidSvgException(
                        'var(--icon-color-accent) has no fallback colour and no accent was configured.',
                    );
                }
                return $fallback;
            },
            $value,
        );

        return $result ?? $value;
    }

    private function resolveAspectRatio(\DOMElement $root): float
    {
        $viewBox = trim((string) $root->getAttribute('viewBox'));
        if ($viewBox !== '') {
            $parts = preg_split('/[\s,]+/', $viewBox) ?: [];
            $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
            if (count($parts) !== 4) {
                throw new InvalidSvgException('viewBox must contain 4 numeric components.');
            }
            // Validate all four components as strict floats so that malformed
            // viewBox tokens (e.g. "abc def 16 16") never reach the rasterizer.
            $minX = $this->parseStrictFloat($parts[0]);
            $minY = $this->parseStrictFloat($parts[1]);
            $width = $this->parseStrictFloat($parts[2]);
            $height = $this->parseStrictFloat($parts[3]);

            if ($minX === null || $minY === null) {
                throw new InvalidSvgException('viewBox min-x/min-y must be finite numbers.');
            }
            if ($width === null || $height === null || $width <= 0.0 || $height <= 0.0) {
                throw new InvalidSvgException('viewBox width and height must be positive finite numbers.');
            }

            return $width / $height;
        }

        $width = $this->parseStrictFloat(trim((string) $root->getAttribute('width')));
        $height = $this->parseStrictFloat(trim((string) $root->getAttribute('height')));
        if ($width !== null && $height !== null && $width > 0.0 && $height > 0.0) {
            return $width / $height;
        }

        throw new InvalidSvgException('SVG must declare either viewBox or numeric width/height.');
    }

    /**
     * Strict float parser: rejects malformed tokens like "16px", "1e309" (overflow), "" and
     * anything that filter_var(FILTER_VALIDATE_FLOAT) would not accept whole-cloth.
     */
    private function parseStrictFloat(string $token): ?float
    {
        if ($token === '') {
            return null;
        }
        $value = filter_var($token, \FILTER_VALIDATE_FLOAT);
        if ($value === false || !is_finite($value)) {
            return null;
        }

        return $value;
    }

    private function firstLibxmlError(): ?string
    {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        if ($errors === []) {
            return null;
        }

        return trim($errors[0]->message);
    }
}
