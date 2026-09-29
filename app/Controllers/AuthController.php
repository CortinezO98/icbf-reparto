<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Csrf;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Security\LoginRateLimiter;
use PDO;

final class AuthController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: /');
            exit;
        }

        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/auth/login.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function login(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);

        $identifier = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $_SESSION['_flash_error'] = 'Usuario y contraseña son obligatorios.';
            header('Location: /login');
            exit;
        }

        $limiter = new LoginRateLimiter($this->pdo);

        if ($limiter->isBlocked($identifier)) {
            (new AuditRepository($this->pdo))->log(
                null,
                'LOGIN_RATE_LIMITED',
                'SECURITY',
                null,
                ['identifier_hash' => hash('sha256', mb_strtolower($identifier))]
            );

            $_SESSION['_flash_error'] = 'No fue posible iniciar sesión. Intenta nuevamente más tarde.';
            header('Location: /login');
            exit;
        }

        $repo = new UserRepository($this->pdo);
        $user = $repo->findForLogin($identifier);

        $ok = $user
            && (int)$user['is_active'] === 1
            && password_verify($password, (string)$user['password_hash']);

        $limiter->record($identifier, (bool)$ok);

        if (!$ok) {
            (new AuditRepository($this->pdo))->log(
                null,
                'LOGIN_FAILED',
                'SECURITY',
                null,
                ['identifier_hash' => hash('sha256', mb_strtolower($identifier))]
            );

            $_SESSION['_flash_error'] = 'Credenciales inválidas.';
            header('Location: /login');
            exit;
        }

        Auth::login($user);

        (new AuditRepository($this->pdo))->log(
            (int)$user['id'],
            'LOGIN_SUCCESS',
            'USER',
            (string)$user['id']
        );

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);

        $uid = Auth::id();
        if ($uid) {
            (new AuditRepository($this->pdo))->log(
                $uid,
                'LOGOUT',
                'USER',
                (string)$uid
            );
        }

        Auth::logout();
        header('Location: /login');
        exit;
    }
}
