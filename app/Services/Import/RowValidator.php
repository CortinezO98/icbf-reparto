<?php
declare(strict_types=1);

namespace App\Services\Import;

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
                foreach ($aliases as $a) $candidates[] = (string)$a;
            }

            $column = null;
            foreach ($candidates as $candidate) {
                $n = HeaderNormalizer::normalize($candidate);
                if (array_key_exists($n, $headerMap)) {
                    $column = $headerMap[$n];
                    break;
                }
            }

            $code = (string)$field['field_code'];
            $value = $column !== null ? ($values[$column] ?? null) : null;
            $text = trim((string)($value ?? ''));

            $raw[$code] = $value;
            $normalized[$code] = $text === '' ? null : $text;

            if ((int)$field['is_required'] === 1 && $text === '') {
                $errors[] = "Campo obligatorio ausente: {$field['display_name']}.";
                continue;
            }

            if ($text !== '' && !empty($field['max_length']) && mb_strlen($text) > (int)$field['max_length']) {
                $errors[] = "Longitud inválida en {$field['display_name']}.";
            }

            if ($text !== '' && !empty($field['validation_regex'])) {
                $ok = @preg_match((string)$field['validation_regex'], $text);
                if ($ok !== 1) $errors[] = "Formato inválido en {$field['display_name']}.";
            }

            $type = (string)$field['data_type'];
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

        return ['raw'=>$raw,'normalized'=>$normalized,'errors'=>$errors,'external_key'=>$external];
    }
}
