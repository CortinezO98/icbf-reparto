CREATE TABLE catalogs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_catalogs_code (code),
    CONSTRAINT fk_catalogs_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE catalog_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    catalog_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(120) NOT NULL,
    label VARCHAR(180) NOT NULL,
    value_text VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_catalog_items_catalog_code (catalog_id, code),
    KEY idx_catalog_items_active_order (catalog_id, is_active, sort_order),
    CONSTRAINT fk_catalog_items_catalog FOREIGN KEY (catalog_id) REFERENCES catalogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_structures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_import_structures_code (code),
    KEY idx_import_structures_active_name (is_active, name),
    CONSTRAINT fk_import_structures_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_structure_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    structure_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    status ENUM('DRAFT','ACTIVE','INACTIVE') NOT NULL DEFAULT 'DRAFT',
    target_sheet_mode ENUM('EXACT','REGEX','FIRST_MATCH') NOT NULL DEFAULT 'FIRST_MATCH',
    target_sheet_value VARCHAR(180) NULL,
    header_row INT UNSIGNED NOT NULL DEFAULT 1,
    data_start_row INT UNSIGNED NOT NULL DEFAULT 2,
    external_key_field_code VARCHAR(100) NOT NULL,
    allow_csv TINYINT(1) NOT NULL DEFAULT 1,
    allow_xlsx TINYINT(1) NOT NULL DEFAULT 1,
    header_signature CHAR(64) NULL,
    notes VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    activated_by BIGINT UNSIGNED NULL,
    activated_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_structure_version (structure_id, version_number),
    KEY idx_structure_versions_status (structure_id, status, version_number),
    CONSTRAINT fk_structure_versions_structure FOREIGN KEY (structure_id) REFERENCES import_structures(id) ON DELETE RESTRICT,
    CONSTRAINT fk_structure_versions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_structure_versions_activated_by FOREIGN KEY (activated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_structure_fields (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    structure_version_id BIGINT UNSIGNED NOT NULL,
    field_code VARCHAR(100) NOT NULL,
    display_name VARCHAR(180) NOT NULL,
    excel_header VARCHAR(180) NOT NULL,
    header_aliases_json JSON NULL,
    data_type ENUM('STRING','INTEGER','DECIMAL','DATE','DATETIME','BOOLEAN','CATALOG') NOT NULL DEFAULT 'STRING',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_external_key TINYINT(1) NOT NULL DEFAULT 0,
    is_reportable TINYINT(1) NOT NULL DEFAULT 0,
    max_length INT UNSIGNED NULL,
    validation_regex VARCHAR(500) NULL,
    catalog_id BIGINT UNSIGNED NULL,
    date_format VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_structure_field_code (structure_version_id, field_code),
    KEY idx_structure_fields_order (structure_version_id, is_active, sort_order),
    CONSTRAINT fk_structure_fields_version FOREIGN KEY (structure_version_id) REFERENCES import_structure_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_structure_fields_catalog FOREIGN KEY (catalog_id) REFERENCES catalogs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_queues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    default_capacity INT UNSIGNED NOT NULL DEFAULT 1,
    priority INT NOT NULL DEFAULT 100,
    assignment_strategy ENUM('FIFO_RELATIVE_LOAD') NOT NULL DEFAULT 'FIFO_RELATIVE_LOAD',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_work_queues_code (code),
    KEY idx_work_queues_active_priority (is_active, priority),
    CONSTRAINT fk_work_queues_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE queue_structures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue_id BIGINT UNSIGNED NOT NULL,
    structure_version_id BIGINT UNSIGNED NOT NULL,
    routing_priority INT NOT NULL DEFAULT 100,
    routing_rule_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_queue_structure_version (queue_id, structure_version_id),
    KEY idx_queue_structures_route (queue_id, is_active, routing_priority),
    CONSTRAINT fk_queue_structures_queue FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_structures_version FOREIGN KEY (structure_version_id) REFERENCES import_structure_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE queue_agents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    capacity_override INT UNSIGNED NULL,
    priority INT NOT NULL DEFAULT 100,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    removed_at DATETIME(6) NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_queue_agents (queue_id, user_id),
    KEY idx_queue_agents_enabled (queue_id, is_enabled, priority),
    KEY idx_queue_agents_user (user_id, is_enabled),
    CONSTRAINT fk_queue_agents_queue FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_agents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_queue_agents_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_number VARCHAR(50) NOT NULL,
    structure_version_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    status ENUM('UPLOADED','VALIDATING','VALIDATED','VALIDATED_WITH_ERRORS','IMPORTING','COMPLETED','FAILED','CANCELLED') NOT NULL DEFAULT 'UPLOADED',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    invalid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    duplicate_rows INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    validated_by BIGINT UNSIGNED NULL,
    confirmed_by BIGINT UNSIGNED NULL,
    uploaded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    validated_at DATETIME(6) NULL,
    confirmed_at DATETIME(6) NULL,
    error_message VARCHAR(1000) NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_import_batches_number (batch_number),
    KEY idx_import_batches_hash (file_sha256), KEY idx_import_batches_status_date (status, uploaded_at),
    CONSTRAINT fk_import_batches_version FOREIGN KEY (structure_version_id) REFERENCES import_structure_versions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_import_batches_queue FOREIGN KEY (queue_id) REFERENCES work_queues(id) ON DELETE SET NULL,
    CONSTRAINT fk_import_batches_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_import_batches_validated_by FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_import_batches_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_batch_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    external_key VARCHAR(180) NULL,
    raw_json JSON NOT NULL,
    normalized_json JSON NULL,
    validation_status ENUM('PENDING','VALID','INVALID','DUPLICATE') NOT NULL DEFAULT 'PENDING',
    validation_errors_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_import_batch_row (batch_id, source_row_number),
    KEY idx_import_batch_rows_status (batch_id, validation_status), KEY idx_import_batch_rows_external (external_key),
    CONSTRAINT fk_import_batch_rows_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
