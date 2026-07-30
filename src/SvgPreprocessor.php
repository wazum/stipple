<?php

declare(strict_types=1);

namespace Wazum\Stipple;

use Wazum\Stipple\Exception\InvalidSvgException;

/**
 * @internal not covered by the backward compatibility promise; use Stipple instead
 */
final class SvgPreprocessor
{
    private const CURRENT_COLOR_REPLACEMENT = '#ffffff';
    private const ACCENT_VAR_PATTERN = '/var\(\s*--icon-color-accent\s*(?:,\s*([^)]+))?\s*\)/i';
    private const PAINT_SERVER_PATTERN = '/\Gurl\([^)]*\)\s*/i';

    private const MAX_USE_DEPTH = 8;

    /**
     * Arguments per path command, per the SVG path grammar.
     *
     * @var array<string, int>
     */
    private const PATH_ARGUMENT_COUNTS = [
        'M' => 2, 'L' => 2, 'T' => 2,
        'H' => 1, 'V' => 1,
        'C' => 6, 'S' => 4, 'Q' => 4,
        'A' => 7,
        'Z' => 0,
    ];

    /**
     * Absolute CSS lengths in px (1in = 96px). Normalising to a common base keeps a
     * mismatched pair like width="1in" height="72pt" correct.
     *
     * @var array<string, float>
     */
    private const ABSOLUTE_UNIT_FACTORS = [
        'px' => 1.0,
        'pt' => 96.0 / 72.0,
        'pc' => 16.0,
        'in' => 96.0,
        'cm' => 96.0 / 2.54,
        'mm' => 96.0 / 25.4,
        'q' => 96.0 / 101.6,
    ];

    public function clean(string $svg, ?string $accent): PreprocessedSvg
    {
        $this->rejectBlankInput($svg);
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
            // Before the colour and path passes, so inlined content is processed like any other.
            $this->unwrapSwitches($document);
            $this->resolveUseReferences($document);
            $this->resolveClipsAndMasks($document);
            $this->applyColorSubstitutions($document, $accent);
            $this->normalisePathData($document);

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

    /**
     * loadXML() throws a raw ValueError on empty input, escaping the StippleException contract.
     */
    private function rejectBlankInput(string $svg): void
    {
        if (trim($svg) === '') {
            throw new InvalidSvgException('SVG input is empty.');
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

        // No font is registered with the rasterizer, so text would silently render as nothing.
        if ($document->getElementsByTagName('text')->length > 0) {
            throw new InvalidSvgException(
                'SVG contains a <text> element, which cannot be rendered; convert the text to paths.',
            );
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
                $rewritten = $this->resolvePaint($this->rewriteAccentVar($original, $accent));
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

        foreach ($document->getElementsByTagName('style') as $styleElement) {
            $original = $styleElement->textContent;
            $rewritten = $this->rewriteStyleSheet($original, $accent);
            if ($rewritten !== $original) {
                $styleElement->textContent = $rewritten;
            }
        }
    }

    /**
     * Replaces each <switch> with its first viable child. The rasterizer renders <switch> as
     * nothing, and Illustrator's SVG 1.1 export wraps content in one.
     */
    private function unwrapSwitches(\DOMDocument $document): void
    {
        // Materialised because replacing nodes mutates the live list.
        $switches = iterator_to_array($document->getElementsByTagName('switch'));

        foreach ($switches as $switch) {
            $chosen = null;
            foreach ($switch->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                if ($child->getAttribute('requiredExtensions') !== ''
                    || $child->getAttribute('requiredFeatures') !== '') {
                    continue;
                }
                $chosen = $child;
                break;
            }

            $parent = $switch->parentNode;
            if ($parent === null) {
                continue;
            }
            if ($chosen === null) {
                $parent->removeChild($switch);
                continue;
            }
            $parent->replaceChild($chosen, $switch);
        }
    }

    /**
     * Inlines each <use> reference. The rasterizer renders <use> as nothing, which is how
     * sprite-sheet icon sets are distributed, so they produced blank output.
     */
    private function resolveUseReferences(\DOMDocument $document): void
    {
        for ($pass = 0; $pass < self::MAX_USE_DEPTH; $pass++) {
            $uses = iterator_to_array($document->getElementsByTagName('use'));
            if ($uses === []) {
                return;
            }

            foreach ($uses as $use) {
                $this->inlineUse($document, $use);
            }
        }

        if ($document->getElementsByTagName('use')->length > 0) {
            throw new InvalidSvgException(
                'SVG nests <use> references too deeply, or they reference each other in a cycle.',
            );
        }
    }

    private function inlineUse(\DOMDocument $document, \DOMElement $use): void
    {
        $href = $use->getAttribute('href');
        if ($href === '') {
            $href = $use->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        }
        if (!str_starts_with($href, '#') || $href === '#') {
            throw new InvalidSvgException(sprintf(
                'SVG <use> must reference a local id; got "%s". External references are not fetched.',
                $href,
            ));
        }

        $referenced = $document->getElementById(substr($href, 1))
            ?? $this->findById($document, substr($href, 1));
        if ($referenced === null) {
            throw new InvalidSvgException(sprintf('SVG <use> references "%s", which does not exist.', $href));
        }
        if ($referenced === $use || $referenced->contains($use)) {
            throw new InvalidSvgException('SVG <use> references an element that contains it.');
        }

        // A <g> carries the use element's own presentation attributes plus its offset, which is
        // how the spec says the referenced content inherits them.
        $group = $document->createElementNS('http://www.w3.org/2000/svg', 'g');
        foreach ($use->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            if (in_array($attribute->localName, ['href', 'x', 'y', 'width', 'height', 'id'], true)) {
                continue;
            }
            $group->setAttribute($attribute->nodeName, $attribute->value);
        }

        $offsetX = $use->getAttribute('x');
        $offsetY = $use->getAttribute('y');
        if ($offsetX !== '' || $offsetY !== '') {
            $group->setAttribute('transform', sprintf(
                'translate(%s,%s)',
                $offsetX === '' ? '0' : $offsetX,
                $offsetY === '' ? '0' : $offsetY,
            ));
        }

        $clone = $referenced->cloneNode(true);
        if (!$clone instanceof \DOMElement) {
            return;
        }
        $clone->removeAttribute('id');

        // <symbol> is never drawn where it is defined, so its children become the group's.
        if ($clone->localName === 'symbol' || $clone->localName === 'svg') {
            foreach (iterator_to_array($clone->childNodes) as $child) {
                $group->appendChild($child);
            }
        } else {
            $group->appendChild($clone);
        }

        $use->parentNode?->replaceChild($group, $use);
    }

    private function findById(\DOMDocument $document, string $id): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }

    /**
     * The rasterizer ignores clip-path and mask, drawing clipped content in full. A clip that
     * covers the whole viewBox changes nothing and is dropped — that is what Figma and
     * Illustrator wrap their exports in. Anything that would actually alter the picture is
     * refused, because drawing it unclipped turns the icon into a filled block instead.
     */
    private function resolveClipsAndMasks(\DOMDocument $document): void
    {
        $viewBox = $this->rootViewBoxRect($document);

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            $mask = trim($element->getAttribute('mask'));
            if ($mask !== '' && strcasecmp($mask, 'none') !== 0) {
                throw new InvalidSvgException(
                    'SVG uses mask, which cannot be rendered; flatten the artwork first.',
                );
            }

            $clip = trim($element->getAttribute('clip-path'));
            if ($clip === '' || strcasecmp($clip, 'none') === 0) {
                continue;
            }

            if (preg_match('/^url\(\s*#([^)\s]+)\s*\)$/i', $clip, $matches) !== 1) {
                throw new InvalidSvgException(sprintf('SVG has an unsupported clip-path value "%s".', $clip));
            }

            $clipPath = $this->findById($document, $matches[1]);
            if ($clipPath === null || $clipPath->localName !== 'clipPath') {
                throw new InvalidSvgException(sprintf(
                    'SVG clip-path references "#%s", which is not a <clipPath>.',
                    $matches[1],
                ));
            }

            if (!$this->clipCoversRect($clipPath, $viewBox)) {
                throw new InvalidSvgException(
                    'SVG uses a clip-path that actually clips, which cannot be rendered; '
                    .'flatten the artwork first.',
                );
            }

            $element->removeAttribute('clip-path');
        }
    }

    /**
     * @param array{float, float, float, float}|null $viewBox
     */
    private function clipCoversRect(\DOMElement $clipPath, ?array $viewBox): bool
    {
        if ($viewBox === null || $clipPath->hasAttribute('transform')) {
            return false;
        }
        if ($clipPath->hasAttribute('clipPathUnits')
            && strcasecmp($clipPath->getAttribute('clipPathUnits'), 'userSpaceOnUse') !== 0) {
            return false;
        }

        $children = [];
        foreach ($clipPath->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }
        if (count($children) !== 1) {
            return false;
        }

        $rect = $children[0];
        if ($rect->localName !== 'rect'
            || $rect->hasAttribute('transform')
            || $rect->hasAttribute('rx')
            || $rect->hasAttribute('ry')) {
            return false;
        }

        $x = $this->parseStrictFloat(trim($rect->getAttribute('x')) ?: '0');
        $y = $this->parseStrictFloat(trim($rect->getAttribute('y')) ?: '0');
        $width = $this->parseStrictFloat(trim($rect->getAttribute('width')));
        $height = $this->parseStrictFloat(trim($rect->getAttribute('height')));
        if ($x === null || $y === null || $width === null || $height === null) {
            return false;
        }

        [$viewX, $viewY, $viewWidth, $viewHeight] = $viewBox;

        return $x <= $viewX
            && $y <= $viewY
            && $x + $width >= $viewX + $viewWidth
            && $y + $height >= $viewY + $viewHeight;
    }

    /**
     * @return array{float, float, float, float}|null
     */
    private function rootViewBoxRect(\DOMDocument $document): ?array
    {
        $root = $document->documentElement;
        if ($root === null) {
            return null;
        }

        $viewBox = trim($root->getAttribute('viewBox'));
        if ($viewBox !== '') {
            $parts = array_values(array_filter(
                preg_split('/[\s,]+/', $viewBox) ?: [],
                static fn (string $part): bool => $part !== '',
            ));
            if (count($parts) !== 4) {
                return null;
            }
            $numbers = [];
            foreach ($parts as $part) {
                $number = $this->parseStrictFloat($part);
                if ($number === null) {
                    return null;
                }
                $numbers[] = $number;
            }

            return [$numbers[0], $numbers[1], $numbers[2], $numbers[3]];
        }

        $width = $this->parseRootLength(trim($root->getAttribute('width')));
        $height = $this->parseRootLength(trim($root->getAttribute('height')));

        return $width === null || $height === null ? null : [0.0, 0.0, $width, $height];
    }

    /**
     * Rewrites every path's data with explicit separators. The rasterizer tokenises path data
     * with a bare number regex, so it misreads the separator-less arc flags every SVG minifier
     * emits — and on an argument-count mismatch it silently drops the rest of the path.
     */
    private function normalisePathData(\DOMDocument $document): void
    {
        foreach ($document->getElementsByTagName('path') as $path) {
            if (!$path->hasAttribute('d')) {
                continue;
            }
            $original = $path->getAttribute('d');
            $normalised = $this->normalisePath($original);
            if ($normalised !== $original) {
                $path->setAttribute('d', $normalised);
            }
        }
    }

    /**
     * Returns $d unchanged if anything cannot be read with certainty, so an unfamiliar
     * construct is passed through rather than corrupted.
     */
    private function normalisePath(string $d): string
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($d);

        while (true) {
            $this->skipPathSeparators($d, $offset);
            if ($offset >= $length) {
                break;
            }

            $command = $d[$offset];
            $argumentCount = self::PATH_ARGUMENT_COUNTS[strtoupper($command)] ?? null;
            if ($argumentCount === null) {
                return $d;
            }
            $offset++;
            $tokens[] = $command;

            if ($argumentCount === 0) {
                continue;
            }

            $isArc = 'A' === strtoupper($command);
            $sets = 0;
            while (true) {
                $resume = $offset;
                $arguments = $this->readArgumentSet($d, $offset, $argumentCount, $isArc);
                if ($arguments === null) {
                    $offset = $resume;
                    break;
                }
                $tokens[] = implode(' ', $arguments);
                $sets++;
            }

            if ($sets === 0) {
                return $d;
            }
        }

        return implode(' ', $tokens);
    }

    /**
     * @return list<string>|null
     */
    private function readArgumentSet(string $d, int &$offset, int $argumentCount, bool $isArc): ?array
    {
        $arguments = [];
        for ($index = 0; $index < $argumentCount; $index++) {
            $this->skipPathSeparators($d, $offset);

            // An arc's large-arc and sweep flags are single digits the spec allows to be written
            // with no separator, which is exactly what the rasterizer's tokeniser cannot see.
            $argument = $isArc && ($index === 3 || $index === 4)
                ? $this->readPathFlag($d, $offset)
                : $this->readPathNumber($d, $offset);

            if ($argument === null) {
                return null;
            }
            $arguments[] = $argument;
        }

        return $arguments;
    }

    private function readPathFlag(string $d, int &$offset): ?string
    {
        if ($offset < strlen($d) && ('0' === $d[$offset] || '1' === $d[$offset])) {
            return $d[$offset++];
        }

        return null;
    }

    private function readPathNumber(string $d, int &$offset): ?string
    {
        $start = $offset;
        $length = strlen($d);

        if ($offset < $length && ('+' === $d[$offset] || '-' === $d[$offset])) {
            $offset++;
        }

        $digits = 0;
        while ($offset < $length && ctype_digit($d[$offset])) {
            $offset++;
            $digits++;
        }
        if ($offset < $length && '.' === $d[$offset]) {
            $offset++;
            while ($offset < $length && ctype_digit($d[$offset])) {
                $offset++;
                $digits++;
            }
        }
        if ($digits === 0) {
            $offset = $start;

            return null;
        }

        if ($offset < $length && ('e' === $d[$offset] || 'E' === $d[$offset])) {
            $beforeExponent = $offset;
            $offset++;
            if ($offset < $length && ('+' === $d[$offset] || '-' === $d[$offset])) {
                $offset++;
            }
            $exponentDigits = 0;
            while ($offset < $length && ctype_digit($d[$offset])) {
                $offset++;
                $exponentDigits++;
            }
            if ($exponentDigits === 0) {
                $offset = $beforeExponent;
            }
        }

        // Lowercased so an uppercase exponent, which the rasterizer's tokeniser also misreads,
        // becomes readable.
        return strtolower(substr($d, $start, $offset - $start));
    }

    private function skipPathSeparators(string $d, int &$offset): void
    {
        $length = strlen($d);
        while ($offset < $length && (',' === $d[$offset] || ' ' === $d[$offset]
            || "\n" === $d[$offset] || "\r" === $d[$offset] || "\t" === $d[$offset])) {
            $offset++;
        }
    }

    /**
     * Selectors and nested at-rules pass through; only declaration blocks are rewritten.
     */
    private function rewriteStyleSheet(string $css, ?string $accent): string
    {
        $withAccent = $this->rewriteAccentVar($css, $accent);

        $result = preg_replace_callback(
            '/\{([^{}]*)\}/',
            fn (array $matches): string => '{'.$this->rewriteCurrentColorInStyle($matches[1]).'}',
            $withAccent,
        );

        return $result ?? $withAccent;
    }

    /**
     * Resolves a fill/stroke value the rasterizer cannot handle on its own: `currentColor`, and
     * `url(#…)` paint servers, which meyfa/php-svg renders as nothing at all. An SVG 2 fallback
     * paint after the reference wins; otherwise the shape becomes solid foreground, since a
     * gradient carries no information in monochrome anyway.
     */
    private function resolvePaint(string $value): string
    {
        $trimmed = trim($value);

        if (strcasecmp($trimmed, 'currentColor') === 0) {
            return self::CURRENT_COLOR_REPLACEMENT;
        }

        // Walked with an offset rather than recursively on the tail: chained references used to
        // copy the remainder per step, which is quadratic and exhausts memory on a small input.
        $offset = 0;
        $length = strlen($trimmed);
        while ($offset < $length
            && preg_match(self::PAINT_SERVER_PATTERN, $trimmed, $matches, 0, $offset) === 1
        ) {
            $offset += strlen($matches[0]);
        }

        if ($offset === 0) {
            return $value;
        }

        $fallback = trim(substr($trimmed, $offset));

        return $fallback === '' || strcasecmp($fallback, 'currentColor') === 0
            ? self::CURRENT_COLOR_REPLACEMENT
            : $fallback;
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

            if (in_array(strtolower($property), ['fill', 'stroke'], true)) {
                $rewritten[] = $property.': '.$this->resolvePaint($value);
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

            return $this->assertUsableRatio($width / $height);
        }

        $width = $this->parseRootLength(trim((string) $root->getAttribute('width')));
        $height = $this->parseRootLength(trim((string) $root->getAttribute('height')));
        if ($width !== null && $height !== null && $width > 0.0 && $height > 0.0) {
            return $this->assertUsableRatio($width / $height);
        }

        throw new InvalidSvgException('SVG must declare either viewBox or numeric width/height.');
    }

    /**
     * Two individually finite dimensions can still divide to INF or 0.0 at the extremes of
     * the float range, which downstream degrades to a one-cell blank icon.
     */
    private function assertUsableRatio(float $ratio): float
    {
        if (!is_finite($ratio) || $ratio <= 0.0) {
            throw new InvalidSvgException('SVG aspect ratio is not a usable positive number.');
        }

        return $ratio;
    }

    /**
     * Unlike viewBox, which the spec defines as bare numbers, width/height may carry an
     * absolute CSS unit. Relative units (%, em, ex) need a viewport we do not have.
     */
    private function parseRootLength(string $token): ?float
    {
        $factor = 1.0;
        foreach (self::ABSOLUTE_UNIT_FACTORS as $unit => $unitFactor) {
            if (strcasecmp(substr($token, -strlen($unit)), $unit) !== 0) {
                continue;
            }
            $token = trim(substr($token, 0, -strlen($unit)));
            $factor = $unitFactor;
            break;
        }

        $value = $this->parseStrictFloat($token);
        if ($value === null) {
            return null;
        }

        $pixels = $value * $factor;

        return is_finite($pixels) ? $pixels : null;
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
