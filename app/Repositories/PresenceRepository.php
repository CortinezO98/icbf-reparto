<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Presence\PresenceStatus;
use DateTimeImmutable;
use PDO;

final class PresenceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function selectableStatuses(): array
    {
        $catalog = $this->pdo->query(
            "SELECT id FROM catalogs
             WHERE code='AGENT_PRESENCE_STATUS' AND is_active=1
             LIMIT 1"
        )->fetchColumn();

        if ($catalog === false) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count(PresenceStatus::selectableCodes()), '?'));

        $st = $this->pdo->prepare(
            "SELECT code,label,sort_order
             FROM catalog_items
             WHERE catalog_id=?
               AND is_active=1
               AND code IN ({$placeholders})
             ORDER BY sort_order,id"
        );

        $st->execute([(int)$catalog, ...PresenceStatus::selectableCodes()]);

        $rows = $st->fetchAll() ?: [];

        return array_map(
            static function(array $row): array {
                $row['color'] = PresenceStatus::color((string)$row['code']);
                $row['is_assignable'] = PresenceStatus::isAssignable((string)$row['code']);
                return $row;
            },
            $rows
        );
    }

    /** @return array<string,mixed>|null */
    public function currentForUser(int $userId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT
                ap.id,
                ap.user_id,
                ap.status_code,
                COALESCE(ci.label,
                    CASE WHEN ap.status_code='OFFLINE' THEN 'Desconectado' ELSE ap.status_code END
                ) status_label,
                ap.started_at,
                ap.ended_at,
                ap.last_heartbeat_at,
                ap.notes,
                ap.source,
                ap.set_by
             FROM agent_presence ap
             LEFT JOIN catalogs c
               ON c.code='AGENT_PRESENCE_STATUS'
             LEFT JOIN catalog_items ci
               ON ci.catalog_id=c.id
              AND ci.code=ap.status_code
             WHERE ap.user_id=:uid
               AND ap.ended_at IS NULL
             ORDER BY ap.id DESC
             LIMIT 1"
        );
        $st->execute([':uid'=>$userId]);
        $row = $st->fetch();

        if (!$row) {
            return null;
        }

        $row['color'] = PresenceStatus::color((string)$row['status_code']);
        $row['is_assignable'] = PresenceStatus::isAssignable((string)$row['status_code']);

        return $row;
    }

    public function setSelectableStatus(
        int $userId,
        string $statusCode,
        int $setBy,
        string $source = 'USER',
        ?string $notes = null
    ): int {
        $statusCode = strtoupper(trim($statusCode));

        if (!PresenceStatus::isSelectable($statusCode)) {
            throw new \InvalidArgumentException('Estado de agente no permitido.');
        }

        $valid = false;
        foreach ($this->selectableStatuses() as $status) {
            if ((string)$status['code'] === $statusCode) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new \InvalidArgumentException('El estado seleccionado no está activo.');
        }

        return $this->setStatus($userId, $statusCode, $setBy, $source, $notes);
    }

    public function markOffline(int $userId, ?int $setBy = null, string $reason = 'SYSTEM'): int
    {
        return $this->setStatus(
            $userId,
            'OFFLINE',
            $setBy ?? $userId,
            'SYSTEM',
            $reason
        );
    }

    /** @return array<string,mixed>|null */
    public function heartbeatWithStaleProtection(int $userId, int $staleSeconds): ?array
    {
        $staleSeconds = max(30, $staleSeconds);
        $current = $this->currentForUser($userId);

        if ($current === null) {
            return null;
        }

        if ((string)$current['status_code'] === 'OFFLINE') {
            return $current;
        }

        $last = $current['last_heartbeat_at'] ?? null;
        if ($last !== null && $last !== '') {
            $lastAt = new DateTimeImmutable((string)$last);
            $cutoff = (new DateTimeImmutable())->modify("-{$staleSeconds} seconds");

            if ($lastAt < $cutoff) {
                $this->markOffline($userId, $userId, 'STALE_HEARTBEAT');
                return $this->currentForUser($userId);
            }
        }

        $this->heartbeat($userId);

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
                 (:user_id,'OFFLINE',NOW(6),NULL,:notes,'SYSTEM',:set_by)"
            );

            foreach ($rows as $row) {
                $update->execute([':id'=>(int)$row['id']]);

                $insert->execute([
                    ':user_id'=>(int)$row['user_id'],
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
    {
        $st = $this->pdo->prepare(
            "UPDATE agent_presence
             SET last_heartbeat_at=NOW(6)
             WHERE user_id=:uid
               AND ended_at IS NULL
               AND status_code<>'OFFLINE'"
        );
        $st->execute([':uid'=>$userId]);
    }

    /** @return list<array<string,mixed>> */
    public function supervisorAgents(int $staleSeconds): array
    {
        $cutoff = (new DateTimeImmutable())
            ->modify('-' . max(30, $staleSeconds) . ' seconds')
            ->format('Y-m-d H:i:s.u');

        $sql = <<<'SQL'
            SELECT
                u.id,
                u.full_name,
                u.username,
                u.is_active,
                u.assign_enabled,
                COALESCE(ap.status_code,'OFFLINE') status_code,
                COALESCE(ci.label,'Desconectado') status_label,
                ap.started_at,
                ap.last_heartbeat_at,
                GROUP_CONCAT(DISTINCT q.code ORDER BY q.code SEPARATOR ', ') queue_codes,
                COALESCE(SUM(DISTINCT CASE
                    WHEN qa.is_enabled=1 AND qa.removed_at IS NULL
                    THEN COALESCE(qa.capacity_override,q.default_capacity)
                    ELSE 0 END),0) configured_capacity,
                (
                    SELECT COUNT(*)
                    FROM cases c2
                    WHERE c2.assigned_user_id=u.id
                      AND c2.closed_at IS NULL
                      AND c2.current_state<>'PENDING_ASSIGNMENT'
                ) open_cases
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE' AND r.is_active=1
            LEFT JOIN agent_presence ap
              ON ap.id=(
                    SELECT ap2.id
                    FROM agent_presence ap2
                    WHERE ap2.user_id=u.id
                      AND ap2.ended_at IS NULL
                    ORDER BY ap2.id DESC
                    LIMIT 1
              )
            LEFT JOIN catalogs cat ON cat.code='AGENT_PRESENCE_STATUS'
            LEFT JOIN catalog_items ci
              ON ci.catalog_id=cat.id
             AND ci.code=ap.status_code
            LEFT JOIN queue_agents qa
              ON qa.user_id=u.id
             AND qa.removed_at IS NULL
            LEFT JOIN work_queues q ON q.id=qa.queue_id
            GROUP BY
                u.id,u.full_name,u.username,u.is_active,u.assign_enabled,
                ap.status_code,ci.label,ap.started_at,ap.last_heartbeat_at
            ORDER BY u.full_name,u.id
        SQL;

        $rows = $this->pdo->query($sql)->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $heartbeatFresh = !empty($row['last_heartbeat_at'])
                && (string)$row['last_heartbeat_at'] >= $cutoff;

            $available = (string)$row['status_code'] === 'AVAILABLE'
                && $heartbeatFresh
                && (int)$row['is_active'] === 1
                && (int)$row['assign_enabled'] === 1;

            $stale = !$heartbeatFresh
                && (string)$row['status_code'] !== 'OFFLINE';

            $row['effective_available'] = $available ? 1 : 0;
            $row['effective_status_code'] = $stale
                ? 'OFFLINE'
                : (string)$row['status_code'];
            $row['effective_status_label'] = $stale
                ? 'Desconectado'
                : (string)$row['status_label'];
            $row['status_color'] = PresenceStatus::color(
                $stale
                    ? 'OFFLINE'
                    : ($available ? 'AVAILABLE' : (string)$row['status_code'])
            );
            $row['free_capacity'] = $available
                ? max(0, (int)$row['configured_capacity'] - (int)$row['open_cases'])
                : 0;
        }
        unset($row);

        return $rows;
    }

    /** @return array<string,int> */
    public function supervisorSummary(int $staleSeconds): array
    {
        $agents = $this->supervisorAgents($staleSeconds);

        $availableAgents = 0;
        $availableCapacity = 0;

        foreach ($agents as $agent) {
            $availableAgents += (int)$agent['effective_available'];
            $availableCapacity += (int)$agent['free_capacity'];
        }

        $pending = (int)$this->pdo->query(
            "SELECT COUNT(*)
             FROM cases
             WHERE current_state='PENDING_ASSIGNMENT'
               AND assigned_user_id IS NULL"
        )->fetchColumn();

        return [
            'available_agents'=>$availableAgents,
            'available_capacity'=>$availableCapacity,
            'pending_queue'=>$pending,
            'total_agents'=>count($agents),
        ];
    }

    private function setStatus(
        int $userId,
        string $statusCode,
        int $setBy,
        string $source,
        ?string $notes
    ): int {
        $this->pdo->beginTransaction();

        try {
            $st = $this->pdo->prepare(
                "SELECT id
                 FROM agent_presence
                 WHERE user_id=:uid
                   AND ended_at IS NULL
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );
            $st->execute([':uid'=>$userId]);
            $currentId = $st->fetchColumn();

            if ($currentId !== false) {
                $close = $this->pdo->prepare(
                    "UPDATE agent_presence
                     SET ended_at=NOW(6),
                         last_heartbeat_at=NOW(6)
                     WHERE id=:id"
                );
                $close->execute([':id'=>(int)$currentId]);
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO agent_presence
                 (user_id,status_code,started_at,last_heartbeat_at,notes,source,set_by)
                 VALUES
                 (:uid,:status,NOW(6),NOW(6),:notes,:source,:set_by)"
            );
            $insert->execute([
                ':uid'=>$userId,
                ':status'=>strtoupper(trim($statusCode)),
                ':notes'=>$notes,
                ':source'=>$source,
                ':set_by'=>$setBy,
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}