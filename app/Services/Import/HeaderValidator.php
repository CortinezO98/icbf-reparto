<?php
declare(strict_types=1);

namespace App\Services\Import;

final class HeaderValidator
{
    /**
     * @param list<array<string,mixed>> $fields
     * @param list<string> $headers
     */
    public function validate(array $fields, array $headers): void
    {
        $normalizedHeaders = [];
        foreach ($headers as $header) {
            $normalized = HeaderNormalizer::normalize($header);
            if ($normalized === '') {
                continue;
            }

            if (isset($normalizedHeaders[$normalized])) {
                throw new \RuntimeException(
                    'El archivo contiene encabezados duplicados o ambiguos: ' . $header . '.'
                );
            }

            $normalizedHeaders[$normalized] = true;
        }

        $missing = [];

        foreach ($fields as $field) {
            if ((int)($field['is_required'] ?? 0) !== 1) {
                continue;
            }

            $candidates = [(string)$field['excel_header']];
            $aliases = json_decode((string)($field['header_aliases_json'] ?? ''), true);

            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    $candidates[] = (string)$alias;
                }
            }

            $found = false;
            foreach ($candidates as $candidate) {
                $normalized = HeaderNormalizer::normalize($candidate);
                if ($normalized !== '' && isset($normalizedHeaders[$normalized])) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $missing[] = (string)$field['excel_header'];
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'La estructura del archivo no coincide. Faltan encabezados obligatorios: '
                . implode(', ', $missing)
                . '.'
            );
        }
    }
}
