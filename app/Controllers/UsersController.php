<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\PasswordPolicy;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use PDO;

final class UsersController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_VIEW');

        $repo = new UserRepository($this->pdo);
        $users = $repo->listAll();

        $view = dirname(__DIR__) . '/Views/users/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function createForm(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_CREATE');

        $roles = (new UserRepository($this->pdo))->roles();
        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/users/create.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function create(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_CREATE');
        Csrf::validate($_POST['_csrf'] ?? null);

        $data = [
            'document_number' => trim((string)($_POST['document_number'] ?? '')),
            'username' => trim((string)($_POST['username'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
        ];

        $password = (string)($_POST['password'] ?? '');
        $roleIds = $_POST['role_ids'] ?? [];

        if (
            $data['document_number'] === '' ||
            $data['username'] === '' ||
            $data['full_name'] === '' ||
            !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
            !is_array($roleIds) ||
            !$roleIds
        ) {
            $_SESSION['_flash_error'] = 'Completa todos los campos y selecciona al menos un rol.';
            header('Location: /admin/users/create');
            exit;
        }

        $errors = PasswordPolicy::validate($password);
        if ($errors) {
            $_SESSION['_flash_error'] = implode(' ', $errors);
            header('Location: /admin/users/create');
            exit;
        }

        $data['password_hash'] = PasswordPolicy::hash($password);

        try {
            $userId = (new UserRepository($this->pdo))->create($data, $roleIds);

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'USER_CREATED',
                'USER',
                (string)$userId,
                ['roles' => array_values(array_map('intval', $roleIds))]
            );

            header('Location: /admin/users');
            exit;
        } catch (\Throwable $e) {
            error_log('[UsersController::create] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible crear el usuario. Verifica que documento, usuario y correo no estén registrados.';
            header('Location: /admin/users/create');
            exit;
        }
    }
}
