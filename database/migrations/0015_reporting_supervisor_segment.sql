ALTER TABLE users
    ADD COLUMN supervisor_user_id BIGINT UNSIGNED NULL AFTER assign_enabled,
    ADD KEY idx_users_supervisor (supervisor_user_id,is_active),
    ADD CONSTRAINT fk_users_supervisor
        FOREIGN KEY (supervisor_user_id) REFERENCES users(id)
        ON DELETE SET NULL;

ALTER TABLE cases
    ADD COLUMN segment VARCHAR(180) NULL AFTER regional,
    ADD KEY idx_cases_segment (segment);

INSERT INTO import_structure_fields
(
    structure_version_id, field_code, display_name, excel_header,
    header_aliases_json, data_type, is_required, is_external_key,
    is_reportable, max_length, validation_regex, catalog_id,
    date_format, sort_order, is_active
)
SELECT
    v.id,
    'segmento',
    'Segmento',
    'Segmento',
    JSON_ARRAY('segmento','Segmento'),
    'STRING',
    0,
    0,
    1,
    180,
    NULL,
    NULL,
    NULL,
    55,
    1
FROM import_structure_versions v
JOIN import_structures s ON s.id=v.structure_id
WHERE v.status='ACTIVE'
ON DUPLICATE KEY UPDATE
    display_name=VALUES(display_name),
    excel_header=VALUES(excel_header),
    header_aliases_json=VALUES(header_aliases_json),
    data_type=VALUES(data_type),
    is_required=VALUES(is_required),
    is_reportable=VALUES(is_reportable),
    max_length=VALUES(max_length),
    sort_order=VALUES(sort_order),
    is_active=1;
