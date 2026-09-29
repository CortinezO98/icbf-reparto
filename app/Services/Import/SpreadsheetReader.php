<?php
declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;

final class SpreadsheetReader
{
    /** @return array{sheet:string,headers:list<string>,rows:list<array<int,mixed>>} */
    public function read(
        string $path,
        string $extension,
        string $sheetMode,
        ?string $sheetValue,
        int $headerRow,
        int $dataStartRow,
        int $maxRows = 10000
    ): array {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        if ($extension === 'csv' && method_exists($reader, 'setInputEncoding')) {
            $reader->setInputEncoding('UTF-8');
        }

        $spreadsheet = $reader->load($path);
        $sheet = $this->selectSheet($spreadsheet, $sheetMode, $sheetValue);
        $sheetName = $sheet->getTitle();

        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = min($sheet->getHighestDataRow(), $dataStartRow + $maxRows - 1);

        $headerValues = $sheet->rangeToArray(
            "A{$headerRow}:{$highestColumn}{$headerRow}",
            null, true, false
        )[0] ?? [];

        $headers = array_map(
            static fn(mixed $v): string => trim((string)$v),
            array_values($headerValues)
        );

        $rows = [];
        for ($r = $dataStartRow; $r <= $highestRow; $r++) {
            $values = $sheet->rangeToArray(
                "A{$r}:{$highestColumn}{$r}",
                null, true, false
            )[0] ?? [];

            $nonEmpty = array_filter($values, static fn(mixed $v): bool => trim((string)$v) !== '');
            if (!$nonEmpty) {
                continue;
            }
            $rows[$r] = array_values($values);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['sheet'=>$sheetName,'headers'=>$headers,'rows'=>$rows];
    }

    private function selectSheet(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        string $mode,
        ?string $value
    ): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet {
        if ($mode === 'FIRST_MATCH' || $value === null || trim($value) === '') {
            return $spreadsheet->getSheet(0);
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($mode === 'EXACT' && $sheet->getTitle() === $value) {
                return $sheet;
            }
            if ($mode === 'REGEX') {
                $ok = @preg_match($value, $sheet->getTitle());
                if ($ok === 1) return $sheet;
            }
        }

        throw new \RuntimeException('No se encontró la hoja configurada para esta estructura.');
    }
}
