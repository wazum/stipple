<?php

declare(strict_types=1);

namespace Wazum\Stipple;

final readonly class RenderedIcon implements \Stringable
{
    /**
     * @param list<string> $rows      one entry per cell row, ANSI-wrapped, without trailing newline
     * @param string       $blankCell the sampler's empty-cell glyph, used to pad short rows
     */
    public function __construct(
        public array $rows,
        public int $widthCells,
        public int $heightCells,
        public string $blankCell,
    ) {
    }

    /**
     * Row $index, or a blank row when out of range, so icons of differing heights can be
     * composed side by side without the caller tracking which one is tallest.
     */
    public function row(int $index): string
    {
        return $this->rows[$index] ?? $this->blankRow();
    }

    public function blankRow(): string
    {
        return str_repeat($this->blankCell, $this->widthCells);
    }

    public function toString(): string
    {
        return $this->rows === [] ? '' : implode("\n", $this->rows)."\n";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
