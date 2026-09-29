<?php
declare(strict_types=1);

namespace App\Security;

use PDO;

final class LoginRateLimiter
{
    public function __construct(private PDO $pdo)
    {
    }

    private function identifierHash(string $identifier): string
    {
        return hash('sha256', mb_strtolower(trim($identifier)));
    }

    private function ipHash(): string
    {
        return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

    public function isBlocked(string $identifier): bool
    {
        $max = max(3, (int)($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5));
        $window = max(5, (int)($_ENV['LOGIN_WINDOW_MINUTES'] ?? 15));

        $sql = '
            SELECT COUNT(*)
            FROM login_attempts
            WHERE identifier_hash = :identifier_hash
              AND ip_hash = :ip_hash
              AND success = 0
              AND created_at >= DATE_SUB(NOW(6), INTERVAL :window MINUTE)
        ';

        $st = $this->pdo->prepare($sql);
        $st->bindValue(':identifier_hash', $this->identifierHash($identifier));
        $st->bindValue(':ip_hash', $this->ipHash());
        $st->bindValue(':window', $window, PDO::PARAM_INT);
        $st->execute();

        return (int)$st->fetchColumn() >= $max;
    }

    public function record(string $identifier, bool $success): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO login_attempts
             (identifier_hash, ip_hash, success, created_at)
             VALUES (:identifier_hash, :ip_hash, :success, NOW(6))'
        );
        $st->execute([
            ':identifier_hash' => $this->identifierHash($identifier),
            ':ip_hash' => $this->ipHash(),
            ':success' => $success ? 1 : 0,
        ]);
    }
}
