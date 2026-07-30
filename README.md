# stipple

[![Packagist Version](https://img.shields.io/packagist/v/wazum/stipple?logo=packagist&logoColor=white)](https://packagist.org/packages/wazum/stipple)
[![PHP Version Support](https://img.shields.io/packagist/dependency-v/wazum/stipple/php?logo=php&logoColor=white)](https://packagist.org/packages/wazum/stipple)
[![CI](https://img.shields.io/github/actions/workflow/status/wazum/stipple/ci.yml?branch=main&logo=githubactions&logoColor=white&label=CI)](https://github.com/wazum/stipple/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen)](https://phpstan.org/)
[![License](https://img.shields.io/packagist/l/wazum/stipple)](LICENSE)

Render small SVG icons as monochrome ANSI in the terminal — pure PHP, zero system dependencies.

Use it in any PHP command line tool that wants real icons next to its menu items. The result is a
plain string, so `echo`, [Symfony TUI](https://github.com/symfony/tui),
[Symfony Console](https://github.com/symfony/console) and
[Laravel Prompts](https://github.com/laravel/prompts) all work. Two samplers are included: Braille
for more detail, half-block for wider terminal support.

## Preview

One icon from [TYPO3.Icons](https://typo3.github.io/TYPO3.Icons/) (MIT licence): the original SVG,
then Braille output at heights 4, 6 and 8.

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

The same icon with the half-block sampler. Less detail, but it works in any terminal and font.

```
   ▄▄███████▄▄
  ███▀█▀▀██▀███
 ████       ████
 ████       ████
 ▀███▄     ▄████
  ▀███▀   ████▀
    ▀▀█   ██▀
```

All example icons, drawn by stipple itself. [GALLERY.md](GALLERY.md) has more sizes and both
samplers.

<!-- gallery:start -->

```
⠀⢀⣠⣤⣤⣤⣀⠀  ⢀⣀⡀⠀⠀⠀⠀⠀  ⠀⠀⠀⠀⠀⠀⠀⠀  ⠀⠀⠀⠀⢀⡀⠀⠀  ⠀⠀⠀⣤⢤⡄⠀⠀  ⠀⠀⢠⡤⠤⣤⠀⠀  ⢠⣤⠤⠤⠤⠤⢤⣤
⢠⣿⡏⠋⠙⠋⣿⣧  ⠀⠘⣯⠉⠉⠉⢩⡏  ⠀⢀⡴⠒⢦⠤⣄⠀  ⢀⣤⡶⠀⣾⠱⢦⣄  ⠰⣟⣙⡥⠤⣝⣉⡷  ⠀⢹⡏⣭⢩⡍⣿⠁  ⠀⠙⢧⡀⠀⣠⠟⠁
⠸⣿⣇⠀⠀⢀⣿⣿  ⠀⠀⣿⣒⣒⣒⣛⠀  ⢰⣏⠁⠀⠀⠀⠛⣦  ⠘⠷⣦⢰⡏⢠⡴⠟  ⢠⣟⠸⣧⣠⡿⠘⣧  ⠀⢸⡇⣿⢸⡇⣿⠀  ⠀⠀⢸⡇⠀⣿⠀⠀
⠀⠙⠿⡅⠀⣿⠟⠁  ⠀⠀⠻⠟⠀⠻⠟⠀  ⠀⠉⠉⠉⠉⠉⠉⠁  ⠀⠀⠈⠾⠁⠈⠀⠀  ⠀⠛⠛⣶⣰⡞⠛⠃  ⠀⠸⣇⣛⣘⣃⡿⠀  ⠀⠀⠈⠛⢶⣿⠀⠀
brand-gi    cart     cloud      code      cog      delete    filter 

⠀⣀⣀⣀⢀⣀⣀⡀  ⠀⠀⠀⢀⣀⠀⠀⠀  ⠀⠀⢀⡤⠤⣄⠀⠀  ⠀⢀⣤⠤⣤⣄⠀⠀  ⠀⠀⠀⠀⣀⠀⠀⠀  ⠀⠀⢀⣤⣤⣄⠀⠀  ⣶⣶⣶⣶⣶⣶⣶⣶
⢸⡇⠀⠈⠟⠀⠀⣿  ⠀⠀⠀⣈⣉⠀⠀⠀  ⠀⣀⣿⣀⣀⣸⣇⡀  ⢰⡟⠁⠀⠀⢹⣷⠀  ⠀⣀⣀⣼⣿⣇⣀⡀  ⠀⠀⢸⣿⣿⡿⠀⠀  ⣿⣿⣿⣿⣿⣿⣿⣿
⠀⠻⣆⠀⠀⣀⡾⠃  ⠀⠀⠀⢹⣿⠀⠀⠀  ⠀⣿⠀⣶⣶⡆⢸⡇  ⠘⢷⡀⠀⠀⣸⣏⠀  ⠀⠈⢻⣿⣿⣿⡋⠀  ⠀⠀⢀⣿⣿⣇⠀⠀  ⣿⣿⣿⣿⣿⣿⣿⣿
⠀⠀⠈⠻⠶⠋⠀⠀  ⠀⠀⠰⠾⠿⠶⠀⠀  ⠀⣿⣀⣘⣛⣀⣸⡇  ⠀⠈⠙⠛⠋⠙⢿⣷  ⠀⠀⠞⠋⠉⠛⠷⠀  ⢰⣿⣿⣿⣿⣿⣿⣷  ⣿⣿⣿⣿⣿⣿⣿⣿
 heart      info      lock     search     star      user      page  
```

<!-- gallery:end -->

## Will my icons work?

| Icons from | Works? |
| ---------- | ------ |
| Material Icons and Symbols, Font Awesome, Heroicons, Bootstrap Icons, Feather, TYPO3.Icons | Yes, also black and unfilled icons |
| A CDN, in minified form | Yes |
| Sprite sheets (`<use>` and `<symbol>`) | Yes |
| Figma or Illustrator export | Yes |
| Files with gradients or patterns | Yes, drawn as one solid colour |
| Files that need real clipping or a `mask` | No. You get an error, not a wrong picture. |
| Files with `<text>` | No. Convert the text to paths first. |
| Colourful illustrations | Not really. The output has one colour. |

The rule everywhere: when stipple cannot draw something correctly, it throws an error. It never
returns an empty or wrong picture and calls it success.

## Install

```bash
composer require wazum/stipple
```

Needs PHP 8.2 or newer with `ext-gd`, `ext-mbstring`, `ext-dom`, `ext-libxml` and `ext-simplexml`.
No extra programs to install: the drawing is done in PHP by
[meyfa/php-svg](https://github.com/meyfa/php-svg).

## Try it on your own icon

Installing also gives you a `stipple` command. Use it to look at one of your icons before you write
any code:

```bash
vendor/bin/stipple path/to/icon.svg
vendor/bin/stipple path/to/icon.svg --height=4 --sampler=half-block
vendor/bin/stipple path/to/logo.svg --color=#00ffff --ink=luminance
vendor/bin/stipple path/to/icon.svg --plain > icon.txt
cat icon.svg | vendor/bin/stipple -
```

`vendor/bin/stipple --help` lists all options. On failure it writes to stderr and returns a
non-zero exit code.

## Usage

```php
use Wazum\Stipple\Sampler\InkMode;
use Wazum\Stipple\Stipple;

// One-shot, defaults: height 8 cells, terminal default fg, Braille sampler.
echo Stipple::render('/path/to/icon.svg');
echo Stipple::renderFromString('<svg ...>');

// Fluent
echo Stipple::make('/path/to/icon.svg')
    ->height(4)                // cells; valid 1..256
    ->color('#00ffff')         // optional, 6-digit hex; null → terminal default fg
    ->accent('#ff8700')        // overrides the fallback in any var(--icon-color-accent, …) call in the SVG
    ->threshold(0.5)           // cutoff in [0.0, 1.0] for whatever the ink mode measures
    ->inkMode(InkMode::Coverage) // Coverage (default) or Luminance — see below
    ->decorated(true)          // false writes no colour codes, for a file or a pipe
    ->maxRasterDimension(2048) // safety cap on the intermediate raster (default 4096 px)
    ->toString();

// __toString delegates to toString(), so casting works too:
echo (string) Stipple::make('/path/to/icon.svg')->height(4);
```

Every row ends with `\n`.

## Using it with a framework

No bridge package is needed. All of these take a plain string.

**[Symfony Console](https://github.com/symfony/console)** — pass `isDecorated()`, so a pipe or
`--no-ansi` gets clean text. Console removes its own `<info>` tags in that case, but not colour codes
from somewhere else, so without this you would find escape codes in your log file:

```php
$output->write(
    Stipple::make($path)->height(4)->decorated($output->isDecorated())->toString()
);
```

**[Laravel Prompts](https://github.com/laravel/prompts)** — `note()` takes the string as it is:

```php
note(Stipple::make($path)->height(4)->toString());
```

**[Symfony TUI](https://github.com/symfony/tui)** — put the rows in a `TextWidget`. Its layout counts
visible columns and ignores colour codes, so the rows keep their width.

Two things to know about Symfony TUI: it needs PHP 8.4 and is still marked experimental, while
stipple stays on PHP 8.2. And if your terminal speaks the Kitty graphics protocol, Symfony TUI can
show the SVG as a real image with its own `ImageWidget`, which always looks better. stipple is for
the terminals that cannot do that.

## Icons side by side

`toString()` returns one string. To put an icon next to something else, use `toIcon()` and get the
rows separately:

```php
use Wazum\Stipple\RenderedIcon;

$icon = Stipple::make('/path/to/icon.svg')->height(4)->toIcon();

$icon->rows;        // one string per row
$icon->widthCells;  // width in terminal cells
$icon->heightCells; // same as count($icon->rows)
$icon->row(9);      // one row, or an empty row if it does not exist
$icon->blankRow();  // an empty row
echo $icon;         // same as toString()
```

Measure a row with `widthCells`, not `strlen()`. Rows with content hold colour codes, empty rows do
not, so equally wide rows have different string lengths.

```php
$icons = array_map(
    static fn (string $path): RenderedIcon => Stipple::make($path)->height(4)->toIcon(),
    $paths,
);

for ($line = 0; $line < 4; $line++) {
    echo implode('  ', array_map(
        static fn (RenderedIcon $icon): string => $icon->row($line),
        $icons,
    )), "\n";
}
```

`row()` fills a missing row with the sampler's own empty cell, so icons of different height stay in
line.

## Ink mode

The output has one colour, so what counts is which pixels are covered, not which colour they are.
That is the default.

```php
use Wazum\Stipple\Sampler\InkMode;

Stipple::make($path)->toString();                             // Coverage (default)
Stipple::make($path)->inkMode(InkMode::Luminance)->toString();
```

| Mode | A pixel counts when | Use it for |
| ---- | ------------------- | ---------- |
| `Coverage` (default) | it is solid enough, whatever its colour | normal icons, also black or unfilled ones |
| `Luminance` | it is bright enough | light drawings on a dark background |

`threshold()` sets the cut-off for the mode you picked.

With `Luminance` a black icon shows nothing, because black is not bright. That is why `Coverage` is
the default.

## Samplers

| Sampler | Pixels per cell | Glyphs | Notes |
| ------- | --------------- | ------ | ----- |
| `BrailleSampler` (default) | 2×4 | `U+2800`–`U+28FF` | More detail. Needs a font with Braille signs: JetBrains Mono, Cascadia, DejaVu and Iosevka all have them. |
| `HalfBlockSampler` | 1×2 | `▀ ▄ █` | Less detail, but works in every terminal and font. |

```php
use Wazum\Stipple\Sampler\HalfBlockSampler;

Stipple::make($path)->sampler(new HalfBlockSampler())->toString();
```

Empty Braille cells use `U+2800` instead of a space, so icons next to each other keep their columns.
The half-block sampler uses spaces, which can shift by one column in some fonts.

## Extending

Use a different drawing library with `RasterizerInterface`:

```php
use Wazum\Stipple\Rasterizer\RasterizerInterface;

final class MyRasterizer implements RasterizerInterface
{
    public function rasterize(string $svg, int $widthPx, int $heightPx): \GdImage { /* … */ }
}

Stipple::make($path)->rasterizer(new MyRasterizer())->toString();
```

Write your own sampler with `SamplerInterface`. `AbstractSampler` already has the helpers for
reading pixels and writing colour codes:

```php
public function sample(\GdImage $image, SamplerOptions $options): string
```

`SamplerOptions` holds the colour, threshold and ink mode, and checks all three when it is created,
so a sampler never has to. `color()`, `threshold()` and `inkMode()` build one for you, or pass one
yourself:

```php
use Wazum\Stipple\Sampler\SamplerOptions;

Stipple::make($path)->samplerOptions(new SamplerOptions('#00ffff', 0.4))->toString();
```

## Public API

These follow semantic versioning: `Stipple`, `RenderedIcon`, `SamplerInterface`, `SamplerOptions`,
`InkMode`, `BrailleSampler`, `HalfBlockSampler`, `AbstractSampler`, `RasterizerInterface`,
`PhpSvgRasterizer` and the classes under `Exception\`. How the `stipple` command behaves is included.

`SvgPreprocessor`, `PreprocessedSvg` and `Console\StippleCommand` are marked `@internal`. They can
change in any release.

## Security

The SVG is checked before it is drawn:

- `DOCTYPE` and `ENTITY` are refused, both before and after parsing. This blocks XXE attacks. libxml
  runs with `LIBXML_NONET`, so it never opens a network connection.
- `<script>`, `<foreignObject>` and `<image>` are refused. An `<image>` could make the drawing
  library read any local file.
- `Stipple::make()` opens local files only. `http://`, `data://` and other wrappers are refused, so
  a path coming from a user cannot turn into a web request. Load remote files yourself and use
  `makeFromString()`.
- Files over 4 MiB are refused before they are read. Path coordinates far outside the `viewBox` are
  refused as well, because drawing them can use up all memory.
- Control characters in a file path never reach an error message, so a file name cannot send escape
  codes to your terminal.
- Your application's own libxml errors are left untouched.

Every problem becomes a `Wazum\Stipple\Exception\StippleException`, so `catch (StippleException)`
is enough.

## SVG support

Handled for you:

- `currentColor`, in attributes, in `style="…"` and in a `<style>` block.
- `var(--icon-color-accent, #hex)` — uses your `accent()` value, or the fallback from the file.
- Gradients and patterns (`fill="url(#id)"`) become one solid colour, because one colour cannot show
  a gradient. A fallback colour after the reference wins, so `fill="url(#g) none"` stays invisible.
- `<use>` and `<symbol>`, also nested. This makes sprite sheets work. Links to other files are
  refused, never downloaded.
- `<switch>` — the first usable child is kept. Illustrator writes this.
- Minified path data. Arc flags without spaces (`a8 8 0 11-16 0`) and an uppercase `E` are rewritten,
  because the drawing library reads them wrong and then drops the rest of the path.
- `viewBox`, or `width`/`height` with a unit (`16px`, `12pt`, `1in`, `2cm`). `%`, `em` and `ex` are
  refused, because they need a page size.
- `clip-path` that covers the whole `viewBox` is removed, because it changes nothing. Figma and
  Illustrator add one.

Refused with an error, so you never get a wrong picture:

- `clip-path` that really cuts something, and `mask`. Flatten these first.
- `<text>` — no font is available.

Drawn, but not exactly like a browser:

- `<style>` rules for element names (`rect { … }`). Rules for classes do work.
- `stroke-dasharray` is drawn solid, and `filter` is ignored.

Everything else is passed on unchanged.

## License

MIT — see [LICENSE](LICENSE). Bundled demo icons (`examples/icons/`) are MIT-licensed by
the [TYPO3.Icons](https://github.com/TYPO3/TYPO3.Icons) project — see
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
