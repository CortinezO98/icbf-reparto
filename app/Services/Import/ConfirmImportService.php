<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Repositories\CaseRepository;
use App\Repositories\ImportBatchRepository;
use PDO;

final class ConfirmImportService
{
    public function __construct(
        private PDO $pdo,
        private ImportBatchRepository $batches,
        private CaseRepository $cases
    ) {
    }

    /** @return array{created:int,duplicates:int} */
    public function confirm(int $batchId, int $userId): array
    {
        $this->pdo->beginTransaction();

        try {
            $batch = $this->batches->findForUpdate($batchId);
            if (!$batch) {
                throw new \RuntimeException('Lote no encontrado.');
            }

            $status = (string)$batch['status'];

            if ($status === 'COMPLETED') {
                throw new \RuntimeException('Este lote ya fue confirmado anteriormente.');
            }

            if (!in_array($status, ['VALIDATED','VALIDATED_WITH_ERRORS'], true)) {
                throw new \RuntimeException('El lote no está en un estado confirmable.');
            }

            $rows = $this->batches->validRowsForConfirmation($batchId);
            if (!$rows) {
                throw new \RuntimeException('El lote no contiene filas válidas para crear casos.');
            }

            $this->batches->markImporting($batchId);

            $created = 0;
            $duplicates = (int)$batch['duplicate_rows'];

            foreach ($rows as $row) {
                $externalKey = trim((string)($row['external_key'] ?? ''));
                if ($externalKey === '') {
                    $this->batches->markRowDuplicate(
                        (int)$row['id'],
                        ['La fila no contiene llave externa válida.']
                    );
                    $duplicates++;
                    continue;
                }

                $existing = $this->cases->findByExternalKeyForUpdate($externalKey);
                if ($existing) {
                    $this->batches->markRowDuplicate(
                        (int)$row['id'],
                        ['Ya existe un caso con esta llave externa.']
                    );
                    $duplicates++;
                    continue;
                }

                $normalized = json_decode((string)($row['normalized_json'] ?? '{}'), true);
                if (!is_array($normalized)) {
                    throw new \RuntimeException(
                        'No fue posible interpretar los datos normalizados de la fila '
                        . (int)$row['source_row_number'] . '.'
                    );
                }

                $radicatedAt = $this->resolveRadicatedAt($normalized);

                $caseId = $this->cases->create([
                    'external_key'=>$externalKey,
                    'queue_id'=>$batch['queue_id'] !== null ? (int)$batch['queue_id'] : null,
                    'source_structure_version_id'=>(int)$batch['structure_version_id'],
                    'source_batch_id'=>$batchId,
                    'source_batch_row_id'=>(int)$row['id'],
                    'petition_type'=>$this->firstValue($normalized, ['tipo_peticion','petition_type']),
                    'regional'=>$this->firstValue($normalized, ['regional']),
                    'segment'=>$this->firstValue($normalized, ['segmento','segment']),
                    'origin_channel'=>$this->firstValue($normalized, ['canal_origen','origin_channel']),
                    'radicated_at'=>$radicatedAt,
                    'created_by'=>$userId,
                ]);

                $this->cases->addCreatedEvent($caseId, $userId, $batchId);
                $created++;
            }

            if ($created === 0 && $duplicates === 0) {
                throw new \RuntimeException('La confirmación no produjo casos ni duplicados.');
            }

            $this->batches->markCompleted($batchId, $userId, $created, $duplicates);
            $this->pdo->commit();

            return ['created'=>$created,'duplicates'=>$duplicates];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /** @param array<string,mixed> $data
     *  @param list<string> $keys
     */
    private function firstValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if ($value !== null && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        return null;
    }

    /** @param array<string,mixed> $data */
    private function resolveRadicatedAt(array $data): ?string
    {
        $date = $this->firstValue($data, [
            'fecha_radicacion',
            'fecha_peticion',
            'radicated_date',
        ]);

        if ($date === null) {
            return null;
        }

        $time = $this->firstValue($data, [
            'hora_radicacion',
            'hora_peticion',
            'radicated_time',
        ]) ?? '00:00:00';

        $candidate = trim($date . ' ' . $time);
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $candidate);

        if ($dt === false) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
