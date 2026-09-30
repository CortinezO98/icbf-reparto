<?php
declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

final class SpreadsheetReadFilter implements IReadFilter
{
    public function __construct(
        private int $minRow,
        private int $maxRow,
        private int $maxColumnIndex = 128
    ) {
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        if ($row < $this->minRow || $row > $this->maxRow) {
            return false;
        }

        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress);

        return $columnIndex <= $this->maxColumnIndex;
    }
}
