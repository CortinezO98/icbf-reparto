ALTER TABLE import_structure_fields
    MODIFY COLUMN data_type ENUM(
        'STRING',
        'INTEGER',
        'DECIMAL',
        'DATE',
        'TIME',
        'DATETIME',
        'BOOLEAN',
        'CATALOG'
    ) NOT NULL DEFAULT 'STRING';
