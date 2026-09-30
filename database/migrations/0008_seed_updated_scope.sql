INSERT INTO work_queues (code, name, description, default_capacity, priority, is_active)
VALUES
    ('IO','IO','Cola operativa de IO',13,100,1)
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    description=VALUES(description),
    default_capacity=13,
    is_active=1;

UPDATE work_queues SET default_capacity=5, is_active=1 WHERE code='PETICIONES';
UPDATE work_queues SET default_capacity=5, is_active=1 WHERE code='ANEXOS';
UPDATE work_queues SET default_capacity=3, is_active=1 WHERE code='ANEXOS_200';
UPDATE work_queues SET default_capacity=13, is_active=1 WHERE code='IO';

INSERT INTO skills (code,name,description)
VALUES
    ('PETICIONES','Direccionamiento de peticiones','Habilidad para gestionar la cola de peticiones'),
    ('ANEXOS','Anexos','Habilidad para gestionar anexos'),
    ('ANEXOS_200','Anexos 200','Habilidad para gestionar anexos 200'),
    ('IO','IO','Habilidad para gestionar IO')
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    description=VALUES(description),
    is_active=1;

INSERT INTO catalogs (code,name,description)
VALUES
    ('AGENT_PRESENCE_STATUS','Estados de agente','Estados operativos disponibles para agentes'),
    ('CASE_MANAGEMENT_TYPE','Tipos de gestión','Tipificaciones de gestión de casos'),
    ('ESCALATION_CATEGORY','Categorías de escalamiento','Subcategorías para casos escalados'),
    ('PETITION_TYPE','Tipos de petición','Catálogo funcional de tipos de petición')
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    description=VALUES(description),
    is_active=1;

INSERT INTO catalog_items (catalog_id,code,label,sort_order)
SELECT c.id,x.code,x.label,x.sort_order
FROM catalogs c
JOIN (
    SELECT 'AVAILABLE' code,'Disponible' label,10 sort_order UNION ALL
    SELECT 'TRAINING','En capacitación',20 UNION ALL
    SELECT 'MEETING','Reunión',30 UNION ALL
    SELECT 'BREAK','Break',40 UNION ALL
    SELECT 'ASYNC_ACTIVITY','Actividades asincrónicas',50 UNION ALL
    SELECT 'BATHROOM','Baño',60 UNION ALL
    SELECT 'TECH_FAILURE','Falla tecnológica',70 UNION ALL
    SELECT 'FEEDBACK','Retroalimentación',80 UNION ALL
    SELECT 'ACTIVE_BREAK','Pausas Activas',90
) x
WHERE c.code='AGENT_PRESENCE_STATUS'
ON DUPLICATE KEY UPDATE
    label=VALUES(label),
    sort_order=VALUES(sort_order),
    is_active=1;

INSERT INTO catalog_items (catalog_id,code,label,sort_order)
SELECT c.id,x.code,x.label,x.sort_order
FROM catalogs c
JOIN (
    SELECT 'CLOSED' code,'CERRADO' label,10 sort_order UNION ALL
    SELECT 'DIRECTED','DIRECCIONADO',20 UNION ALL
    SELECT 'ESCALATED','ESCALADO',30 UNION ALL
    SELECT 'PETITION_TYPE_CHANGE','Cambio de tipo de petición',40 UNION ALL
    SELECT 'POLICE_REPORT','Reporte a policía',50
) x
WHERE c.code='CASE_MANAGEMENT_TYPE'
ON DUPLICATE KEY UPDATE
    label=VALUES(label),
    sort_order=VALUES(sort_order),
    is_active=1;

INSERT INTO catalog_items (catalog_id,code,label,sort_order)
SELECT c.id,x.code,x.label,x.sort_order
FROM catalogs c
JOIN (
    SELECT 'LAWYERS' code,'ABOGADOS' label,10 sort_order UNION ALL
    SELECT 'PSYCHOSOCIAL','PSICOSOCIAL',20 UNION ALL
    SELECT 'SEXUAL_VIOLENCE','VIOLENCIA SEXUAL',30 UNION ALL
    SELECT 'STAFF','STAFF',40 UNION ALL
    SELECT 'QUALITY_LEADER','LIDER DE CALIDAD',50 UNION ALL
    SELECT 'NATIONAL_COORDINATOR','COORDINADOR NACIONAL',60 UNION ALL
    SELECT 'DRC','DRC',70 UNION ALL
    SELECT 'COGESTOR','COGESTOR',80 UNION ALL
    SELECT 'AGENT','AGENTE',90 UNION ALL
    SELECT 'EXTENSION','AMPLIACIÓN',100
) x
WHERE c.code='ESCALATION_CATEGORY'
ON DUPLICATE KEY UPDATE
    label=VALUES(label),
    sort_order=VALUES(sort_order),
    is_active=1;

INSERT INTO catalog_items (catalog_id,code,label,sort_order)
SELECT c.id,x.code,x.label,x.sort_order
FROM catalogs c
JOIN (
    SELECT 'ANEXO' code,'ANEXO' label,10 sort_order UNION ALL
    SELECT 'ANEXO_COMISARIA_FAMILIA','ANEXO COMISARÍA DE FAMILIA',20 UNION ALL
    SELECT 'ATENCION_CRISIS','ATENCIÓN EN CRISIS',30 UNION ALL
    SELECT 'DP_CICLOS_VIDA_NUTRICION','DP - ATENCIÓN POR CICLOS DE VIDA Y NUTRICIÓN',40 UNION ALL
    SELECT 'DP_INFO_ORIENTACION','DP - INFORMACIÓN Y ORIENTACIÓN',50 UNION ALL
    SELECT 'DP_INFO_ORIENTACION_TRAMITE','DP - INFORMACIÓN Y ORIENTACIÓN CON TRÁMITE',60 UNION ALL
    SELECT 'DP_QUEJAS','DP - QUEJAS',70 UNION ALL
    SELECT 'DP_RECLAMOS','DP - RECLAMOS',80 UNION ALL
    SELECT 'DP_SUGERENCIA','DP - SUGERENCIA',90 UNION ALL
    SELECT 'INOBSERVANCIA_DERECHOS','INOBSERVANCIA DE DERECHOS',100 UNION ALL
    SELECT 'OBSERVACION','OBSERVACIÓN',110 UNION ALL
    SELECT 'ORIENTACION_DERECHO_FAMILIA','ORIENTACIÓN EN DERECHO DE FAMILIA',120 UNION ALL
    SELECT 'PRESENCIA_CONVIVENCIA_VINCULOS','PRESENCIA PARA LA CONVIVENCIA Y EL FORTALECIMIENTO DE VÍNCULOS FAMILIARES Y COMUNITARIOS',130 UNION ALL
    SELECT 'SRD','SOLICITUD DE RESTABLECIMIENTO DE DERECHOS (SRD)',140 UNION ALL
    SELECT 'BUSQUEDA_ORIGENES','TRÁMITE BÚSQUEDA DE ORÍGENES',150 UNION ALL
    SELECT 'TAE','TRÁMITE DE ATENCIÓN EXTRAPROCESAL (TAE)',160
) x
WHERE c.code='PETITION_TYPE'
ON DUPLICATE KEY UPDATE
    label=VALUES(label),
    sort_order=VALUES(sort_order),
    is_active=1;

INSERT IGNORE INTO queue_skills (queue_id,skill_id,is_required)
SELECT q.id,s.id,1
FROM work_queues q
JOIN skills s ON s.code=q.code
WHERE q.code IN ('PETICIONES','ANEXOS','ANEXOS_200','IO');
