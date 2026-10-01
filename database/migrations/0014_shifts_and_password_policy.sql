ALTER TABLE users
    ADD COLUMN password_must_change TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;

CREATE TABLE agent_shifts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    starts_at DATETIME(6) NOT NULL,
    ends_at DATETIME(6) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_agent_shifts_user_time (user_id, starts_at, ends_at, is_active),
    KEY idx_agent_shifts_queue_time (queue_id, starts_at, ends_at, is_active),
    CONSTRAINT fk_agent_shifts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_agent_shifts_queue
        FOREIGN KEY (queue_id) REFERENCES work_queues(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_agent_shifts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT chk_agent_shifts_range CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
