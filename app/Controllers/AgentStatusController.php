<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Repositories\PresenceRepository;
use PDO;

final class AgentStatusController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'QUEUE_VIEW');

        $repo = new PresenceRepository($this->pdo);
        $staleSeconds = max(30, (int)($_ENV['AGENT_PRESENCE_STALE_SECONDS'] ?? 90));

        $agents = $repo->supervisorAgents($staleSeconds);
        $summary = $repo->supervisorSummary($staleSeconds);

        $view = dirname(__DIR__) . '/Views/agents/status.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function data(): void
    {
        Authorization::requirePermission($this->pdo, 'QUEUE_VIEW');

        $repo = new PresenceRepository($this->pdo);
        $staleSeconds = max(30, (int)($_ENV['AGENT_PRESENCE_STALE_SECONDS'] ?? 90));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'=>true,
            'agents'=>$repo->supervisorAgents($staleSeconds),
            'summary'=>$repo->supervisorSummary($staleSeconds),
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
}
