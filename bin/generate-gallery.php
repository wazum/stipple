#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Regenerates GALLERY.md and the icon wall in README.md from the actual renderer, so the
 * pictures in the docs cannot drift away from what the library does.
 *
 * Run from a checkout:  php bin/generate-gallery.php
 *
 * Output is not byte-reproducible across GD builds — different libgd versions place shape edges
 * a pixel apart — so regenerate locally, commit the result, and review the diff. There is
 * deliberately no CI check for freshness.
 */

require __DIR__.'/../vendor/autoload.php';

use Wazum\Stipple\RenderedIcon;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Sampler\SamplerInterface;
use Wazum\Stipple\Stipple;

const ICON_DIR = __DIR__.'/../examples/icons';
const WALL_START = '<!-- gallery:start -->';
const WALL_END = '<!-- gallery:end -->';

/** @return list<string> */
function iconNames(): array
{
    $files = glob(ICON_DIR.'/*.svg') ?: [];
    $names = array_map(static fn (string $file): string => basename($file, '.svg'), $files);
    sort($names);

    return $names;
}

function render(string $name, SamplerInterface $sampler, int $height): RenderedIcon
{
    return Stipple::make(ICON_DIR.'/'.$name.'.svg')->height($height)->sampler($sampler)->toIcon();
}

/** Strips SGR so the docs show glyphs rather than escape codes. */
function plain(RenderedIcon $icon): string
{
    return implode("\n", array_map(
        static fn (string $row): string => (string) preg_replace('/\e\[[0-9;]*m/', '', $row),
        $icon->rows,
    ));
}

/**
 * Icons laid out side by side with their names underneath, the pattern from bin/icon-row.php.
 *
 * @param list<string> $names
 */
function wall(array $names, SamplerInterface $sampler, int $height, int $perRow): string
{
    $output = '';

    foreach (array_chunk($names, $perRow) as $chunk) {
        $icons = array_map(static fn (string $name): RenderedIcon => render($name, $sampler, $height), $chunk);

        for ($line = 0; $line < $height; $line++) {
            $cells = array_map(
                static fn (RenderedIcon $icon): string => (string) preg_replace('/\e\[[0-9;]*m/', '', $icon->row($line)),
                $icons,
            );
            $output .= implode('  ', $cells)."\n";
        }

        $labels = [];
        foreach ($chunk as $position => $name) {
            $short = str_replace(['actions-', 'module-'], '', $name);
            $width = $icons[$position]->widthCells;
            $labels[] = mb_strlen($short) > $width
                ? mb_substr($short, 0, $width)
                : str_pad($short, $width, ' ', STR_PAD_BOTH);
        }
        $output .= implode('  ', $labels)."\n\n";
    }

    return rtrim($output, "\n");
}

$names = iconNames();
if ($names === []) {
    fwrite(STDERR, "No icons found in examples/icons.\n");
    exit(1);
}

// --- GALLERY.md -------------------------------------------------------------------------------

$gallery = <<<'MD'
    # Gallery

    Every icon bundled in `examples/icons`, rendered by stipple itself. Regenerate with
    `php bin/generate-gallery.php` — never edit this file by hand.

    Colour is stripped here so the glyphs are readable on any background; the library emits an
    SGR-wrapped row when you ask for a colour.

    MD."\n\n";

$gallery .= "## Braille, height 4\n\n```\n".wall($names, new BrailleSampler(), 4, 5)."\n```\n\n";
$gallery .= "## Braille, height 8\n\n```\n".wall($names, new BrailleSampler(), 8, 4)."\n```\n\n";
$gallery .= "## Half-block, height 8\n\n```\n".wall($names, new HalfBlockSampler(), 8, 4)."\n```\n\n";

$gallery .= "## One icon at every height\n\n";
foreach ([2, 4, 6, 8, 12] as $height) {
    $icon = render('actions-brand-github', new BrailleSampler(), $height);
    $gallery .= sprintf(
        "`height(%d)` — %d×%d cells\n\n```\n%s\n```\n\n",
        $height,
        $icon->widthCells,
        $icon->heightCells,
        plain($icon),
    );
}

file_put_contents(__DIR__.'/../GALLERY.md', $gallery);

// --- README icon wall -------------------------------------------------------------------------

$readmePath = __DIR__.'/../README.md';
$readme = (string) file_get_contents($readmePath);
$start = strpos($readme, WALL_START);
$end = strpos($readme, WALL_END);

if ($start === false || $end === false) {
    fwrite(STDERR, "README.md has no gallery markers; wrote GALLERY.md only.\n");
    exit(0);
}

$block = WALL_START."\n\n```\n".wall($names, new BrailleSampler(), 4, 7)."\n```\n\n";
$readme = substr($readme, 0, $start).$block.substr($readme, $end);
file_put_contents($readmePath, $readme);

printf("Wrote GALLERY.md and refreshed the README icon wall (%d icons).\n", count($names));
