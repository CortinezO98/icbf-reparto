CREATE TABLE IF NOT EXISTS sla_policies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    target_minutes INT UNSIGNED NOT NULL DEFAULT 360,
    green_until_minutes INT UNSIGNED NOT NULL DEFAULT 120,
    yellow_until_minutes INT UNSIGNED NOT NULL DEFAULT 300,
    red_from_minutes INT UNSIGNED NOT NULL DEFAULT 300,
    no_management_alert_minutes INT UNSIGNED NOT NULL DEFAULT 240,
    business_start TIME NOT NULL DEFAULT '08:00:00',
    business_end TIME NOT NULL DEFAULT '17:00:00',
    timezone VARCHAR(100) NOT NULL DEFAULT 'America/Bogota',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_sla_policies_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sla_holidays (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    label VARCHAR(180) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_sla_holidays_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cases
    ADD COLUMN IF NOT EXISTS sla_policy_id BIGINT UNSIGNED NULL AFTER closed_at,
    ADD COLUMN IF NOT EXISTS sla_due_at DATETIME(6) NULL AFTER sla_policy_id,
    ADD COLUMN IF NOT EXISTS sla_status VARCHAR(30) NULL AFTER sla_due_at,
    ADD COLUMN IF NOT EXISTS sla_elapsed_minutes INT UNSIGNED NULL AFTER sla_status,
    ADD COLUMN IF NOT EXISTS sla_last_evaluated_at DATETIME(6) NULL AFTER sla_elapsed_minutes,
    ADD COLUMN IF NOT EXISTS sla_breached_at DATETIME(6) NULL AFTER sla_last_evaluated_at;

SET @fk_cases_sla_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cases'
      AND CONSTRAINT_NAME = 'fk_cases_sla_policy'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_fk_cases_sla := IF(
    @fk_cases_sla_exists = 0,
    'ALTER TABLE cases ADD CONSTRAINT fk_cases_sla_policy FOREIGN KEY (sla_policy_id) REFERENCES sla_policies(id) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE stmt_fk_cases_sla FROM @sql_fk_cases_sla;
EXECUTE stmt_fk_cases_sla;
DEALLOCATE PREPARE stmt_fk_cases_sla;

CREATE INDEX IF NOT EXISTS idx_cases_sla_status
    ON cases (sla_status, sla_due_at, current_state);

CREATE TABLE IF NOT EXISTS case_alerts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id BIGINT UNSIGNED NOT NULL,
    alert_type ENUM('NO_MANAGEMENT','NEAR_SLA','SLA_BREACHED') NOT NULL,
    severity ENUM('INFO','WARNING','CRITICAL') NOT NULL,
    title VARCHAR(180) NOT NULL,
    message VARCHAR(500) NOT NULL,
    dedupe_key VARCHAR(180) NOT NULL,
    opened_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    last_seen_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    resolved_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_case_alerts_dedupe (dedupe_key),
    KEY idx_case_alerts_open (resolved_at, severity, opened_at),
    KEY idx_case_alerts_case (case_id, alert_type, resolved_at),
    CONSTRAINT fk_case_alerts_case
        FOREIGN KEY (case_id) REFERENCES cases(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sla_policies (
    code,
    name,
    description,
    target_minutes,
    green_until_minutes,
    yellow_until_minutes,
    red_from_minutes,
    no_management_alert_minutes,
    business_start,
    business_end,
    timezone,
    is_active
)
VALUES (
    'ICBF_6H',
    'ANS ICBF 6 horas hábiles',
    'Lunes a viernes de 08:00 a 17:00. Verde <2h; amarillo 2h a <5h; rojo 5h a <6h; vencido desde 6h.',
    360,
    120,
    300,
    300,
    240,
    '08:00:00',
    '17:00:00',
    'America/Bogota',
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    target_minutes = VALUES(target_minutes),
    green_until_minutes = VALUES(green_until_minutes),
    yellow_until_minutes = VALUES(yellow_until_minutes),
    red_from_minutes = VALUES(red_from_minutes),
    no_management_alert_minutes = VALUES(no_management_alert_minutes),
    business_start = VALUES(business_start),
    business_end = VALUES(business_end),
    timezone = VALUES(timezone),
    is_active = 1;

UPDATE cases c
JOIN sla_policies p ON p.code='ICBF_6H' AND p.is_active=1
SET c.sla_policy_id = p.id
WHERE c.sla_policy_id IS NULL;
