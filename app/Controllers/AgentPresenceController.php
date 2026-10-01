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
            if ($status === 'AVAILABLE' && !$wasAvailable) {
                try {
                    $assignment = (new AssignmentEngine(
                        $this->pdo,
                        new AssignmentRepository($this->pdo)
                    ))->runForAgent($uid, 500);
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
