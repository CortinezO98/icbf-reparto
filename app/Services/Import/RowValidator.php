<?php
declare(strict_types=1);

namespace App\Services\Import;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class RowValidator
{
    /** @param list<array<string,mixed>> $fields
     *  @param list<string> $headers
     *  @param array<int,mixed> $values
     *  @return array{raw:array<string,mixed>,normalized:array<string,mixed>,errors:list<string>,external_key:?string}
     */
    public function validate(array $fields, array $headers, array $values, string $externalKeyCode): array
    {
        $headerMap = [];
        foreach ($headers as $index => $header) {
            $headerMap[HeaderNormalizer::normalize($header)] = $index;
        }

        $raw = [];
        $normalized = [];
        $errors = [];

        foreach ($fields as $field) {
            $candidates = [(string)$field['excel_header']];
            $aliases = json_decode((string)($field['header_aliases_json'] ?? ''), true);

            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    $candidates[] = (string)$alias;
                }
            }

            $column = null;
            foreach ($candidates as $candidate) {
                $normalizedHeader = HeaderNormalizer::normalize($candidate);
                if (array_key_exists($normalizedHeader, $headerMap)) {
                    $column = $headerMap[$normalizedHeader];
                    break;
                }
            }

            $code = (string)$field['field_code'];
            $value = $column !== null ? ($values[$column] ?? null) : null;
            $raw[$code] = $value;

            $type = (string)$field['data_type'];

            try {
                $normalizedValue = $this->normalizeValue(
                    $value,
                    $type,
                    isset($field['date_format']) ? (string)$field['date_format'] : null
                );
            } catch (\RuntimeException $e) {
                $normalizedValue = null;
                $errors[] = "{$field['display_name']}: {$e->getMessage()}";
            }

            $normalized[$code] = $normalizedValue;
            $text = $normalizedValue === null ? '' : trim((string)$normalizedValue);

            if ((int)$field['is_required'] === 1 && $text === '') {
                $errors[] = "Campo obligatorio ausente: {$field['display_name']}.";
                continue;
            }

            if (
                $text !== ''
                && !empty($field['max_length'])
                && mb_strlen($text) > (int)$field['max_length']
            ) {
                $errors[] = "Longitud inválida en {$field['display_name']}.";
            }

            if ($text !== '' && !empty($field['validation_regex'])) {
                $ok = @preg_match((string)$field['validation_regex'], $text);
                if ($ok !== 1) {
                    $errors[] = "Formato inválido en {$field['display_name']}.";
                }
            }

            if ($text !== '' && $type === 'INTEGER' && filter_var($text, FILTER_VALIDATE_INT) === false) {
                $errors[] = "{$field['display_name']} debe ser entero.";
            }

            if ($text !== '' && $type === 'DECIMAL' && !is_numeric(str_replace(',', '.', $text))) {
                $errors[] = "{$field['display_name']} debe ser numérico.";
            }
        }

        $external = null;
        $externalValue = $normalized[$externalKeyCode] ?? null;

        if ($externalValue !== null) {
            $candidate = trim((string)$externalValue);

            if ($candidate !== '') {
                $external = $candidate;
            }
        }

        if ($external === null) {
            $errors[] = 'No se encontró la llave externa del registro.';
        }

        return [
            'raw'=>$raw,
            'normalized'=>$normalized,
            'errors'=>$errors,
            'external_key'=>$external,
        ];
    }

    private function normalizeValue(mixed $value, string $type, ?string $configuredFormat): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return match ($type) {
                'DATE' => $value->format('Y-m-d'),
                'TIME' => $value->format('H:i:s'),
                'DATETIME' => $value->format('Y-m-d H:i:s'),
                default => trim($value->format('Y-m-d H:i:s')),
            };
        }

        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }

        return match ($type) {
            'DATE' => $this->normalizeDate($value, $configuredFormat),
            'TIME' => $this->normalizeTime($value),
            'DATETIME' => $this->normalizeDateTime($value, $configuredFormat),
            'INTEGER' => $this->normalizeInteger($value),
            'DECIMAL' => $this->normalizeDecimal($value),
            'BOOLEAN' => $this->normalizeBoolean($value),
            default => $text,
        };
    }

    private function normalizeDate(mixed $value, ?string $configuredFormat): string
    {
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
            } catch (\Throwable) {
                throw new \RuntimeException('fecha Excel inválida.');
            }
        }

        $text = trim((string)$value);
        $formats = array_values(array_filter([
            $configuredFormat,
            'd/m/Y',
            'Y-m-d',
            'd-m-Y',
            'm/d/Y',
        ]));

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text);
            if ($date !== false) {
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $date->format('Y-m-d');
                }
            }
        }

        throw new \RuntimeException('fecha inválida.');
    }

    private function normalizeTime(mixed $value): string
    {
        if (is_numeric($value)) {
            $fraction = (float)$value;
            $fraction -= floor($fraction);

            $seconds = (int)round($fraction * 86400) % 86400;
            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $secs = $seconds % 60;

            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        $text = trim((string)$value);
        foreach (['H:i:s', 'H:i', 'h:i:s A', 'h:i A'] as $format) {
            $time = \DateTimeImmutable::createFromFormat('!' . $format, $text);
            if ($time !== false) {
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $time->format('H:i:s');
                }
            }
        }

        throw new \RuntimeException('hora inválida.');
    }

    private function normalizeDateTime(mixed $value, ?string $configuredFormat): string
    {
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                throw new \RuntimeException('fecha/hora Excel inválida.');
            }
        }

        $text = trim((string)$value);
        $formats = array_values(array_filter([
            $configuredFormat,
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ]));

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text);
            if ($date !== false) {
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $date->format('Y-m-d H:i:s');
                }
            }
        }

        throw new \RuntimeException('fecha/hora inválida.');
    }

    private function normalizeInteger(mixed $value): int|string
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int)$value;
        }

        $text = trim((string)$value);
        if (preg_match('/^-?\d+$/', $text) === 1) {
            return $text;
        }

        throw new \RuntimeException('valor entero inválido.');
    }

    private function normalizeDecimal(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string)$value));

        if (!is_numeric($text)) {
            throw new \RuntimeException('valor decimal inválido.');
        }

        return $text;
    }

    private function normalizeBoolean(mixed $value): int
    {
        $text = mb_strtolower(trim((string)$value), 'UTF-8');

        if (in_array($text, ['1','true','si','sí','yes','y'], true)) {
            return 1;
        }

        if (in_array($text, ['0','false','no','n'], true)) {
            return 0;
        }

        throw new \RuntimeException('valor booleano inválido.');
    }
}
