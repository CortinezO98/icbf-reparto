INSERT INTO roles (code, name, description)
VALUES
    ('ADMIN', 'Administrador', 'Administración global del sistema'),
    ('SUPERVISOR', 'Supervisor', 'Operación, cargas, equipo y reportes'),
    ('AGENTE', 'Agente', 'Gestión de casos asignados')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_active = 1;

INSERT INTO permissions (code, module, description)
VALUES
    ('CASE_VIEW_OWN', 'CASES', 'Ver casos propios'),
    ('CASE_VIEW_TEAM', 'CASES', 'Ver casos del equipo'),
    ('CASE_VIEW_ALL', 'CASES', 'Ver todos los casos'),
    ('CASE_MANAGE_OWN', 'CASES', 'Gestionar casos propios'),
    ('CASE_REASSIGN', 'CASES', 'Reasignar casos'),
    ('IMPORT_UPLOAD', 'IMPORTS', 'Subir archivos de importación'),
    ('IMPORT_VALIDATE', 'IMPORTS', 'Validar cargas'),
    ('IMPORT_CONFIRM', 'IMPORTS', 'Confirmar importaciones'),
    ('QUEUE_VIEW', 'QUEUES', 'Ver colas'),
    ('QUEUE_MANAGE_AGENTS', 'QUEUES', 'Administrar agentes de cola'),
    ('QUEUE_ADMIN', 'QUEUES', 'Crear y configurar colas'),
    ('STRUCTURE_VIEW', 'STRUCTURES', 'Ver estructuras'),
    ('STRUCTURE_ADMIN', 'STRUCTURES', 'Crear y versionar estructuras'),
    ('REPORT_VIEW', 'REPORTS', 'Ver reportes'),
    ('REPORT_EXPORT', 'REPORTS', 'Exportar reportes'),
    ('USER_VIEW', 'USERS', 'Ver usuarios'),
    ('USER_CREATE', 'USERS', 'Crear usuarios'),
    ('USER_EDIT', 'USERS', 'Editar usuarios'),
    ('USER_ROLE_ADMIN', 'USERS', 'Administrar roles de usuarios'),
    ('SLA_VIEW', 'SLA', 'Ver configuración ANS'),
    ('SLA_ADMIN', 'SLA', 'Administrar políticas ANS'),
    ('AUDIT_VIEW', 'AUDIT', 'Ver auditoría global'),
    ('SYSTEM_ADMIN', 'SYSTEM', 'Administrar configuración global')
ON DUPLICATE KEY UPDATE
    module = VALUES(module),
    description = VALUES(description),
    is_active = 1;

-- ADMIN recibe todos los permisos
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code = 'ADMIN';

-- SUPERVISOR
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'CASE_VIEW_OWN',
    'CASE_VIEW_TEAM',
    'CASE_MANAGE_OWN',
    'CASE_REASSIGN',
    'IMPORT_UPLOAD',
    'IMPORT_VALIDATE',
    'IMPORT_CONFIRM',
    'QUEUE_VIEW',
    'QUEUE_MANAGE_AGENTS',
    'REPORT_VIEW',
    'REPORT_EXPORT',
    'USER_VIEW',
    'SLA_VIEW'
)
WHERE r.code = 'SUPERVISOR';

-- AGENTE
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'CASE_VIEW_OWN',
    'CASE_MANAGE_OWN'
)
WHERE r.code = 'AGENTE';
