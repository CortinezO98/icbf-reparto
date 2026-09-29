<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Repositories\ImportBatchRepository;
use PDO;

final class ImportStagingService
{
    public function __construct(
        private PDO $pdo,
        private ImportBatchRepository $repo
    ) {
    }

    /** @param array<string,mixed> $file */
    public function stage(array $file, int $versionId, ?int $queueId, int $userId): int
    {
        $version = $this->repo->activeVersion($versionId);
        if (!$version) throw new \RuntimeException('La estructura seleccionada no está activa.');

        $fields = $this->repo->fields($versionId);
        if (!$fields) throw new \RuntimeException('La estructura activa no tiene campos configurados.');

        if ($queueId !== null) {
            $validQueue = false;
            foreach ($this->repo->queuesForVersion($versionId) as $q) {
                if ((int)$q['id'] === $queueId) $validQueue = true;
            }
            if (!$validQueue) throw new \RuntimeException('La cola no está asociada a la estructura seleccionada.');
        }

        $meta = (new ImportFileValidator())->validate(
            $file,
            (bool)$version['allow_csv'],
            (bool)$version['allow_xlsx']
        );

        if ($this->repo->duplicateCompletedHash($meta['sha256'])) {
            throw new \RuntimeException('Este archivo ya fue cargado anteriormente.');
        }

        $storageDir = dirname(__DIR__, 3) . '/storage/imports/incoming';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
            throw new \RuntimeException('No fue posible preparar el almacenamiento de importaciones.');
        }

        $stored = bin2hex(random_bytes(24)) . '.' . $meta['extension'];
        $destination = $storageDir . '/' . $stored;

        if (!move_uploaded_file($meta['tmp_name'], $destination)) {
            throw new \RuntimeException('No fue posible almacenar el archivo.');
        }

        try {
            $data = (new SpreadsheetReader())->read(
                $destination,
                $meta['extension'],
                (string)$version['target_sheet_mode'],
                $version['target_sheet_value'] !== null ? (string)$version['target_sheet_value'] : null,
                (int)$version['header_row'],
                (int)$version['data_start_row']
            );

            $headerSignature = HeaderNormalizer::signature($data['headers']);

            $validator = new RowValidator();
            $rows = [];
            $seen = [];
            $valid = $invalid = $duplicates = 0;

            foreach ($data['rows'] as $rowNumber => $values) {
                $result = $validator->validate(
                    $fields,
                    $data['headers'],
                    $values,
                    (string)$version['external_key_field_code']
                );

                $status = $result['errors'] ? 'INVALID' : 'VALID';
                $external = $result['external_key'];

                if ($status === 'VALID' && $external !== null) {
                    if (isset($seen[$external])) {
                        $status = 'DUPLICATE';
                        $result['errors'][] = 'Llave externa repetida dentro del mismo archivo.';
                    } else {
                        $seen[$external] = true;
                    }
                }

                if ($status === 'VALID') $valid++;
                elseif ($status === 'DUPLICATE') $duplicates++;
                else $invalid++;

                $rows[] = [
                    'source_row_number'=>(int)$rowNumber,
                    'external_key'=>$external,
                    'raw'=>$result['raw'],
                    'normalized'=>$result['normalized'],
                    'validation_status'=>$status,
                    'errors'=>$result['errors'],
                ];
            }

            $total = count($rows);
            if ($total === 0) throw new \RuntimeException('El archivo no contiene filas de datos.');

            $status = ($invalid > 0 || $duplicates > 0) ? 'VALIDATED_WITH_ERRORS' : 'VALIDATED';
            $batchNumber = 'IMP-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $this->pdo->beginTransaction();
            try {
                $batchId = $this->repo->create([
                    'batch_number'=>$batchNumber,
                    'structure_version_id'=>$versionId,
                    'queue_id'=>$queueId,
                    'original_filename'=>$meta['original_name'],
                    'stored_filename'=>$stored,
                    'file_sha256'=>$meta['sha256'],
                    'file_size_bytes'=>$meta['size'],
                    'mime_type'=>$meta['mime'],
                    'selected_sheet_name'=>$data['sheet'],
                    'header_signature'=>$headerSignature,
                    'status'=>$status,
                    'total_rows'=>$total,
                    'valid_rows'=>$valid,
                    'invalid_rows'=>$invalid,
                    'duplicate_rows'=>$duplicates,
                    'uploaded_by'=>$userId,
                    'validated_by'=>$userId,
                    'error_message'=>null,
                    'validation_summary'=>[
                        'headers'=>$data['headers'],
                        'sheet'=>$data['sheet'],
                        'valid'=>$valid,
                        'invalid'=>$invalid,
                        'duplicates'=>$duplicates,
                    ],
                ]);
                $this->repo->insertRows($batchId, $rows);
                $this->pdo->commit();
                return $batchId;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            @unlink($destination);
            throw $e;
        }
    }
}
