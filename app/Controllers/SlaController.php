<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Repositories\SlaRepository;
use App\Services\Sla\SlaService;
use PDO;

final class SlaController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'SLA_VIEW');

        $repo = new SlaRepository($this->pdo);

        // Refresh on view so the dashboard is useful even if the worker
        // has not run in the last few minutes.
        try {
            (new SlaService($repo))->evaluateOpenCases();
        } catch (\Throwable $e) {
            error_log('[SLA][DASHBOARD] ' . $e->getMessage());
        }

        $summary = $repo->summary();
        $alerts = $repo->openAlerts();
        $policy = $repo->activePolicy();

        $view = dirname(__DIR__) . '/Views/sla/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }
}
