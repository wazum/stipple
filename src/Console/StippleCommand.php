<?php

declare(strict_types=1);

namespace Wazum\Stipple\Console;

use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Sampler\BrailleSampler;
use Wazum\Stipple\Sampler\HalfBlockSampler;
use Wazum\Stipple\Sampler\InkMode;
use Wazum\Stipple\Sampler\SamplerInterface;
use Wazum\Stipple\Stipple;

/**
 * Argument handling for the bundled `stipple` command.
 *
 * @internal the command is supported; this class is not part of the frozen API
 */
final class StippleCommand
{
    private const VALUE_OPTIONS = ['height', 'color', 'accent', 'threshold', 'sampler', 'ink'];

    public function usage(): string
    {
        return <<<'TEXT'
            stipple — render a small SVG icon as monochrome ANSI

            Usage:
              stipple <icon.svg> [options]
              cat icon.svg | stipple - [options]

            Options:
              --height=N        rows of terminal cells, 1..256 (default 8)
              --sampler=NAME    braille (default) or half-block
              --color=#rrggbb   foreground; omit for the terminal default
              --accent=#rrggbb  replaces var(--icon-color-accent, …) in the SVG
              --threshold=F     cutoff in 0.0..1.0 (default 0.5)
              --ink=NAME        coverage (default) or luminance
              -h, --help        this text

            Examples:
              stipple icon.svg --height=4
              stipple icon.svg --sampler=half-block --color=#00ffff
              stipple logo.svg --ink=luminance   # light-on-dark artwork

            TEXT;
    }

    /**
     * @param list<string> $arguments
     *
     * @throws InvalidArgumentException on unusable arguments
     */
    public function render(array $arguments, ?string $standardInput = null): string
    {
        $source = null;
        $options = [];

        for ($index = 0, $count = count($arguments); $index < $count; $index++) {
            $argument = $arguments[$index];

            if (!str_starts_with($argument, '--')) {
                if ($source !== null) {
                    throw new InvalidArgumentException('Render one icon at a time.');
                }
                $source = $argument;
                continue;
            }

            $name = substr($argument, 2);
            $value = null;
            if (str_contains($name, '=')) {
                [$name, $value] = explode('=', $name, 2);
            }

            if (!in_array($name, self::VALUE_OPTIONS, true)) {
                throw new InvalidArgumentException(sprintf('Unknown option --%s. Try --help.', $name));
            }

            if ($value === null) {
                if (++$index >= $count) {
                    throw new InvalidArgumentException(sprintf('Option --%s needs a value.', $name));
                }
                $value = $arguments[$index];
            }

            $options[$name] = $value;
        }

        if ($source === null) {
            throw new InvalidArgumentException('No SVG given. Try --help.');
        }

        $stipple = $source === '-'
            ? Stipple::makeFromString($standardInput ?? '')
            : Stipple::make($source);

        // Exhaustive over VALUE_OPTIONS; PHPStan flags the two lists drifting apart.
        foreach ($options as $name => $value) {
            $stipple = match ($name) {
                'height' => $stipple->height($this->integer($name, $value)),
                'color' => $stipple->color($value),
                'accent' => $stipple->accent($value),
                'threshold' => $stipple->threshold($this->number($name, $value)),
                'sampler' => $stipple->sampler($this->sampler($value)),
                'ink' => $stipple->inkMode($this->inkMode($value)),
            };
        }

        return $stipple->toString();
    }

    private function sampler(string $value): SamplerInterface
    {
        return match (strtolower($value)) {
            'braille' => new BrailleSampler(),
            'half-block', 'halfblock' => new HalfBlockSampler(),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown sampler "%s"; expected braille or half-block.',
                $value,
            )),
        };
    }

    private function inkMode(string $value): InkMode
    {
        return match (strtolower($value)) {
            'coverage' => InkMode::Coverage,
            'luminance' => InkMode::Luminance,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown ink mode "%s"; expected coverage or luminance.',
                $value,
            )),
        };
    }

    private function integer(string $name, string $value): int
    {
        $parsed = filter_var($value, \FILTER_VALIDATE_INT);
        if ($parsed === false) {
            throw new InvalidArgumentException(sprintf('Option --%s expects a whole number; got "%s".', $name, $value));
        }

        return $parsed;
    }

    private function number(string $name, string $value): float
    {
        $parsed = filter_var($value, \FILTER_VALIDATE_FLOAT);
        if ($parsed === false) {
            throw new InvalidArgumentException(sprintf('Option --%s expects a number; got "%s".', $name, $value));
        }

        return $parsed;
    }
}
