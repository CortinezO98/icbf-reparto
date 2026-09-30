<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;

$pdo = Database::connection();

echo "=== COLAS ACTIVAS ===", PHP_EOL;
foreach ($pdo->query(
    "SELECT id,code,name,default_capacity
     FROM work_queues
     WHERE is_active=1
     ORDER BY priority,code"
) as $row) {
    echo $row['id'], ' | ', $row['code'], ' | ', $row['name'],
        ' | cap=', $row['default_capacity'], PHP_EOL;
}

echo PHP_EOL, "=== AGENTES Y COLAS ===", PHP_EOL;
$sql = "
    SELECT
        u.id,
        u.username,
        u.full_name,
        u.is_active,
        u.assign_enabled,
        GROUP_CONCAT(DISTINCT q.code ORDER BY q.priority,q.code SEPARATOR ',') queue_codes
    FROM users u
    JOIN user_roles ur ON ur.user_id=u.id
    JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE'
    LEFT JOIN queue_agents qa
      ON qa.user_id=u.id
     AND qa.is_enabled=1
     AND qa.removed_at IS NULL
    LEFT JOIN work_queues q ON q.id=qa.queue_id
    GROUP BY u.id,u.username,u.full_name,u.is_active,u.assign_enabled
    ORDER BY u.id
";

$rows = $pdo->query($sql)->fetchAll();

if ($rows === []) {
    echo "No hay agentes creados.", PHP_EOL;
    exit;
}

foreach ($rows as $row) {
    echo $row['id'], ' | ', $row['username'], ' | ', $row['full_name'],
        ' | active=', $row['is_active'],
        ' | assign=', $row['assign_enabled'],
        ' | queues=', ($row['queue_codes'] ?: 'NINGUNA'),
        PHP_EOL;
}
