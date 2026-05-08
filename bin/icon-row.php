<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

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

/** @var list<list<string>> $iconRows each entry is the lines of one icon */
$iconRows = [];
$labels = [];
foreach ($icons as $iconName) {
    $rendered = Stipple::make($fixturesDir.'/'.$iconName.'.svg')
        ->height($height)
        ->sampler(new BrailleSampler())
        ->toString();

    $iconRows[] = explode("\n", rtrim($rendered, "\n"));
    $labels[] = str_pad(str_replace('actions-', '', $iconName), 8, ' ', STR_PAD_BOTH);
}

echo "\n";
for ($line = 0; $line < $height; $line++) {
    $rowParts = [];
    foreach ($iconRows as $iconLines) {
        $rowParts[] = $iconLines[$line] ?? '';
    }
    echo implode($gap, $rowParts)."\n";
}

echo implode($gap, $labels)."\n\n";
