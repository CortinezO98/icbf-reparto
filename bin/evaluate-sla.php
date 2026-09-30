<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;
use App\Repositories\SlaRepository;
use App\Services\Sla\SlaService;

$pdo = Database::connection();
$service = new SlaService(new SlaRepository($pdo));
$result = $service->evaluateOpenCases();

echo 'Casos evaluados: ', $result['processed'], PHP_EOL;
echo 'Alertas actualizadas: ', $result['alerts_touched'], PHP_EOL;
