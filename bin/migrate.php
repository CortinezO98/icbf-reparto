<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(150) NOT NULL PRIMARY KEY,
        applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $version = basename($file);

    $st = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
    $st->execute([':version' => $version]);

    if ($st->fetchColumn()) {
        echo "[skip] {$version}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("No se pudo leer {$file}");
    }

    echo "[run ] {$version}\n";

    // MySQL DDL puede hacer commit implícito. Se registra la migración solo después de ejecutar sin error.
    $pdo->exec($sql);

    $insert = $pdo->prepare(
        'INSERT INTO schema_migrations (version, applied_at)
         VALUES (:version, NOW(6))'
    );
    $insert->execute([':version' => $version]);

    echo "[ ok ] {$version}\n";
}

echo "Migraciones finalizadas.\n";
