# Changelog

All notable changes to this project will be documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-30

First stable release. The public API is now covered by semantic versioning; see
[Public API](README.md#public-api) for what that includes.

### Added

- `InkMode` enum plus `Stipple::inkMode()`, and `SamplerOptions::withForegroundHex()`,
  `withThreshold()`, `withInkMode()`. The withers carry every other setting across, so an option
  added in 1.x can never be silently dropped by `color()` or `threshold()`.
- Golden tests recording how all 14 bundled example icons render, so pipeline changes surface as a
  reviewable picture. Nothing previously rendered a real icon.
- `Stipple::toIcon(): RenderedIcon` for laying icons out beside other output — the rows unjoined,
  plus `widthCells`, `heightCells`, `blankCell`, `row()` and `blankRow()`. `row()` pads out-of-range
  rows with the sampler's blank cell so mixed heights and aspect ratios stay column-aligned.
  `toString()` is now `toIcon()` joined; its output is unchanged.
- `currentColor` and `var(--icon-color-accent, …)` are now substituted inside `<style>` element
  CSS, not just inside `fill`/`stroke`/`style` attributes. Icons styled through a stylesheet
  previously rendered as blank rows with no error.
- `url(#…)` paint-server references in `fill`/`stroke` are flattened to solid foreground, honouring
  an SVG 2 fallback paint when present. Gradient- and pattern-filled icons previously rendered as
  blank rows with no error, because `meyfa/php-svg` does not resolve paint servers.
- Root `width`/`height` may carry an absolute CSS unit (`px`, `pt`, `pc`, `in`, `cm`, `mm`, `q`),
  normalised to px. `width="16px"` is common in the wild and was previously refused outright.

### Changed

- **Ink is now decided by coverage, not brightness.** A pixel is ink when it is opaque enough,
  whatever colour it is. Previously the rule was brightness x opacity, so a black-filled or
  unfilled icon — the most common convention in Material Icons, Material Symbols, Font Awesome
  and Heroicons — rendered as entirely blank rows with no error, and no `threshold()` value could
  recover it. `InkMode::Luminance` keeps the old rule for light-on-dark artwork; select it with
  `Stipple::inkMode()`. Rendering of the bundled example icons is unchanged (golden-tested).
- **`AbstractSampler::pixelOn()` takes `SamplerOptions`** instead of a bare `float $threshold`,
  so the ink rule and any later option reach it. Affects samplers extending `AbstractSampler`.
- **`SamplerInterface::sample()` now takes a `SamplerOptions` object** instead of positional
  `?string $foregroundHex, float $threshold`. Options can then be added without breaking the
  interface again. `SamplerOptions` validates the colour and threshold on construction, so an
  invalid combination is unconstructable and samplers no longer re-check. `Stipple::color()` and
  `threshold()` are unchanged; `Stipple::samplerOptions()` sets both at once.
- **`SamplerInterface` gained `blankCell(): string`.** Custom samplers must implement it, so that
  padding uses the sampler's blank cell (`U+2800` for Braille) rather than a space.
- **`Stipple::make()` accepts local filesystem paths only.** Stream wrappers (`http://`,
  `data://`, `php://`, …) now throw `InvalidArgumentException` instead of being read, so a
  caller-supplied path cannot trigger an outbound request under the default `allow_url_fopen`.
  Pass remote SVG to `makeFromString()` yourself.
- **Samplers require a true-colour image** and throw `InvalidArgumentException` for a palette
  image, which the packed-pixel read cannot interpret. `RasterizerInterface` already documented
  true-colour output; custom rasterizers returning a palette image must now convert.
- `SvgPreprocessor` and `PreprocessedSvg` are marked `@internal` and excluded from the
  backward compatibility promise.
- `friendsofphp/php-cs-fixer` now requires `^3.75`; the previous `^3.50` floor could not parse
  this project's own fixer configuration.

### Security

- The DOCTYPE check now also runs after parsing. The raw-byte pre-scan cannot see a declaration in
  an encoding libxml auto-detects, so UTF-16 input carried one straight through. No XXE or SSRF was
  reachable through the gap — `LIBXML_NONET` and PHP's defaults held — but the pre-scan was
  presented as the primary defence and was not one.
- Path coordinates far outside the viewBox are refused. Curve and arc approximation cost scales with
  coordinate magnitude, so a 138-byte document could exhaust memory with an uncatchable fatal.
- Input files are capped at 4 MiB, checked before reading rather than after.
- Control characters are stripped from paths interpolated into exception messages, so a filename
  cannot smuggle terminal escape sequences into output printed to a TTY.
- The host's libxml error queue is no longer cleared unless this library enabled collection itself,
  and a parse failure reports this library's own error rather than whatever the host had queued.
- `ext-libxml` is declared, and CI declares `permissions: contents: read`.

### Fixed

- `<use>` / `<symbol>` references are inlined, and `<switch>` is replaced by its first viable child.
  The rasterizer treats both as no-ops, so sprite-sheet icon sets and Illustrator's SVG 1.1 export
  rendered as nothing at all. External `<use>` references are refused rather than fetched;
  dangling and circular ones raise `InvalidSvgException` instead of rendering blank.
- Path data is normalised before rasterization. The rasterizer tokenises it with a bare number
  regex, so separator-less arc flags (`a8 8 0 11-16 0`, what every SVG minifier emits) and uppercase
  exponents were misread — and on an argument-count mismatch it silently discarded the rest of the
  path. Minified icons rendered as fragments.
- `clip-path` and `mask` no longer render incorrectly. Both were ignored, so clipped content was
  drawn in full and a load-bearing clip turned its icon into a filled block. A clip covering the
  whole viewBox is dropped (what Figma and Illustrator wrap exports in); anything that would change
  the picture, and any `mask`, is refused.
- `<text>` is refused rather than rendering as nothing, since no font is registered.
- Rasterizer diagnostics no longer reach the caller. `meyfa/php-svg` emits `E_DEPRECATED` for the
  fractional coordinates ordinary icons produce at most heights, and the error handler was
  restored before rasterization ran — so with `display_errors` on, PHP diagnostic text was printed
  into the middle of the rendered frame, and under a host error handler the same icon failed
  outright. Deprecations and notices from the dependency are now dropped; warnings still surface
  as `RasterizationFailedException`.
- Chained `url(#…)` paint fallbacks no longer exhaust memory. Resolution recursed on the remaining
  string, which is quadratic: a 48 KB document could exhaust a 128 MB limit with an uncatchable
  fatal. It is now an offset walk.
- Blank input (empty file, empty string, unreadable directory) raises `InvalidSvgException`
  instead of letting `DOMDocument::loadXML()`'s raw `ValueError` escape the `StippleException`
  contract.
- `threshold(NAN)` is rejected. `NAN` compares false against both range bounds, so it was
  accepted and then turned every pixel off.
- Aspect ratios that overflow or collapse are rejected. Two individually finite dimensions can
  still divide to `INF` or `0.0` (`viewBox="0 0 1e308 1e-308"`), which previously produced a
  one-cell blank icon or a confusing error from the rasterizer.

### Performance

- Pixels are unpacked with bit shifts rather than an `imagecolorsforindex()` array per pixel:
  the sampling loop is ~3.4x faster, and a `height(256)` render drops from ~208 ms to ~90 ms.

### Documentation

- Added Packagist, PHP version, CI, PHPStan and license badges.
- Documented the supported public API surface.

### Internal

- CI covers PHP 8.2 through 8.5, adds a `--prefer-lowest` job so the declared dependency floor is
  actually exercised, and runs php-cs-fixer on the minimum PHP version.
- PHPStan raised from level 8 to level 10 across `src` and `tests`.

## [0.1.0] - 2026-05-08

### Added

- Initial release.
- `Stipple` facade with fluent immutable builder (`make`, `makeFromString`, `render`,
  `renderFromString`, `height`, `color`, `accent`, `threshold`, `maxRasterDimension`,
  `rasterizer`, `sampler`).
- `BrailleSampler` (default, 2×4 px/cell) and `HalfBlockSampler` (1×2 px/cell) implementing
  a shared `SamplerInterface`.
- `PhpSvgRasterizer` wrapping `meyfa/php-svg`; `RasterizerInterface` for swapping backends.
- `SvgPreprocessor` with hardened input handling: DOCTYPE/ENTITY pre-rejection,
  `LIBXML_NONET`, rejection of `<script>`, `<foreignObject>`, and all `<image>` elements,
  `currentColor` and `var(--icon-color-accent, …)` substitution.

[1.0.0]: https://github.com/wazum/stipple/compare/v0.1.0...v1.0.0
[0.1.0]: https://github.com/wazum/stipple/releases/tag/v0.1.0
