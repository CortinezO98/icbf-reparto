INSERT INTO import_structures (code, name, description)
VALUES
 ('PETICIONES','Peticiones','Base operativa de peticiones'),
 ('ANEXOS','Anexos','Base operativa de anexos'),
 ('ANEXOS_200','Anexos 200','Base operativa de anexos 200'),
 ('IO','IO','Base operativa IO')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), is_active=1;

INSERT INTO work_queues (code, name, description, default_capacity, priority)
VALUES
 ('PETICIONES','Direccionamiento de peticiones','Cola inicial para peticiones',5,100),
 ('ANEXOS','Anexos','Cola inicial para anexos',5,100),
 ('ANEXOS_200','Anexos 200','Cola inicial para anexos 200',3,100)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), default_capacity=VALUES(default_capacity), is_active=1;

-- IO no se precarga con capacidad hasta resolver DEC-001: 3 vs 13.
