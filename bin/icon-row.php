<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Wazum\Stipple\RenderedIcon;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Stipple;

$fixturesDir = __DIR__.'/../examples/icons';
$icons = [
    'actions-heart',
    'actions-star',
    'actions-cog',
    'actions-search',
    'actions-filter',
    'actions-delete',
    'actions-cart',
    'actions-lock',
    'actions-user',
    'actions-info',
];

$height = 4;
$gap = '  ';

/** @var list<RenderedIcon> $renderedIcons */
$renderedIcons = [];
$labels = [];
foreach ($icons as $iconName) {
    $icon = Stipple::make($fixturesDir.'/'.$iconName.'.svg')
        ->height($height)
        ->sampler(new BrailleSampler())
        ->toIcon();

    $renderedIcons[] = $icon;
    // widthCells rather than a hard-coded column width, so labels stay centred under
    // icons of any aspect ratio.
    $labels[] = str_pad(str_replace('actions-', '', $iconName), $icon->widthCells, ' ', STR_PAD_BOTH);
}

$lineCount = max(array_map(static fn (RenderedIcon $icon): int => $icon->heightCells, $renderedIcons));

echo "\n";
for ($line = 0; $line < $lineCount; $line++) {
    echo implode($gap, array_map(
        // row() pads short icons with their own blank glyph, keeping columns aligned.
        static fn (RenderedIcon $icon): string => $icon->row($line),
        $renderedIcons,
    ))."\n";
}

echo implode($gap, $labels)."\n\n";
