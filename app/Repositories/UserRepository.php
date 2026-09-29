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
            'SELECT id, document_number, username, email, full_name, password_hash, is_active
             FROM users
             WHERE username = :username_identifier OR email = :email_identifier
             LIMIT 1'
        );
        $st->execute([
            ':username_identifier' => $identifier,
            ':email_identifier' => $identifier,
        ]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function listAll(): array
    {
        $sql = <<<'SQL'
            SELECT
                u.id,
                u.document_number,
                u.username,
                u.email,
                u.full_name,
                u.is_active,
                GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ', ') AS roles
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            GROUP BY u.id
            ORDER BY u.full_name, u.id
        SQL;

        return $this->pdo->query($sql)->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function roles(): array
    {
        return $this->pdo->query(
            'SELECT id, code, name FROM roles WHERE is_active = 1 ORDER BY name'
        )->fetchAll() ?: [];
    }

    /**
     * @param array<string,mixed> $data
     * @param list<int> $roleIds
     */
    public function create(array $data, array $roleIds): int
    {
        $this->pdo->beginTransaction();

        try {
            $st = $this->pdo->prepare(
                'INSERT INTO users
                 (document_number, username, email, full_name, password_hash, is_active, assign_enabled, created_at, updated_at)
                 VALUES
                 (:document_number, :username, :email, :full_name, :password_hash, 1, 1, NOW(6), NOW(6))'
            );
            $st->execute([
                ':document_number' => $data['document_number'],
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':full_name' => $data['full_name'],
                ':password_hash' => $data['password_hash'],
            ]);

            $userId = (int)$this->pdo->lastInsertId();

            $roleStmt = $this->pdo->prepare(
                'INSERT INTO user_roles (user_id, role_id, created_at)
                 VALUES (:uid, :rid, NOW(6))'
            );

            foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
                if ($roleId > 0) {
                    $roleStmt->execute([':uid' => $userId, ':rid' => $roleId]);
                }
            }

            $this->pdo->commit();
            return $userId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
