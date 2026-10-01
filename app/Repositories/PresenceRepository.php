
        return $this->currentForUser($userId);
    }

    /**
     * Cierra automáticamente las presencias cuyo heartbeat quedó obsoleto.
     *
     * La desconexión explícita (logout) se registra en el instante del logout.
     * Para cierres de navegador, pérdida de red o caída del cliente, la hora
     * registrada corresponde al momento en que el worker detecta el heartbeat
     * vencido.
     *
     * @return array{processed:int,cutoff:string}
     */
    public function expireStalePresences(int $staleSeconds): array
    {
        $staleSeconds = max(30, $staleSeconds);
        $cutoff = (new DateTimeImmutable())
            ->modify('-' . $staleSeconds . ' seconds')
            ->format('Y-m-d H:i:s.u');

        $this->pdo->beginTransaction();

        try {
            $st = $this->pdo->prepare(
                "SELECT id,user_id
                 FROM agent_presence
                 WHERE ended_at IS NULL
                   AND status_code<>'OFFLINE'
                   AND last_heartbeat_at IS NOT NULL
                   AND last_heartbeat_at < :cutoff
                 ORDER BY id
                 FOR UPDATE"
            );
            $st->execute([':cutoff'=>$cutoff]);
            $rows = $st->fetchAll() ?: [];

            if ($rows === []) {
                $this->pdo->commit();

                return [
                    'processed'=>0,
                    'cutoff'=>$cutoff,
                ];
            }

            $update = $this->pdo->prepare(
                "UPDATE agent_presence
                 SET ended_at=NOW(6)
                 WHERE id=:id
                   AND ended_at IS NULL"
            );
            $insert = $this->pdo->prepare(
                "INSERT INTO agent_presence
                 (user_id,status_code,started_at,last_heartbeat_at,notes,source,set_by)
                 VALUES
                 (:user_id,'OFFLINE',NOW(6),:last_heartbeat_at,:notes,'SYSTEM',:set_by)"
            );

            foreach ($rows as $row) {
                $update->execute([':id'=>(int)$row['id']]);

                $insert->execute([
                    ':user_id'=>(int)$row['user_id'],
                    ':last_heartbeat_at'=>null,
                    ':notes'=>'STALE_HEARTBEAT',
                    ':set_by'=>(int)$row['user_id'],
                ]);
            }

            $this->pdo->commit();

            return [
                'processed'=>count($rows),
                'cutoff'=>$cutoff,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function heartbeat(int $userId): void