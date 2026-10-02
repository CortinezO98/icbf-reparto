-- Índices de apoyo para la reportería operativa.
-- La migración se ejecuta una sola vez mediante el migrador del proyecto.

CREATE INDEX idx_cases_created_at
    ON cases (created_at);

CREATE INDEX idx_cases_regional_petition_created
    ON cases (regional, petition_type, created_at);

CREATE INDEX idx_cases_management_type_created
    ON cases (current_management_type_code, created_at);

CREATE INDEX idx_case_managements_created_type_case
    ON case_managements (created_at, management_type_code, case_id);

CREATE INDEX idx_case_assignments_assigned_type
    ON case_assignments (assigned_at, assignment_type, case_id);

CREATE INDEX idx_case_events_type_created_case
    ON case_events (event_type, created_at, case_id);
