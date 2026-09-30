<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findForLogin(string $identifier): ?array
    {
        $identifier = trim($identifier);

        $st = $this->pdo->prepare(
            'SELECT id,document_number,username,email,full_name,password_hash,is_active
             FROM users
             WHERE username=:u OR email=:e
             LIMIT 1'
        );
        $st->execute([':u'=>$identifier, ':e'=>$identifier]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /**
     * @return array{
     *   rows:list<array<string,mixed>>,
     *   total:int,
     *   page:int,
     *   per_page:int,
     *   total_pages:int
     * }
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        string $search = '',
        ?int $active = null,
        ?int $roleId = null,
        ?int $queueId = null
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->userFilterSql($search, $active, $roleId, $queueId);

        $count = $this->pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM users u {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $sql = <<<SQL
            SELECT
                u.id,
                u.document_number,
                u.username,
                u.email,
                u.full_name,
                u.is_active,
                u.assign_enabled,
                u.last_login_at,
                u.last_assigned_at,
                GROUP_CONCAT(DISTINCT r.code ORDER BY r.code SEPARATOR ', ') AS roles,
                GROUP_CONCAT(DISTINCT q.code ORDER BY q.code SEPARATOR ', ') AS queues,
                COALESCE(ap.status_code,'OFFLINE') AS presence_code,
                COALESCE(ci.label,'Desconectado') AS presence_label
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id=u.id
            LEFT JOIN roles r ON r.id=ur.role_id
            LEFT JOIN queue_agents qa
              ON qa.user_id=u.id
             AND qa.is_enabled=1
             AND qa.removed_at IS NULL
            LEFT JOIN work_queues q ON q.id=qa.queue_id
            LEFT JOIN agent_presence ap
              ON ap.id=(
                    SELECT ap2.id
                    FROM agent_presence ap2
                    WHERE ap2.user_id=u.id
                      AND ap2.ended_at IS NULL
                    ORDER BY ap2.id DESC
                    LIMIT 1
              )
            LEFT JOIN catalogs pc ON pc.code='AGENT_PRESENCE_STATUS'
            LEFT JOIN catalog_items ci
              ON ci.catalog_id=pc.id
             AND ci.code=ap.status_code
            {$where}
            GROUP BY
                u.id,u.document_number,u.username,u.email,u.full_name,
                u.is_active,u.assign_enabled,u.last_login_at,u.last_assigned_at,
                ap.status_code,ci.label
            ORDER BY u.full_name,u.id
            LIMIT :limit OFFSET :offset
        SQL;

        $st = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value);
        }
        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        return [
            'rows'=>$st->fetchAll() ?: [],
            'total'=>$total,
            'page'=>$page,
            'per_page'=>$perPage,
            'total_pages'=>max(1, (int)ceil($total / $perPage)),
        ];
    }

    /** @return array<string,int> */
    public function statistics(): array
    {
        $sql = <<<SQL
            SELECT
                COUNT(*) total_users,
                SUM(is_active=1) active_users,
                SUM(assign_enabled=1) assignable_users,
                SUM(EXISTS(
                    SELECT 1
                    FROM user_roles ur
                    JOIN roles r ON r.id=ur.role_id
                    WHERE ur.user_id=users.id
                      AND r.code='AGENTE'
                )) agent_users
            FROM users
        SQL;

        $row = $this->pdo->query($sql)->fetch() ?: [];

        $available = (int)$this->pdo->query(
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE'
             JOIN agent_presence ap
               ON ap.user_id=u.id
              AND ap.ended_at IS NULL
              AND ap.status_code='AVAILABLE'
             WHERE u.is_active=1
               AND u.assign_enabled=1"
        )->fetchColumn();

        return [
            'total_users'=>(int)($row['total_users'] ?? 0),
            'active_users'=>(int)($row['active_users'] ?? 0),
            'assignable_users'=>(int)($row['assignable_users'] ?? 0),
            'agent_users'=>(int)($row['agent_users'] ?? 0),
            'available_agents'=>$available,
        ];
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id,document_number,username,email,full_name,is_active,assign_enabled,last_login_at,last_assigned_at
             FROM users WHERE id=:id LIMIT 1'
        );
        $st->execute([':id'=>$id]);
        $user = $st->fetch();

        if (!$user) {
            return null;
        }

        $user['role_ids'] = $this->idsForUser(
            'SELECT role_id FROM user_roles WHERE user_id=:uid ORDER BY role_id',
            $id
        );
        $user['queue_ids'] = $this->idsForUser(
            'SELECT queue_id
             FROM queue_agents
             WHERE user_id=:uid
               AND is_enabled=1
               AND removed_at IS NULL
             ORDER BY queue_id',
            $id
        );

        return $user;
    }

    /** @return list<array<string,mixed>> */
    public function roles(): array
    {
        return $this->pdo->query(
            'SELECT id,code,name,description
             FROM roles
             WHERE is_active=1
             ORDER BY FIELD(code,"ADMIN","SUPERVISOR","AGENTE"),name'
        )->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function queues(): array
    {
        return $this->pdo->query(
            'SELECT id,code,name,description,default_capacity
             FROM work_queues
             WHERE is_active=1
             ORDER BY priority,code'
        )->fetchAll() ?: [];
    }

    /**
     * @param list<int> $roleIds
     * @return list<string>
     */
    public function selectedRoleCodes(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($roleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT code FROM roles WHERE is_active=1 AND id IN ({$placeholders}) ORDER BY id"
        );
        $st->execute($roleIds);

        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<string> $codes
     * @return list<int>
     */
    public function roleIdsFromCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn(mixed $v): string => strtoupper(trim((string)$v)), $codes)
        )));

        if ($codes === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $st = $this->pdo->prepare(
            "SELECT id FROM roles WHERE is_active=1 AND code IN ({$placeholders}) ORDER BY id"
        );
        $st->execute($codes);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<string> $codes
     * @return list<int>
     */
    public function queueIdsFromCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn(mixed $v): string => strtoupper(trim((string)$v)), $codes)
        )));

        if ($codes === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $st = $this->pdo->prepare(
            "SELECT id FROM work_queues WHERE is_active=1 AND code IN ({$placeholders}) ORDER BY id"
        );
        $st->execute($codes);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function duplicateExists(
        string $documentNumber,
        string $username,
        string $email,
        ?int $excludeId = null
    ): bool {
        $sql = 'SELECT 1 FROM users
                WHERE (document_number=:document OR username=:username OR email=:email)';
        $params = [
            ':document'=>$documentNumber,
            ':username'=>$username,
            ':email'=>$email,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id<>:exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (bool)$st->fetchColumn();
    }

    /**
     * @param array<string,mixed> $data
     * @param list<int> $roleIds
     * @param list<int> $queueIds
     */
    public function create(array $data, array $roleIds, array $queueIds): int
    {
        $manageTransaction = !$this->pdo->inTransaction();

        if ($manageTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $st = $this->pdo->prepare(
                'INSERT INTO users
                 (document_number,username,email,full_name,password_hash,is_active,assign_enabled,created_at,updated_at)
                 VALUES
                 (:document,:username,:email,:full_name,:password_hash,:active,:assign_enabled,NOW(6),NOW(6))'
            );
            $st->execute([
                ':document'=>$data['document_number'],
                ':username'=>$data['username'],
                ':email'=>$data['email'],
                ':full_name'=>$data['full_name'],
                ':password_hash'=>$data['password_hash'],
                ':active'=>(int)$data['is_active'],
                ':assign_enabled'=>(int)$data['assign_enabled'],
            ]);

            $userId = (int)$this->pdo->lastInsertId();

            $this->replaceRoles($userId, $roleIds);
            $this->replaceQueuesAndSkills(
                $userId,
                $queueIds,
                (int)($data['created_by'] ?? $userId)
            );

            if ($manageTransaction) {
                $this->pdo->commit();
            }

            return $userId;
        } catch (\Throwable $e) {
            if ($manageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param list<int> $roleIds
     * @param list<int> $queueIds
     */
    public function update(
        int $userId,
        array $data,
        array $roleIds,
        array $queueIds,
        int $actorUserId
    ): void {
        $this->pdo->beginTransaction();

        try {
            $sql = 'UPDATE users SET
                        document_number=:document,
                        username=:username,
                        email=:email,
                        full_name=:full_name,
                        is_active=:active,
                        assign_enabled=:assign_enabled,
                        updated_at=NOW(6)';
            $params = [
                ':document'=>$data['document_number'],
                ':username'=>$data['username'],
                ':email'=>$data['email'],
                ':full_name'=>$data['full_name'],
                ':active'=>(int)$data['is_active'],
                ':assign_enabled'=>(int)$data['assign_enabled'],
                ':id'=>$userId,
            ];

            if (!empty($data['password_hash'])) {
                $sql .= ', password_hash=:password_hash';
                $params[':password_hash'] = $data['password_hash'];
            }

            $sql .= ' WHERE id=:id';

            $st = $this->pdo->prepare($sql);
            $st->execute($params);

            $this->replaceRoles($userId, $roleIds);
            $this->replaceQueuesAndSkills($userId, $queueIds, $actorUserId);

            if ((int)$data['is_active'] !== 1 || (int)$data['assign_enabled'] !== 1) {
                $this->endCurrentPresence($userId);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function toggleActive(int $userId): int
    {
        $this->pdo->beginTransaction();

        try {
            $st = $this->pdo->prepare(
                'SELECT is_active FROM users WHERE id=:id FOR UPDATE'
            );
            $st->execute([':id'=>$userId]);
            $current = $st->fetchColumn();

            if ($current === false) {
                throw new \RuntimeException('Usuario no encontrado.');
            }

            $next = ((int)$current === 1) ? 0 : 1;

            $up = $this->pdo->prepare(
                'UPDATE users
                 SET is_active=:active,
                     assign_enabled=CASE WHEN :active2=0 THEN 0 ELSE assign_enabled END,
                     updated_at=NOW(6)
                 WHERE id=:id'
            );
            $up->execute([
                ':active'=>$next,
                ':active2'=>$next,
                ':id'=>$userId,
            ]);

            if ($next === 0) {
                $this->endCurrentPresence($userId);
            }

            $this->pdo->commit();
            return $next;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function userFilterSql(
        string $search,
        ?int $active,
        ?int $roleId,
        ?int $queueId
    ): array {
        $where = 'WHERE 1=1';
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $where .= ' AND (
                u.document_number LIKE :s1
                OR u.username LIKE :s2
                OR u.email LIKE :s3
                OR u.full_name LIKE :s4
            )';
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
            $params[':s4'] = $like;
        }

        if ($active !== null) {
            $where .= ' AND u.is_active=:active';
            $params[':active'] = $active;
        }

        if ($roleId !== null && $roleId > 0) {
            $where .= ' AND EXISTS(
                SELECT 1 FROM user_roles fur
                WHERE fur.user_id=u.id AND fur.role_id=:role_id
            )';
            $params[':role_id'] = $roleId;
        }

        if ($queueId !== null && $queueId > 0) {
            $where .= ' AND EXISTS(
                SELECT 1 FROM queue_agents fqa
                WHERE fqa.user_id=u.id
                  AND fqa.queue_id=:queue_id
                  AND fqa.is_enabled=1
                  AND fqa.removed_at IS NULL
            )';
            $params[':queue_id'] = $queueId;
        }

        return [$where, $params];
    }

    /** @param list<int> $roleIds */
    private function replaceRoles(int $userId, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn(int $id): bool => $id > 0
        )));

        $this->pdo->prepare('DELETE FROM user_roles WHERE user_id=:uid')
            ->execute([':uid'=>$userId]);

        $st = $this->pdo->prepare(
            'INSERT INTO user_roles(user_id,role_id,created_at)
             VALUES(:uid,:rid,NOW(6))'
        );

        foreach ($roleIds as $roleId) {
            $st->execute([':uid'=>$userId, ':rid'=>$roleId]);
        }
    }

    /** @param list<int> $queueIds */
    private function replaceQueuesAndSkills(
        int $userId,
        array $queueIds,
        int $actorUserId
    ): void {
        $queueIds = array_values(array_unique(array_filter(
            array_map('intval', $queueIds),
            static fn(int $id): bool => $id > 0
        )));

        $this->pdo->prepare(
            'UPDATE queue_agents
             SET is_enabled=0,removed_at=NOW(6)
             WHERE user_id=:uid AND removed_at IS NULL'
        )->execute([':uid'=>$userId]);

        $this->pdo->prepare(
            'UPDATE user_skills
             SET is_active=0,removed_at=NOW(6)
             WHERE user_id=:uid AND removed_at IS NULL'
        )->execute([':uid'=>$userId]);

        if ($queueIds === []) {
            return;
        }

        $queueStmt = $this->pdo->prepare(
            'INSERT INTO queue_agents
             (queue_id,user_id,capacity_override,priority,is_enabled,assigned_by,assigned_at,removed_at)
             VALUES(:qid,:uid,NULL,100,1,:actor,NOW(6),NULL)
             ON DUPLICATE KEY UPDATE
                is_enabled=1,
                removed_at=NULL,
                assigned_by=VALUES(assigned_by),
                assigned_at=NOW(6)'
        );

        $skillLookup = $this->pdo->prepare(
            'SELECT s.id
             FROM queue_skills qs
             JOIN skills s ON s.id=qs.skill_id
             WHERE qs.queue_id=:qid
               AND qs.is_required=1
               AND s.is_active=1'
        );

        $skillStmt = $this->pdo->prepare(
            'INSERT INTO user_skills
             (user_id,skill_id,is_active,assigned_by,assigned_at,removed_at)
             VALUES(:uid,:sid,1,:actor,NOW(6),NULL)
             ON DUPLICATE KEY UPDATE
                is_active=1,
                removed_at=NULL,
                assigned_by=VALUES(assigned_by),
                assigned_at=NOW(6)'
        );

        foreach ($queueIds as $queueId) {
            $queueStmt->execute([
                ':qid'=>$queueId,
                ':uid'=>$userId,
                ':actor'=>$actorUserId,
            ]);

            $skillLookup->execute([':qid'=>$queueId]);
            foreach ($skillLookup->fetchAll(PDO::FETCH_COLUMN) ?: [] as $skillId) {
                $skillStmt->execute([
                    ':uid'=>$userId,
                    ':sid'=>(int)$skillId,
                    ':actor'=>$actorUserId,
                ]);
            }
        }
    }

    private function endCurrentPresence(int $userId): void
    {
        $this->pdo->prepare(
            "UPDATE agent_presence
             SET ended_at=NOW(6),last_heartbeat_at=NOW(6)
             WHERE user_id=:uid AND ended_at IS NULL"
        )->execute([':uid'=>$userId]);
    }

    /** @return list<int> */
    private function idsForUser(string $sql, int $userId): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute([':uid'=>$userId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
