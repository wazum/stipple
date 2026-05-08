# stipple

Render small SVG icons as monochrome ANSI in the terminal — pure PHP, zero system dependencies.

Drop it into any PHP CLI tool that wants real icons next to its menu items. The output is a plain string ending in `\n` per row — works equally well with `echo`, Symfony Console, Laravel/Prompts, or whatever else writes to a TTY. Two pluggable samplers ship: Braille (default) for highest density and half-block as a more universal fallback.

## Preview

The [`actions-brand-github`](https://typo3.github.io/TYPO3.Icons/icons/actions/actions-brand-github.html) icon from [TYPO3.Icons](https://typo3.github.io/TYPO3.Icons/) (MIT-licensed) — source SVG and Braille rendering at heights 4, 6, 8:

<table>
<tr>
<td align="center" valign="middle">

<img src="examples/icons/actions-brand-github.svg" width="96" alt="actions-brand-github source SVG" />

<sub>Source SVG (16×16)</sub>

</td>
<td valign="top">

```
⠀⢀⣠⣤⣤⣤⣀⠀
⢠⣿⡏⠋⠙⠋⣿⣧
⠸⣿⣇⠀⠀⢀⣿⣿
⠀⠙⠿⡅⠀⣿⠟⠁
```

<sub>Braille, <code>height(4)</code></sub>

</td>
<td valign="top">

```
⠀⠀⠀⢀⣀⣀⣀⣀⣀⠀⠀⠀
⠀⢀⣴⡿⢿⣿⣿⣿⠿⣷⣄⠀
⠀⣿⣿⠇⠀⠀⠀⠀⠀⢿⣿⡇
⠀⣿⣿⡄⠀⠀⠀⠀⠀⣼⣿⡏
⠀⠹⣿⡿⠶⠀⠀⢰⣾⣿⡿⠁
⠀⠀⠈⠻⠶⠀⠀⠸⠟⠋⠀⠀
```

<sub>Braille, <code>height(6)</code></sub>

</td>
<td valign="top">

```
⠀⠀⠀⠀⠀⠀⣀⣀⣀⣀⡀⠀⠀⠀⠀⠀
⠀⠀⠀⣤⣶⣿⣿⣿⣿⣿⣿⣷⣦⡄⠀⠀
⠀⢀⣾⣿⡏⠉⠛⠛⠛⠛⠋⠉⣿⣿⣆⠀
⠀⣾⣿⣿⠋⠀⠀⠀⠀⠀⠀⠀⢻⣿⣿⡆
⠀⣿⣿⣿⡀⠀⠀⠀⠀⠀⠀⠀⣸⣿⣿⡇
⠀⠹⣿⣿⣷⣤⣀⠀⠀⢀⣀⣴⣿⣿⡿⠁
⠀⠀⠙⢿⣮⣙⣋⠀⠀⢸⣿⣿⣿⠟⠁⠀
⠀⠀⠀⠀⠉⠛⠟⠀⠀⠘⠟⠋⠁⠀⠀⠀
```

<sub>Braille, <code>height(8)</code></sub>

</td>
</tr>
</table>

Same icon at `height(8)` rendered with the half-block sampler — coarser, but works in any terminal/font:

```
   ▄▄███████▄▄
  ███▀█▀▀██▀███
 ████       ████
 ████       ████
 ▀███▄     ▄████
  ▀███▀   ████▀
    ▀▀█   ██▀
```

## Install

```bash
composer require wazum/stipple
```

Requires PHP 8.2+ with `ext-gd`, `ext-mbstring`, `ext-dom`, `ext-simplexml`. No system binaries needed — rasterization is handled in pure PHP via [`meyfa/php-svg`](https://github.com/meyfa/php-svg).

## Usage

```php
use Wazum\Stipple\Stipple;

// One-shot, defaults: height 8 cells, terminal default fg, Braille sampler.
echo Stipple::render('/path/to/icon.svg');
echo Stipple::renderFromString('<svg ...>');

// Fluent
echo Stipple::make('/path/to/icon.svg')
    ->height(4)                // cells; valid 1..256
    ->color('#00ffff')         // optional, 6-digit hex; null → terminal default fg
    ->accent('#ff8700')        // overrides the fallback in any var(--icon-color-accent, …) call in the SVG
    ->threshold(0.5)           // alpha-weighted luminance cutoff in [0.0, 1.0]
    ->maxRasterDimension(2048) // safety cap on the intermediate raster (default 4096 px)
    ->toString();

// __toString delegates to toString(), so casting works too:
echo (string) Stipple::make('/path/to/icon.svg')->height(4);
```

The output is a plain string ending in `\n` per row, safe to `echo` or pass to Laravel/Prompts' `note()`/`info()`.

## Samplers

```php
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;

Stipple::make($path)->sampler(new BrailleSampler())->toString();   // default — 2x4 px/cell
Stipple::make($path)->sampler(new HalfBlockSampler())->toString(); // 1x2 px/cell, more universal
```

| Sampler        | Density        | Glyphs               | Best for                                      |
| -------------- | -------------- | -------------------- | --------------------------------------------- |
| `BrailleSampler` (default) | 2×4 px/cell | `U+2800`–`U+28FF` | Highest fidelity for line-art icons. Needs a Braille-capable monospace font (JetBrains Mono, Cascadia, DejaVu, Iosevka all work). |
| `HalfBlockSampler`         | 1×2 px/cell | `▀ ▄ █`           | Universal — works in any terminal/font including legacy `cmd.exe`. |

For a 16×16 SVG at `height(4)` the Braille sampler maps 1:1 with the source pixel grid; at `height(8)` it super-samples 2×.

> **Alignment note.** Blank Braille cells emit `U+2800` so adjacent icons stay column-aligned in fonts that render `U+2800` narrower than other Braille glyphs. The half-block sampler emits raw spaces for blank rows — usually fine, but two icons rendered side-by-side may drift by a column on terminals/fonts that treat space and `█` as different widths.

## Demo

Two scripts are bundled in this repository. They run only from a checkout — the `bin/`
and `examples/` directories are excluded from the Composer dist tarball, so they're not
shipped to library consumers.

```bash
composer install
php bin/demo.php      # height 4/6/8 comparison across two samplers
php bin/icon-row.php  # ten icons rendered side-by-side in a single 4-line row
```

## Pluggable rasterizer

The default rasterizer wraps `meyfa/php-svg`. You can swap in a different backend later by implementing `RasterizerInterface`:

```php
use Wazum\Stipple\Rasterizer\RasterizerInterface;

final class RsvgConvertRasterizer implements RasterizerInterface
{
    public function rasterize(string $svg, int $widthPx, int $heightPx): \GdImage { /* … */ }
}

Stipple::make($path)->rasterizer(new RsvgConvertRasterizer())->toString();
```

## Security

The preprocessor hardens SVG input before rasterization:

- DOCTYPE / ENTITY declarations are rejected pre-parse (XXE attack surface).
- `<script>`, `<foreignObject>`, and **all `<image>`** elements are rejected after parse — embedded raster is out of scope, and allowing `<image href="file://..."/>` would let the rasterizer dependency `file_get_contents()` arbitrary local files.
- libxml is invoked with `LIBXML_NONET` (no network).
- `currentColor` is substituted with a configurable foreground hex; `var(--icon-color-accent, ...)` is resolved DOM-side so the rasterizer never has to deal with CSS custom properties.

## Supported SVG features

The preprocessor handles common patterns found in icon SVGs from any source:

- `fill="currentColor"` and `stroke="currentColor"` — substituted with `#ffffff` so the rasterizer always renders at full luminance, regardless of the terminal foreground colour.
- `style="fill: currentColor; …"` — same substitution inside inline CSS, with other declarations preserved.
- `var(--icon-color-accent, <fallback-hex>)` — resolved DOM-side using either the configured `accent()` value or the embedded fallback hex (the rasterizer doesn't resolve CSS custom properties on its own).
- `viewBox` (space- or comma-separated) and root `width`/`height` numeric attributes for aspect-ratio resolution.

Anything not in the above list is passed through to the rasterizer untouched.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse src --level=8
```

## License

MIT — see [LICENSE](LICENSE). Bundled demo icons (`examples/icons/`) are MIT-licensed by
the [TYPO3.Icons](https://github.com/TYPO3/TYPO3.Icons) project — see
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
