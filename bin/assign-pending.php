<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;
use App\Repositories\AssignmentRepository;
use App\Services\Assignment\AssignmentEngine;

$queueId = null;
$max = 500;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--queue=')) {
        $value = substr($argument, 8);
        $queueId = $value !== '' ? (int)$value : null;
    }

    if (str_starts_with($argument, '--max=')) {
        $max = max(1, (int)substr($argument, 6));
    }
}

$pdo = Database::connection();

$result = (new AssignmentEngine(
    $pdo,
    new AssignmentRepository($pdo)
))->run($queueId, $max);

echo 'Asignados: ' . $result['assigned'] . PHP_EOL;
echo 'Sin agente elegible: ' . $result['no_agent'] . PHP_EOL;
echo 'Iteraciones: ' . $result['iterations'] . PHP_EOL;
