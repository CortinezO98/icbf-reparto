<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Repositories\ImportStructureRepository;

final class StructureVersionBuilder
{
    public function __construct(
        private ImportStructureRepository $structures,
        private ImportFileValidator $fileValidator,
        private SpreadsheetReader $reader
    ) {
    }

    /**
     * Creates a DRAFT version from the headers of an uploaded XLSX/CSV.
     *
     * The upload is only used to build the version definition. It never
     * creates cases or imports rows.
     *
     * @param array<string,mixed> $file
     * @return array{version_id:int,version_number:int,sheet:string,headers:list<string>,external_key:string|null}
     */
    public function createDraftFromFile(int $structureId, array $file, int $userId): array
    {
        $meta = $this->fileValidator->validate($file, true, true);

        $parsed = $this->reader->read(
            $meta['tmp_name'],
            $meta['extension'],
            'FIRST_MATCH',
            null,
            1,
            2,
            10000
        );

        $headers = $parsed['headers'];
        if ($headers === []) {
            throw new \RuntimeException('El archivo no contiene encabezados utilizables.');
        }

        $normalizedSeen = [];
        $fields = [];

        foreach ($headers as $position => $header) {
            $displayName = trim($header);

            if ($displayName === '') {
                throw new \RuntimeException(
                    'El encabezado de la columna ' . ($position + 1) . ' está vacío.'
                );
            }

            $normalized = HeaderNormalizer::normalize($displayName);
            if ($normalized === '') {
                throw new \RuntimeException(
                    'El encabezado "' . $displayName . '" no contiene caracteres utilizables.'
                );
            }

            if (isset($normalizedSeen[$normalized])) {
                throw new \RuntimeException(
                    'El archivo contiene encabezados duplicados o ambiguos: "' . $displayName . '".'
                );
            }

            $normalizedSeen[$normalized] = true;

            $fields[] = [
                'field_code' => $this->fieldCode($normalized, $position + 1),
                'display_name' => $displayName,
                'excel_header' => $displayName,
                'header_aliases_json' => null,
                'data_type' => 'STRING',
                'is_required' => 0,
                'is_external_key' => 0,
                'is_reportable' => 1,
                'max_length' => null,
                'validation_regex' => null,
                'date_format' => null,
                'sort_order' => $position + 1,
            ];
        }

        $externalKey = $this->detectExternalKey($fields);

        if ($externalKey !== null) {
            foreach ($fields as &$field) {
                if ($field['field_code'] === $externalKey) {
                    $field['is_external_key'] = 1;
                    $field['is_required'] = 1;
                    break;
                }
            }
            unset($field);
        }

        $versionId = $this->structures->createVersionFromHeaders(
            $structureId,
            [
                'target_sheet_mode' => 'FIRST_MATCH',
                'target_sheet_value' => $parsed['sheet'],
                'header_row' => 1,
                'data_start_row' => 2,
                'external_key_field_code' => $externalKey ?? '',
                'allow_csv' => $meta['extension'] === 'csv' ? 1 : 0,
                'allow_xlsx' => $meta['extension'] === 'xlsx' ? 1 : 0,
                'header_signature' => HeaderNormalizer::signature($headers),
                'notes' => 'Versión generada automáticamente desde ' . $meta['original_name'],
            ],
            $fields,
            $userId
        );

        return [
            'version_id' => $versionId,
            'version_number' => $this->structures->versionNumber($versionId),
            'sheet' => $parsed['sheet'],
            'headers' => $headers,
            'external_key' => $externalKey,
        ];
    }

    private function fieldCode(string $normalized, int $position): string
    {
        $code = strtolower(str_replace(' ', '_', $normalized));
        $code = preg_replace('/[^a-z0-9_]+/', '', $code) ?? '';
        $code = trim(preg_replace('/_+/', '_', $code) ?? '', '_');

        if ($code === '') {
            $code = 'campo_' . $position;
        }

        if (!preg_match('/^[a-z]/', $code)) {
            $code = 'campo_' . $code;
        }

        return substr($code, 0, 100);
    }

    /**
     * @param list<array<string,mixed>> $fields
     */
    private function detectExternalKey(array $fields): ?string
    {
        $preferred = [
            'numero_peticion',
            'numero_de_peticion',
            'numero_sim',
            'numero_de_sim',
            'numero_solicitud',
            'numero_de_solicitud',
            'numero_caso',
            'numero_de_caso',
            'id_peticion',
            'id_caso',
            'sim',
        ];

        foreach ($preferred as $candidate) {
            foreach ($fields as $field) {
                if ($field['field_code'] === $candidate) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
