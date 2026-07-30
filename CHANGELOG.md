# Changelog

All notable changes to this project will be documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Stipple::toIcon(): RenderedIcon` for laying icons out beside other output — the rows unjoined,
  plus `widthCells`, `heightCells`, `blankCell`, `row()` and `blankRow()`. `row()` pads out-of-range
  rows with the sampler's blank cell so mixed heights and aspect ratios stay column-aligned.
  `toString()` is now `toIcon()` joined; its output is unchanged.

- `currentColor` and `var(--icon-color-accent, …)` are now substituted inside `<style>` element
  CSS, not just inside `fill`/`stroke`/`style` attributes. Icons styled through a stylesheet
  previously rendered as blank rows with no error.
- Root `width`/`height` may carry an absolute CSS unit (`px`, `pt`, `pc`, `in`, `cm`, `mm`, `q`),
  normalised to px. `width="16px"` is common in the wild and was previously refused outright.

### Changed

- **`SamplerInterface` gained `blankCell(): string`.** Custom samplers must implement it, so that
  padding uses the sampler's blank cell (`U+2800` for Braille) rather than a space.
- **`Stipple::make()` accepts local filesystem paths only.** Stream wrappers (`http://`,
  `data://`, `php://`, …) now throw `InvalidArgumentException` instead of being read, so a
  caller-supplied path cannot trigger an outbound request under the default `allow_url_fopen`.
  Pass remote SVG to `makeFromString()` yourself.
- **`SamplerInterface::sample()` validates `$foregroundHex`** and throws
  `InvalidArgumentException` for anything that is not a 6-digit `#rrggbb`. It previously
  emitted PHP deprecations from `hexdec()` and produced a wrong colour.
- **Samplers require a true-colour image** and throw `InvalidArgumentException` for a palette
  image, which the packed-pixel read cannot interpret. `RasterizerInterface` already documented
  true-colour output; custom rasterizers returning a palette image must now convert.

### Fixed

- Blank input (empty file, empty string, unreadable directory) raises `InvalidSvgException`
  instead of letting `DOMDocument::loadXML()`'s raw `ValueError` escape the `StippleException`
  contract.

### Performance

- Pixels are unpacked with bit shifts rather than an `imagecolorsforindex()` array per pixel:
  the sampling loop is ~3.4x faster, and a `height(256)` render drops from ~208 ms to ~90 ms.

### Documentation

- Added Packagist, PHP version, CI, PHPStan and license badges.
- Documented that gradient-filled icons (`fill="url(#…)"`) render blank, a `meyfa/php-svg`
  limitation stipple cannot detect.

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

[Unreleased]: https://github.com/wazum/stipple/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/wazum/stipple/releases/tag/v0.1.0
