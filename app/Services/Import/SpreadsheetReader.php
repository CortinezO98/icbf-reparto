<?php
declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class SpreadsheetReader
{
    private const MAX_IMPORT_COLUMNS = 128;

    /** @return array{sheet:string,headers:list<string>,rows:array<int,array<int,mixed>>} */
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

        if ($extension === 'csv') {
            if (method_exists($reader, 'setInputEncoding')) {
                $reader->setInputEncoding('UTF-8');
            }

            return $this->readLoadedWorkbook(
                $reader,
                $path,
                null,
                $headerRow,
                $dataStartRow,
                $maxRows
            );
        }

        $sheetNames = $reader->listWorksheetNames($path);
        $selectedSheetName = $this->selectSheetName($sheetNames, $sheetMode, $sheetValue);

        $reader->setLoadSheetsOnly([$selectedSheetName]);
        $reader->setReadFilter(
            new SpreadsheetReadFilter(
                min($headerRow, $dataStartRow),
                $dataStartRow + $maxRows - 1,
                self::MAX_IMPORT_COLUMNS
            )
        );

        return $this->readLoadedWorkbook(
            $reader,
            $path,
            $selectedSheetName,
            $headerRow,
            $dataStartRow,
            $maxRows
        );
    }

    /** @return array{sheet:string,headers:list<string>,rows:array<int,array<int,mixed>>} */
    private function readLoadedWorkbook(
        IReader $reader,
        string $path,
        ?string $selectedSheetName,
        int $headerRow,
        int $dataStartRow,
        int $maxRows
    ): array {
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $selectedSheetName !== null
                ? $spreadsheet->getSheetByName($selectedSheetName)
                : $spreadsheet->getSheet(0);

            if (!$sheet instanceof Worksheet) {
                throw new \RuntimeException('No fue posible abrir la hoja seleccionada.');
            }

            $sheetName = $sheet->getTitle();

            $headerValues = $sheet->rangeToArray(
                'A' . $headerRow . ':'
                . Coordinate::stringFromColumnIndex(self::MAX_IMPORT_COLUMNS)
                . $headerRow,
                null,
                true,
                false
            )[0] ?? [];

            $lastHeaderIndex = $this->lastMeaningfulColumnIndex($headerValues);
            if ($lastHeaderIndex === 0) {
                throw new \RuntimeException('No se encontraron encabezados en la fila configurada.');
            }

            $headerValues = array_slice($headerValues, 0, $lastHeaderIndex);
            $headers = array_map(
                static fn(mixed $value): string => trim((string)$value),
                array_values($headerValues)
            );

            $lastColumn = Coordinate::stringFromColumnIndex($lastHeaderIndex);
            $highestRow = min(
                $sheet->getHighestDataRow(),
                $dataStartRow + $maxRows - 1
            );

            $rows = [];

            for ($rowNumber = $dataStartRow; $rowNumber <= $highestRow; $rowNumber++) {
                $values = $sheet->rangeToArray(
                    "A{$rowNumber}:{$lastColumn}{$rowNumber}",
                    null,
                    true,
                    false
                )[0] ?? [];

                $nonEmpty = array_filter(
                    $values,
                    static fn(mixed $value): bool => trim((string)$value) !== ''
                );

                if ($nonEmpty === []) {
                    continue;
                }

                $rows[$rowNumber] = array_values($values);
            }

            return [
                'sheet'=>$sheetName,
                'headers'=>$headers,
                'rows'=>$rows,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /** @param list<string> $sheetNames */
    private function selectSheetName(
        array $sheetNames,
        string $mode,
        ?string $value
    ): string {
        if ($sheetNames === []) {
            throw new \RuntimeException('El archivo no contiene hojas disponibles.');
        }

        if ($mode === 'FIRST_MATCH' || $value === null || trim($value) === '') {
            return $sheetNames[0];
        }

        $expected = trim($value);

        foreach ($sheetNames as $sheetName) {
            if ($mode === 'EXACT' && trim($sheetName) === $expected) {
                return $sheetName;
            }

            if ($mode === 'REGEX') {
                $ok = @preg_match($expected, $sheetName);
                if ($ok === 1) {
                    return $sheetName;
                }
            }
        }

        throw new \RuntimeException(
            'No se encontró la hoja configurada. Hojas disponibles: '
            . implode(', ', $sheetNames)
            . '.'
        );
    }

    /** @param array<int,mixed> $values */
    private function lastMeaningfulColumnIndex(array $values): int
    {
        for ($index = count($values) - 1; $index >= 0; $index--) {
            if (trim((string)$values[$index]) !== '') {
                return $index + 1;
            }
        }

        return 0;
    }
}
