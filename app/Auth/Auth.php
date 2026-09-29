<?php
declare(strict_types=1);

namespace App\Auth;

final class Auth
{
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name($_ENV['APP_SESSION_NAME'] ?? 'ICBF_REPARTO');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        $idleMinutes = max(5, (int)($_ENV['APP_SESSION_IDLE_MINUTES'] ?? 30));
        $now = time();

        if (
            isset($_SESSION['_last_activity']) &&
            ($now - (int)$_SESSION['_last_activity']) > ($idleMinutes * 60)
        ) {
            self::logout();
            session_start();
        }

        $_SESSION['_last_activity'] = $now;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user']['id']);
    }

    public static function id(): ?int
    {
        return self::check() ? (int)$_SESSION['user']['id'] : null;
    }

    /** @return array{id:int,username:string,full_name:string,email:string}|null */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'full_name' => (string)$user['full_name'],
            'email' => (string)$user['email'],
        ];

        $_SESSION['_last_activity'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }
}
