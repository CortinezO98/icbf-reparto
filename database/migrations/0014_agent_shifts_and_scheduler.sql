CREATE TABLE IF NOT EXISTS work_shifts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(180) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_work_shifts_code (code),
    KEY idx_work_shifts_active_time (is_active,start_time,end_time),
    CONSTRAINT fk_work_shifts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_shift_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NOT NULL,
    schedule_date DATE NULL,
    weekday TINYINT UNSIGNED NULL,
    valid_from DATE NULL,
    valid_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_agent_shift_current (user_id,queue_id,is_active,schedule_date,weekday),
    KEY idx_agent_shift_queue (queue_id,is_active,weekday,schedule_date),
    KEY idx_agent_shift_shift (shift_id,is_active),
    CONSTRAINT fk_agent_shift_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_shift_queue
        FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_shift_shift
        FOREIGN KEY (shift_id) REFERENCES work_shifts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_shift_assigned_by
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_agent_shift_pattern
        CHECK (
            (schedule_date IS NOT NULL AND weekday IS NULL)
            OR
            (schedule_date IS NULL AND weekday IS NOT NULL)
        ),
    CONSTRAINT chk_agent_shift_weekday
        CHECK (weekday IS NULL OR weekday BETWEEN 1 AND 7),
    CONSTRAINT chk_agent_shift_dates
        CHECK (valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_shift_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    event_type ENUM('SHIFT_END') NOT NULL,
    processed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    released_cases INT UNSIGNED NOT NULL DEFAULT 0,
    pending_cases INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_agent_shift_run (schedule_id,shift_date,event_type),
    KEY idx_agent_shift_runs_date (shift_date,event_type),
    CONSTRAINT fk_agent_shift_runs_schedule
        FOREIGN KEY (schedule_id) REFERENCES agent_shift_schedules(id) ON DELETE CASCADE,
    CONSTRAINT fk_agent_shift_runs_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_shift_runs_queue
        FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (code,module,description)
VALUES
    ('SHIFT_VIEW','SHIFTS','Ver turnos y cronograma operativo'),
    ('SHIFT_MANAGE','SHIFTS','Administrar turnos y cronograma operativo')
ON DUPLICATE KEY UPDATE
    module=VALUES(module),
    description=VALUES(description),
    is_active=1;

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.code IN ('SHIFT_VIEW','SHIFT_MANAGE')
WHERE r.code='ADMIN';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.code IN ('SHIFT_VIEW','SHIFT_MANAGE')
WHERE r.code='SUPERVISOR';
