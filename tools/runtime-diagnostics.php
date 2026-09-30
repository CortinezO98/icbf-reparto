<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;

$pdo = Database::connection();

echo "=== ESQUEMA RUNTIME ===\n";

$checks = [
    ['users', 'last_login_at', false],
    ['users', 'last_assigned_at', true],
    ['import_batch_rows', 'normalized_json', true],
    ['import_batch_rows', 'normalized_data_json', false],
];

foreach ($checks as [$table, $column, $expected]) {
    $st = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=:table
           AND COLUMN_NAME=:column"
    );
    $st->execute([':table'=>$table, ':column'=>$column]);
    $exists = (int)$st->fetchColumn() > 0;

    echo $table, '.', $column, ' => ', $exists ? 'EXISTE' : 'NO EXISTE',
        ' | esperado=', $expected ? 'EXISTE' : 'NO EXISTE',
        ' | ', $exists === $expected ? 'OK' : 'REVISAR',
        PHP_EOL;
}

echo PHP_EOL, "=== RUTAS ===\n";
$routes = file_get_contents(dirname(__DIR__) . '/routes/web.php') ?: '';

foreach ([
    "use App\\Controllers\\CasesController;",
    "/admin/users",
    "/admin/users/import",
    "/cases",
    "/cases/{id}",
    "/cases/{id}/manage",
] as $needle) {
    echo $needle, ' => ', str_contains($routes, $needle) ? 'OK' : 'FALTA', PHP_EOL;
}

echo PHP_EOL, "=== CONTEOS ===\n";
foreach ([
    'users'=>'SELECT COUNT(*) FROM users',
    'cases'=>'SELECT COUNT(*) FROM cases',
    'pending_cases'=>"SELECT COUNT(*) FROM cases WHERE current_state='PENDING_ASSIGNMENT'",
    'agents'=>"SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code='AGENTE'",
] as $label=>$sql) {
    echo $label, ' = ', (int)$pdo->query($sql)->fetchColumn(), PHP_EOL;
}
