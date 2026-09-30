CREATE TABLE skills (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_skills_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_skills (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    skill_id BIGINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    removed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_skills (user_id, skill_id),
    KEY idx_user_skills_active (user_id, is_active),
    CONSTRAINT fk_user_skills_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_skills_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_skills_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE queue_skills (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue_id BIGINT UNSIGNED NOT NULL,
    skill_id BIGINT UNSIGNED NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_queue_skills (queue_id, skill_id),
    CONSTRAINT fk_queue_skills_queue FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_skills_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE agent_presence (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    status_code VARCHAR(100) NOT NULL,
    started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    ended_at DATETIME(6) NULL,
    last_heartbeat_at DATETIME(6) NULL,
    notes VARCHAR(500) NULL,
    source ENUM('USER','SUPERVISOR','SYSTEM') NOT NULL DEFAULT 'USER',
    set_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_agent_presence_current (user_id, ended_at, started_at),
    KEY idx_agent_presence_status (status_code, ended_at),
    CONSTRAINT fk_agent_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_agent_presence_set_by FOREIGN KEY (set_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_number VARCHAR(50) NOT NULL,
    external_key VARCHAR(180) NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    source_structure_version_id BIGINT UNSIGNED NULL,
    source_batch_id BIGINT UNSIGNED NULL,
    source_batch_row_id BIGINT UNSIGNED NULL,
    petition_type VARCHAR(255) NULL,
    regional VARCHAR(180) NULL,
    origin_channel VARCHAR(180) NULL,
    radicated_at DATETIME(6) NULL,
    current_state VARCHAR(100) NOT NULL DEFAULT 'PENDING_ASSIGNMENT',
    current_management_type_code VARCHAR(100) NULL,
    current_escalation_category_code VARCHAR(100) NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    assigned_at DATETIME(6) NULL,
    first_management_at DATETIME(6) NULL,
    last_management_at DATETIME(6) NULL,
    closed_at DATETIME(6) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_cases_case_number (case_number),
    UNIQUE KEY uq_cases_external_key (external_key),
    UNIQUE KEY uq_cases_source_batch_row (source_batch_row_id),
    KEY idx_cases_queue_state (queue_id, current_state, created_at),
    KEY idx_cases_agent_state (assigned_user_id, current_state, assigned_at),
    KEY idx_cases_radicated (radicated_at),
    CONSTRAINT fk_cases_queue FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE SET NULL,
    CONSTRAINT fk_cases_structure_version FOREIGN KEY (source_structure_version_id) REFERENCES import_structure_versions(id) ON DELETE SET NULL,
    CONSTRAINT fk_cases_source_batch FOREIGN KEY (source_batch_id) REFERENCES import_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_cases_source_batch_row FOREIGN KEY (source_batch_row_id) REFERENCES import_batch_rows(id) ON DELETE SET NULL,
    CONSTRAINT fk_cases_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_cases_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE case_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assignment_type ENUM('AUTO','MANUAL','REASSIGN') NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    ended_at DATETIME(6) NULL,
    end_reason VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_case_assignments_case (case_id, assigned_at),
    KEY idx_case_assignments_user_open (user_id, ended_at, assigned_at),
    CONSTRAINT fk_case_assignments_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_case_assignments_queue FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE SET NULL,
    CONSTRAINT fk_case_assignments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_case_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE case_managements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    management_type_code VARCHAR(100) NOT NULL,
    escalation_category_code VARCHAR(100) NULL,
    petition_type_selected VARCHAR(255) NULL,
    previous_petition_type VARCHAR(255) NULL,
    new_petition_type VARCHAR(255) NULL,
    observation TEXT NULL,
    support_path VARCHAR(500) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_case_managements_case_date (case_id, created_at),
    KEY idx_case_managements_actor_date (actor_user_id, created_at),
    KEY idx_case_managements_type (management_type_code, created_at),
    CONSTRAINT fk_case_managements_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_case_managements_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE case_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    from_state VARCHAR(100) NULL,
    to_state VARCHAR(100) NULL,
    details_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_case_events_case_date (case_id, created_at),
    KEY idx_case_events_type_date (event_type, created_at),
    CONSTRAINT fk_case_events_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_case_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
