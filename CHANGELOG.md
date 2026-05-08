# Changelog

All notable changes to this project will be documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.1.0]: https://github.com/wazum/stipple/releases/tag/v0.1.0
