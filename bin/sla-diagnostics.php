<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;

$pdo = Database::connection();

echo "=== POLITICA ANS ===", PHP_EOL;
foreach ($pdo->query(
    "SELECT code,name,target_minutes,green_until_minutes,
            yellow_until_minutes,red_from_minutes,
            no_management_alert_minutes,business_start,business_end,timezone
     FROM sla_policies
     WHERE is_active=1
     ORDER BY id"
) as $p) {
    echo $p['code'],
        ' | target=', $p['target_minutes'], 'm',
        ' | green<', $p['green_until_minutes'],
        ' | yellow<', $p['yellow_until_minutes'],
        ' | red>=', $p['red_from_minutes'],
        ' | no_management>=', $p['no_management_alert_minutes'],
        ' | ', $p['business_start'], '-', $p['business_end'],
        ' | ', $p['timezone'],
        PHP_EOL;
}

echo PHP_EOL, "=== CASOS ===", PHP_EOL;
foreach ($pdo->query(
    "SELECT id,case_number,current_state,sla_status,
            sla_elapsed_minutes,sla_due_at,sla_breached_at
     FROM cases
     ORDER BY id"
) as $c) {
    echo $c['id'], ' | ', $c['case_number'],
        ' | state=', $c['current_state'],
        ' | sla=', ($c['sla_status'] ?: 'SIN_EVALUAR'),
        ' | elapsed=', ($c['sla_elapsed_minutes'] ?? 0), 'm',
        ' | due=', ($c['sla_due_at'] ?: '—'),
        ' | breached=', ($c['sla_breached_at'] ?: '—'),
        PHP_EOL;
}

echo PHP_EOL, "=== ALERTAS ABIERTAS ===", PHP_EOL;
$count = 0;
foreach ($pdo->query(
    "SELECT a.id,a.case_id,a.alert_type,a.severity,a.title
     FROM case_alerts a
     WHERE a.resolved_at IS NULL
     ORDER BY a.id"
) as $a) {
    $count++;
    echo $a['id'], ' | case=', $a['case_id'],
        ' | ', $a['severity'],
        ' | ', $a['alert_type'],
        ' | ', $a['title'],
        PHP_EOL;
}

if ($count === 0) {
    echo "No hay alertas abiertas.", PHP_EOL;
}
