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

            http_response_code(403);
            exit('Solicitud inválida.');
        }
    }
}
