-- Configuración reproducible de PETICIONES v1 a partir de la estructura operativa revisada.
-- No incluye regional/canal porque no están presentes en la base operativa revisada.
-- Si el archivo oficial cambia, crear una nueva versión; no alterar esta versión histórica.

SET @structure_id := (
    SELECT id FROM import_structures WHERE code='PETICIONES' LIMIT 1
);

SET @queue_id := (
    SELECT id FROM work_queues WHERE code='PETICIONES' LIMIT 1
);

INSERT INTO import_structure_versions
(
    structure_id,
    version_number,
    status,
    target_sheet_mode,
    target_sheet_value,
    header_row,
    data_start_row,
    external_key_field_code,
    allow_csv,
    allow_xlsx,
    notes,
    created_at,
    updated_at
)
SELECT
    @structure_id,
    1,
    'ACTIVE',
    'EXACT',
    'Asignacion Agente',
    1,
    2,
    'numero_peticion',
    1,
    1,
    'Versión inicial reproducible para la base operativa de Peticiones.',
    NOW(6),
    NOW(6)
WHERE @structure_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM import_structure_versions
      WHERE structure_id=@structure_id AND version_number=1
  );

UPDATE import_structure_versions
SET
    status='ACTIVE',
    target_sheet_mode='EXACT',
    target_sheet_value='Asignacion Agente',
    header_row=1,
    data_start_row=2,
    external_key_field_code='numero_peticion',
    allow_csv=1,
    allow_xlsx=1,
    notes='Versión inicial reproducible para la base operativa de Peticiones.'
WHERE structure_id=@structure_id
  AND version_number=1;

SET @version_id := (
    SELECT id
    FROM import_structure_versions
    WHERE structure_id=@structure_id AND version_number=1
    LIMIT 1
);

INSERT INTO import_structure_fields
(
    structure_version_id, field_code, display_name, excel_header,
    header_aliases_json, data_type, is_required, is_external_key,
    is_reportable, max_length, validation_regex, catalog_id,
    date_format, sort_order, is_active
)
VALUES
(@version_id,'numero_peticion','Número petición','Número petición',
 JSON_ARRAY('Número de Petición','Numero Peticion','Numero petición'),
 'INTEGER',1,1,1,40,NULL,NULL,NULL,10,1),

(@version_id,'fecha_peticion','Fecha de petición','Fecha de petición',
 JSON_ARRAY('Fecha peticion'),
 'DATE',1,0,1,NULL,NULL,NULL,'d/m/Y',20,1),

(@version_id,'hora_peticion','Hora de petición','Hora de petición',
 JSON_ARRAY('Hora peticion'),
 'TIME',1,0,1,NULL,NULL,NULL,NULL,30,1),

(@version_id,'tipo_peticion','Tipo petición','Tipo petición',
 JSON_ARRAY('Tipo de petición','Tipo peticion'),
 'STRING',1,0,1,255,NULL,NULL,NULL,40,1),

(@version_id,'estado_peticion','Estado petición','Estado petición',
 JSON_ARRAY('Estado de petición','Estado peticion'),
 'STRING',1,0,1,180,NULL,NULL,NULL,50,1),

(@version_id,'agente_origen','Agente','Agente',
 NULL,
 'STRING',0,0,1,180,NULL,NULL,NULL,60,1),

(@version_id,'fecha_reporte','Fecha del reporte','Fecha del reporte',
 NULL,
 'DATE',0,0,1,NULL,NULL,NULL,'d/m/Y',70,1),

(@version_id,'hora_reporte','Hora del reporte','Hora del reporte',
 JSON_ARRAY('Hora de reporte'),
 'TIME',0,0,1,NULL,NULL,NULL,NULL,80,1),

(@version_id,'indicador','Indicador','Indicador',
 NULL,
 'DECIMAL',0,0,1,NULL,NULL,NULL,NULL,90,1),

(@version_id,'hora_vencimiento','Hora Vencimiento','Hora Vencimiento',
 JSON_ARRAY('Hora vencimiento'),
 'TIME',0,0,1,NULL,NULL,NULL,NULL,100,1)
ON DUPLICATE KEY UPDATE
    display_name=VALUES(display_name),
    excel_header=VALUES(excel_header),
    header_aliases_json=VALUES(header_aliases_json),
    data_type=VALUES(data_type),
    is_required=VALUES(is_required),
    is_external_key=VALUES(is_external_key),
    is_reportable=VALUES(is_reportable),
    max_length=VALUES(max_length),
    validation_regex=VALUES(validation_regex),
    catalog_id=VALUES(catalog_id),
    date_format=VALUES(date_format),
    sort_order=VALUES(sort_order),
    is_active=1;

INSERT INTO queue_structures
(
    queue_id,
    structure_version_id,
    routing_priority,
    routing_rule_json,
    is_active,
    created_at,
    updated_at
)
SELECT
    @queue_id,
    @version_id,
    100,
    NULL,
    1,
    NOW(6),
    NOW(6)
WHERE @queue_id IS NOT NULL
  AND @version_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    routing_priority=VALUES(routing_priority),
    is_active=1,
    updated_at=NOW(6);
