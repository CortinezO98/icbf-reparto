<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Repositories\AssignmentRepository;
use App\Repositories\AuditRepository;
use App\Repositories\PresenceRepository;
use App\Services\Assignment\AssignmentEngine;
use PDO;

final class AgentPresenceController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function current(): void
    {
        $uid = $this->requireAgent();
        $repo = new PresenceRepository($this->pdo);

        $presence = $repo->heartbeatWithStaleProtection(
            $uid,
            $this->staleSeconds()
        );

        $this->json([
            'ok'=>true,
            'presence'=>$presence,
            'statuses'=>$repo->selectableStatuses(),
            'heartbeat_seconds'=>$this->heartbeatSeconds(),
        ]);
    }

    public function update(): void
    {
        $uid = $this->requireAgent();
        Csrf::validate($_POST['_csrf'] ?? null);

        $status = strtoupper(trim((string)($_POST['status_code'] ?? '')));

        try {
            $repo = new PresenceRepository($this->pdo);
            $previousPresence = $repo->currentForUser($uid);
            $wasAvailable = $previousPresence !== null
                && (string)$previousPresence['status_code'] === 'AVAILABLE'
                && !empty($previousPresence['last_heartbeat_at']);

            $repo->setSelectableStatus($uid, $status, $uid, 'USER');

            $assignment = [
                'assigned'=>0,
                'no_agent'=>0,
                'iterations'=>0,
            ];

            // Al pasar a AVAILABLE se intenta repartir inmediatamente los
            // casos pendientes. El motor vuelve a validar todos los criterios
            // de elegibilidad y capacidad dentro de sus transacciones.
            if ($status === 'AVAILABLE') {
                try {
                    $assignment = (new AssignmentEngine(
                        $this->pdo,
                        new AssignmentRepository($this->pdo)
                    ))->run(null, 500);
                } catch (\Throwable $assignmentError) {
                    error_log('[AgentPresenceController::update][ASSIGNMENT] ' . $assignmentError->getMessage());
                }
            }

            (new AuditRepository($this->pdo))->log(
                $uid,
                'AGENT_PRESENCE_CHANGED',
                'USER',
                (string)$uid,
                [
                    'status_code'=>$status,
                    'previous_status'=>$previousPresence['status_code'] ?? null,
                    'assigned_after_available'=>$assignment['assigned'],
                ]
            );

            $this->json([
                'ok'=>true,
                'presence'=>$repo->currentForUser($uid),
                'assignment'=>$assignment,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok'=>false,'message'=>$e->getMessage()], 422);
        }
    }

    public function heartbeat(): void
    {
        $uid = $this->requireAgent();
        Csrf::validate($_POST['_csrf'] ?? null);

        $presence = (new PresenceRepository($this->pdo))
            ->heartbeatWithStaleProtection($uid, $this->staleSeconds());

        $this->json([
            'ok'=>true,
            'presence'=>$presence,
        ]);
    }

    private function requireAgent(): int
    {
        Auth::requireLogin();
        $uid = (int)Auth::id();

        if (!in_array('AGENTE', Authorization::roles($this->pdo, $uid), true)) {
            $this->json(['ok'=>false,'message'=>'Solo aplica para agentes.'], 403);
        }

        return $uid;
    }

    private function heartbeatSeconds(): int
    {
        return max(10, (int)($_ENV['AGENT_PRESENCE_HEARTBEAT_SECONDS'] ?? 30));
    }

    private function staleSeconds(): int
    {
        return max(30, (int)($_ENV['AGENT_PRESENCE_STALE_SECONDS'] ?? 90));
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
}