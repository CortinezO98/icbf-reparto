<?php
declare(strict_types=1);

namespace App\Auth;

final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session not started.');
        }

        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['_csrf'];
    }

    public static function validate(?string $token): void
    {
        $expected = $_SESSION['_csrf'] ?? null;

        if (
            !is_string($expected) ||
            !is_string($token) ||
            $token === '' ||
            !hash_equals($expected, $token)
        ) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

            // Un token CSRF del login puede quedar obsoleto por expiración
            // de sesión, otra pestaña o regeneración de sesión.
            if ($path === '/login') {
                unset($_SESSION['_csrf']);
                $_SESSION['_flash_error'] =
                    'La sesión de inicio de sesión expiró o ya no es válida. Por favor, vuelve a iniciar sesión.';
                header('Location: /login');
                exit;
            }

            $authenticated = Auth::check();

            if ($authenticated) {
                $_SESSION['_flash_error'] =
                    'La solicitud expiró o ya no es válida. Vuelve a intentarlo desde la página actual.';
                $target = self::safeRefererPath() ?? '/';
            } else {
                $_SESSION['_flash_error'] =
                    'Tu sesión expiró o ya no es válida. Por favor, vuelve a iniciar sesión.';
                $target = '/login';
            }

            header('Location: ' . $target);
            exit;
        }
    }

    private static function safeRefererPath(): ?string
    {
        $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer === '') {
            return null;
        }

        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return null;
        }

        $host = (string)($parts['host'] ?? '');
        $currentHost = (string)($_SERVER['HTTP_HOST'] ?? '');

        if ($host !== '' && $currentHost !== '' && strcasecmp($host, $currentHost) !== 0) {
            return null;
        }

        $path = (string)($parts['path'] ?? '/');
        if ($path === '' || $path[0] !== '/') {
            return null;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $path . $query;
    }
}
