<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ImportBatchRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT b.*, s.name structure_name, q.name queue_name, u.full_name uploaded_by_name
                FROM import_batches b
                JOIN import_structure_versions v ON v.id=b.structure_version_id
                JOIN import_structures s ON s.id=v.structure_id
                LEFT JOIN work_queues q ON q.id=b.queue_id
                JOIN users u ON u.id=b.uploaded_by
                ORDER BY b.id DESC
                LIMIT {$limit}";
        return $this->pdo->query($sql)->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function activeVersions(): array
    {
        $sql = "SELECT v.id, v.version_number, v.target_sheet_mode, v.target_sheet_value,
                       v.header_row, v.data_start_row, v.external_key_field_code,
                       v.allow_csv, v.allow_xlsx, s.code structure_code, s.name structure_name
                FROM import_structure_versions v
                JOIN import_structures s ON s.id=v.structure_id
                WHERE v.status='ACTIVE' AND s.is_active=1
                ORDER BY s.name, v.version_number DESC";
        return $this->pdo->query($sql)->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function activeVersion(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT v.*, s.code structure_code, s.name structure_name
             FROM import_structure_versions v
             JOIN import_structures s ON s.id=v.structure_id
             WHERE v.id=:id AND v.status='ACTIVE' AND s.is_active=1
             LIMIT 1"
        );
        $st->execute([':id'=>$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function fields(int $versionId): array
    {
        $st = $this->pdo->prepare(
            "SELECT *
             FROM import_structure_fields
             WHERE structure_version_id=:vid AND is_active=1
             ORDER BY sort_order,id"
        );
        $st->execute([':vid'=>$versionId]);
        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function queuesForVersion(int $versionId): array
    {
        $st = $this->pdo->prepare(
            "SELECT q.id,q.code,q.name
             FROM queue_structures qs
             JOIN work_queues q ON q.id=qs.queue_id
             WHERE qs.structure_version_id=:vid
               AND qs.is_active=1
               AND q.is_active=1
             ORDER BY qs.routing_priority,q.name"
        );
        $st->execute([':vid'=>$versionId]);
        return $st->fetchAll() ?: [];
    }

    public function duplicateCompletedHash(string $sha256): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM import_batches
             WHERE file_sha256=:sha
               AND status IN ('VALIDATED','VALIDATED_WITH_ERRORS','IMPORTING','COMPLETED')
             LIMIT 1"
        );
        $st->execute([':sha'=>$sha256]);
        return (bool)$st->fetchColumn();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO import_batches
             (batch_number,structure_version_id,queue_id,original_filename,stored_filename,
              file_sha256,file_size_bytes,mime_type,selected_sheet_name,header_signature,
              status,total_rows,valid_rows,invalid_rows,duplicate_rows,uploaded_by,
              validated_by,uploaded_at,validated_at,error_message,validation_summary_json)
             VALUES
             (:batch,:vid,:qid,:original,:stored,:sha,:size,:mime,:sheet,:signature,
              :status,:total,:valid,:invalid,:duplicates,:uid,:validated_by,
              NOW(6),NOW(6),:error,:summary)"
        );
        $st->execute([
            ':batch'=>$data['batch_number'],
            ':vid'=>$data['structure_version_id'],
            ':qid'=>$data['queue_id'],
            ':original'=>$data['original_filename'],
            ':stored'=>$data['stored_filename'],
            ':sha'=>$data['file_sha256'],
            ':size'=>$data['file_size_bytes'],
            ':mime'=>$data['mime_type'],
            ':sheet'=>$data['selected_sheet_name'],
            ':signature'=>$data['header_signature'],
            ':status'=>$data['status'],
            ':total'=>$data['total_rows'],
            ':valid'=>$data['valid_rows'],
            ':invalid'=>$data['invalid_rows'],
            ':duplicates'=>$data['duplicate_rows'],
            ':uid'=>$data['uploaded_by'],
            ':validated_by'=>$data['validated_by'],
            ':error'=>$data['error_message'],
            ':summary'=>json_encode($data['validation_summary'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @param list<array<string,mixed>> $rows */
    public function insertRows(int $batchId, array $rows): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO import_batch_rows
             (batch_id,source_row_number,external_key,raw_json,normalized_json,
              validation_status,validation_errors_json,created_at)
             VALUES
             (:bid,:rownum,:external,:raw,:normalized,:status,:errors,NOW(6))"
        );

        foreach ($rows as $row) {
            $st->execute([
                ':bid'=>$batchId,
                ':rownum'=>$row['source_row_number'],
                ':external'=>$row['external_key'],
                ':raw'=>json_encode($row['raw'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                ':normalized'=>json_encode($row['normalized'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                ':status'=>$row['validation_status'],
                ':errors'=>$row['errors'] ? json_encode($row['errors'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            ]);
        }
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT b.*,s.name structure_name,s.code structure_code,v.version_number,
                    q.name queue_name,u.full_name uploaded_by_name
             FROM import_batches b
             JOIN import_structure_versions v ON v.id=b.structure_version_id
             JOIN import_structures s ON s.id=v.structure_id
             LEFT JOIN work_queues q ON q.id=b.queue_id
             JOIN users u ON u.id=b.uploaded_by
             WHERE b.id=:id LIMIT 1"
        );
        $st->execute([':id'=>$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT *
             FROM import_batches
             WHERE id=:id
             FOR UPDATE"
        );
        $st->execute([':id'=>$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function rows(int $batchId, int $limit = 300): array
    {
        $limit = max(1, min(1000, $limit));
        $st = $this->pdo->prepare(
            "SELECT id,source_row_number,external_key,raw_json,normalized_json,
                    validation_status,validation_errors_json
             FROM import_batch_rows
             WHERE batch_id=:bid
             ORDER BY source_row_number
             LIMIT {$limit}"
        );
        $st->execute([':bid'=>$batchId]);
        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function validRowsForConfirmation(int $batchId): array
    {
        $st = $this->pdo->prepare(
            "SELECT id,source_row_number,external_key,normalized_json
             FROM import_batch_rows
             WHERE batch_id=:bid
               AND validation_status='VALID'
             ORDER BY source_row_number
             FOR UPDATE"
        );
        $st->execute([':bid'=>$batchId]);
        return $st->fetchAll() ?: [];
    }

    /** @param list<string> $errors */
    public function markRowDuplicate(int $rowId, array $errors): void
    {
        $st = $this->pdo->prepare(
            "UPDATE import_batch_rows
             SET validation_status='DUPLICATE',
                 validation_errors_json=:errors
             WHERE id=:id"
        );
        $st->execute([
            ':id'=>$rowId,
            ':errors'=>json_encode($errors, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function markImporting(int $batchId): void
    {
        $st = $this->pdo->prepare(
            "UPDATE import_batches
             SET status='IMPORTING', error_message=NULL
             WHERE id=:id"
        );
        $st->execute([':id'=>$batchId]);
    }

    public function markCompleted(
        int $batchId,
        int $confirmedBy,
        int $createdCases,
        int $duplicateRows
    ): void {
        $st = $this->pdo->prepare(
            "UPDATE import_batches
             SET status='COMPLETED',
                 duplicate_rows=:duplicates,
                 confirmed_by=:uid,
                 confirmed_at=NOW(6),
                 error_message=NULL
             WHERE id=:id"
        );
        $st->execute([
            ':id'=>$batchId,
            ':duplicates'=>$duplicateRows,
            ':uid'=>$confirmedBy,
        ]);
    }
}
