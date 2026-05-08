<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Stipple;

$fixturesDir = __DIR__.'/../examples/icons';
$icons = [
    'actions-cloud',
    'actions-code',
    'module-page',
];

$heights = [4, 6, 8];

foreach ($icons as $iconName) {
    $iconPath = $fixturesDir.'/'.$iconName.'.svg';
    if (!is_readable($iconPath)) {
        fwrite(STDERR, "Cannot read $iconPath\n");
        continue;
    }

    foreach ($heights as $height) {
        echo "\n\e[1m== {$iconName} (Braille, height {$height}) ==\e[0m\n\n";
        echo Stipple::make($iconPath)
            ->height($height)
            ->sampler(new BrailleSampler())
            ->toString();

        echo "\n\e[1m== {$iconName} (Half-block, height {$height}) ==\e[0m\n\n";
        echo Stipple::make($iconPath)
            ->height($height)
            ->sampler(new HalfBlockSampler())
            ->toString();
    }
}

echo "\n";
