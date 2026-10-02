<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;

$pdo = Database::connection();

echo "=== CASOS PENDIENTES POR COLA ===" . PHP_EOL;

$sql = "SELECT q.id,q.code,q.name,COUNT(c.id) pending
        FROM work_queues q
        LEFT JOIN cases c
          ON c.queue_id=q.id
         AND c.current_state='PENDING_ASSIGNMENT'
         AND c.assigned_user_id IS NULL
        WHERE q.is_active=1
        GROUP BY q.id,q.code,q.name
        ORDER BY q.code";

foreach ($pdo->query($sql) as $row) {
    echo $row['id'] . ' | '
        . $row['code'] . ' | pendientes='
        . $row['pending'] . PHP_EOL;
}

echo PHP_EOL . "=== AGENTES CONFIGURADOS ===" . PHP_EOL;

$sql = "SELECT
            q.code queue_code,
            u.id user_id,
            u.full_name,
            qa.is_enabled,
            COALESCE(qa.capacity_override,q.default_capacity) capacity,
            COALESCE(ap.status_code,'SIN_PRESENCIA') presence,
            CASE WHEN EXISTS (
                SELECT 1
                FROM agent_shift_schedules ass
                JOIN work_shifts ws ON ws.id=ass.shift_id
                WHERE ass.user_id=u.id
                  AND ass.queue_id=q.id
                  AND ass.is_active=1
                  AND ws.is_active=1
                  AND (
                      ass.schedule_date=CURDATE()
                      OR (
                          ass.schedule_date IS NULL
                          AND ass.weekday=WEEKDAY(CURDATE())+1
                      )
                  )
                  AND (ass.valid_from IS NULL OR ass.valid_from<=CURDATE())
                  AND (ass.valid_to IS NULL OR ass.valid_to>=CURDATE())
                  AND ws.start_time<=TIME(NOW())
                  AND ws.end_time>TIME(NOW())
            ) THEN 'ON_SHIFT' ELSE 'OFF_SHIFT' END shift_status,
            (
                SELECT COUNT(*)
                FROM cases c
                WHERE c.queue_id=q.id
                  AND c.assigned_user_id=u.id
                  AND c.closed_at IS NULL
                  AND c.current_state<>'PENDING_ASSIGNMENT'
            ) open_cases
        FROM queue_agents qa
        JOIN work_queues q ON q.id=qa.queue_id
        JOIN users u ON u.id=qa.user_id
        LEFT JOIN agent_presence ap
          ON ap.user_id=u.id
         AND ap.ended_at IS NULL
        WHERE qa.removed_at IS NULL
        ORDER BY q.code,u.full_name";

$rows = $pdo->query($sql)->fetchAll() ?: [];

if ($rows === []) {
    echo "No hay agentes asociados a colas." . PHP_EOL;
    exit(0);
}

foreach ($rows as $row) {
    echo $row['queue_code']
        . ' | user=' . $row['user_id']
        . ' | ' . $row['full_name']
        . ' | enabled=' . $row['is_enabled']
        . ' | presence=' . $row['presence']
        . ' | shift=' . $row['shift_status']
        . ' | open=' . $row['open_cases']
        . '/' . $row['capacity']
        . PHP_EOL;
}