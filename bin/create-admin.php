<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Auth\PasswordPolicy;
use App\Config\Database;

function prompt(string $label): string
{
    $value = readline($label . ': ');
    return trim((string)$value);
}

function secretPrompt(string $label): string
{
    fwrite(STDOUT, $label . ': ');

    $isUnix = DIRECTORY_SEPARATOR === '/';
    if ($isUnix) {
        shell_exec('stty -echo');
    }

    $value = trim((string)fgets(STDIN));

    if ($isUnix) {
        shell_exec('stty echo');
    }

    fwrite(STDOUT, PHP_EOL);
    return $value;
}

$document = prompt('Documento');
$username = prompt('Usuario');
$fullName = prompt('Nombre completo');
$email = prompt('Correo');
$password = secretPrompt('Contraseña');

if (
    $document === '' ||
    $username === '' ||
    $fullName === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    fwrite(STDERR, "Datos inválidos.\n");
    exit(1);
}

$errors = PasswordPolicy::validate($password);
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $st = $pdo->prepare(
        'INSERT INTO users
        (document_number, username, email, full_name, password_hash, is_active, assign_enabled, created_at, updated_at)
        VALUES
        (:document, :username, :email, :full_name, :password_hash, 1, 1, NOW(6), NOW(6))'
    );

    $st->execute([
        ':document' => $document,
        ':username' => $username,
        ':email' => $email,
        ':full_name' => $fullName,
        ':password_hash' => PasswordPolicy::hash($password),
    ]);

    $userId = (int)$pdo->lastInsertId();

    $role = $pdo->query("SELECT id FROM roles WHERE code = 'ADMIN' LIMIT 1")->fetchColumn();
    if (!$role) {
        throw new RuntimeException('No existe el rol ADMIN. Ejecuta primero las migraciones.');
    }

    $assign = $pdo->prepare(
        'INSERT INTO user_roles (user_id, role_id, created_at)
         VALUES (:uid, :rid, NOW(6))'
    );
    $assign->execute([':uid' => $userId, ':rid' => (int)$role]);

    $pdo->commit();

    echo "Administrador creado correctamente. ID={$userId}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "No fue posible crear el administrador: {$e->getMessage()}\n");
    exit(1);
}
