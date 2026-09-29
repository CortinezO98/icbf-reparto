ALTER TABLE import_batches
    ADD COLUMN selected_sheet_name VARCHAR(180) NULL AFTER mime_type,
    ADD COLUMN header_signature CHAR(64) NULL AFTER selected_sheet_name,
    ADD COLUMN validation_summary_json JSON NULL AFTER error_message;

CREATE INDEX idx_import_batches_hash_status
    ON import_batches(file_sha256, status);

CREATE INDEX idx_import_batch_rows_batch_external
    ON import_batch_rows(batch_id, external_key);
