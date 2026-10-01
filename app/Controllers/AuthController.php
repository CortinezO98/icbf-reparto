<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\PasswordPolicy;
use App\Repositories\AuditRepository;
use App\Repositories\PresenceRepository;
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

        $uid = (int)$user['id'];

        try {
            if (in_array('AGENTE', Authorization::roles($this->pdo, $uid), true)) {
                (new PresenceRepository($this->pdo))->markOffline($uid, $uid, 'LOGIN');
            }
        } catch (\Throwable $e) {
            error_log('[AgentPresence][LOGIN] ' . $e->getMessage());
        }

        (new AuditRepository($this->pdo))->log(
            $uid,
            'LOGIN_SUCCESS',
            'USER',
            (string)$uid
        );

        if ((int)($user['password_must_change'] ?? 0) === 1) {
            $_SESSION['_password_must_change'] = 1;
            header('Location: /change-password');
            exit;
        }

        $roles = Authorization::roles($this->pdo, $uid);
        header('Location: ' . (in_array('AGENTE', $roles, true) ? '/cases' : '/'));
        exit;
    }

    public function showForgotPassword(): void
    {
        if (Auth::check()) {
            header('Location: /');
            exit;
        }

        $error = $_SESSION['_flash_error'] ?? null;
        $success = $_SESSION['_flash_success'] ?? null;
        unset($_SESSION['_flash_error'], $_SESSION['_flash_success']);

        $view = dirname(__DIR__) . '/Views/auth/forgot_password.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function forgotPassword(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);

        $identifier = trim((string)($_POST['login'] ?? ''));

        if ($identifier === '') {
            $_SESSION['_flash_error'] = 'Ingresa tu usuario o correo.';
            header('Location: /forgot-password');
            exit;
        }

        $rateLimit = $this->passwordResetRateLimit($identifier);
        if (!$rateLimit['allowed']) {
            $_SESSION['_flash_error'] = (string)$rateLimit['message'];
            header('Location: /forgot-password');
            exit;
        }

        $repo = new UserRepository($this->pdo);
        $user = $repo->findActiveByIdentifier($identifier);

        // Respuesta uniforme para no revelar si una cuenta existe.
        if ($user) {
            try {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new \DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s.u');

                $repo->createPasswordResetToken(
                    (int)$user['id'],
                    $tokenHash,
                    $expiresAt
                );

                $this->sendPasswordResetEmail(
                    (string)$user['email'],
                    (string)$user['full_name'],
                    $token
                );

                (new AuditRepository($this->pdo))->log(
                    (int)$user['id'],
                    'PASSWORD_RESET_REQUESTED',
                    'USER',
                    (string)$user['id']
                );
            } catch (\Throwable $e) {
                error_log('[AuthController::forgotPassword] ' . $e->getMessage());
            }
        }

        $_SESSION['_flash_success'] =
            'Si la cuenta está registrada, recibirás un enlace para restablecer la contraseña.';
        header('Location: /forgot-password');
        exit;
    }

    public function showResetPassword(): void
    {
        $token = trim((string)($_GET['token'] ?? ''));

        if ($token === '') {
            http_response_code(400);
            echo 'Token inválido.';
            return;
        }

        $reset = (new UserRepository($this->pdo))
            ->findValidPasswordResetByTokenHash(hash('sha256', $token));

        if (!$reset) {
            $_SESSION['_flash_error'] = 'El enlace no es válido o ya expiró.';
            header('Location: /forgot-password');
            exit;
        }

        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/auth/reset_password.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function resetPassword(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);

        $token = trim((string)($_POST['token'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($token === '' || $password === '' || $passwordConfirm === '') {
            $_SESSION['_flash_error'] = 'Todos los campos son obligatorios.';
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['_flash_error'] = 'Las contraseñas no coinciden.';
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        $errors = PasswordPolicy::validate($password);
        if ($errors !== []) {
            $_SESSION['_flash_error'] = implode(' ', $errors);
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        $repo = new UserRepository($this->pdo);
        $reset = $repo->findValidPasswordResetByTokenHash(hash('sha256', $token));

        if (!$reset) {
            $_SESSION['_flash_error'] = 'El enlace no es válido o ya expiró.';
            header('Location: /forgot-password');
            exit;
        }

        $this->pdo->beginTransaction();

        try {
            $repo->updatePassword(
                (int)$reset['user_id'],
                PasswordPolicy::hash($password),
                false
            );
            $repo->markPasswordResetUsed((int)$reset['id']);
            $this->pdo->commit();

            (new AuditRepository($this->pdo))->log(
                (int)$reset['user_id'],
                'PASSWORD_RESET_SUCCESS',
                'USER',
                (string)$reset['user_id']
            );

            $_SESSION['_flash_success'] = 'Tu contraseña fue actualizada correctamente.';
            header('Location: /login');
            exit;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('[AuthController::resetPassword] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible actualizar la contraseña.';
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }
    }

    public function showChangePassword(): void
    {
        Auth::requireLogin();

        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $mustChange = (int)($_SESSION['_password_must_change'] ?? 0) === 1;

        $view = dirname(__DIR__) . '/Views/auth/change_password.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::validate($_POST['_csrf'] ?? null);

        $uid = (int)(Auth::id() ?? 0);
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($currentPassword === '' || $password === '' || $passwordConfirm === '') {
            $_SESSION['_flash_error'] = 'Todos los campos son obligatorios.';
            header('Location: /change-password');
            exit;
        }

        $user = (new UserRepository($this->pdo))->findForLogin((string)(Auth::user()['username'] ?? ''));

        if (!$user || !password_verify($currentPassword, (string)$user['password_hash'])) {
            $_SESSION['_flash_error'] = 'La contraseña actual no es correcta.';
            header('Location: /change-password');
            exit;
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['_flash_error'] = 'Las contraseñas no coinciden.';
            header('Location: /change-password');
            exit;
        }

        $errors = PasswordPolicy::validate($password);
        if ($errors !== []) {
            $_SESSION['_flash_error'] = implode(' ', $errors);
            header('Location: /change-password');
            exit;
        }

        try {
            (new UserRepository($this->pdo))->updatePassword(
                $uid,
                PasswordPolicy::hash($password),
                false
            );

            unset($_SESSION['_password_must_change']);

            (new AuditRepository($this->pdo))->log(
                $uid,
                'PASSWORD_CHANGED',
                'USER',
                (string)$uid
            );

            $_SESSION['_flash_success'] = 'Contraseña actualizada correctamente.';
            header('Location: /');
            exit;
        } catch (\Throwable $e) {
            error_log('[AuthController::changePassword] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible actualizar la contraseña.';
            header('Location: /change-password');
            exit;
        }
    }

    public function logout(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);

        $uid = Auth::id();

        if ($uid) {
            try {
                if (in_array('AGENTE', Authorization::roles($this->pdo, $uid), true)) {
                    (new PresenceRepository($this->pdo))->markOffline($uid, $uid, 'LOGOUT');
                }
            } catch (\Throwable $e) {
                error_log('[AgentPresence][LOGOUT] ' . $e->getMessage());
            }

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

    /**
     * Limita solicitudes de recuperación por IP + identificador.
     * 3 solicitudes por ventana de 60 minutos.
     *
     * @return array{allowed:bool,attempts:int,message?:string}
     */
    private function passwordResetRateLimit(string $identifier): array
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = '_password_reset_rate_' . hash(
            'sha256',
            mb_strtolower($ip . '|' . trim($identifier))
        );

        $now = time();
        $limit = 3;
        $window = 3600;

        $data = $_SESSION[$key] ?? ['count'=>0,'first'=>$now];

        if (!is_array($data)) {
            $data = ['count'=>0,'first'=>$now];
        }

        $first = (int)($data['first'] ?? $now);
        $count = (int)($data['count'] ?? 0);

        if (($now - $first) >= $window) {
            $data = ['count'=>1,'first'=>$now];
            $_SESSION[$key] = $data;

            return ['allowed'=>true,'attempts'=>1];
        }

        $count++;
        $data['count'] = $count;
        $_SESSION[$key] = $data;

        if ($count > $limit) {
            $minutesLeft = max(1, (int)ceil(($window - ($now - $first)) / 60));

            return [
                'allowed'=>false,
                'attempts'=>$count,
                'message'=>"Has superado el límite de solicitudes. Intenta nuevamente en {$minutesLeft} minutos.",
            ];
        }

        return ['allowed'=>true,'attempts'=>$count];
    }

    private function sendPasswordResetEmail(
        string $email,
        string $fullName,
        string $token
    ): void {
        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        $link = $appUrl . '/reset-password?token=' . urlencode($token);

        if ($appUrl === '') {
            throw new \RuntimeException('APP_URL no está configurada.');
        }

        $fromEmail = (string)($_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@icbf.gov.co');
        $fromName = (string)($_ENV['MAIL_FROM_NAME'] ?? 'ICBF Reparto');

        $subject = 'Restablecimiento de contraseña - ICBF Reparto';
        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

        $html = '<!doctype html><html lang="es"><body style="font-family:Arial,sans-serif">'
            . '<h2>Restablecimiento de contraseña</h2>'
            . '<p>Hola ' . $safeName . '.</p>'
            . '<p>Recibimos una solicitud para restablecer tu contraseña.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Restablecer contraseña</a></p>'
            . '<p>El enlace es válido durante 30 minutos y solo puede utilizarse una vez.</p>'
            . '<p>Si no solicitaste este cambio, puedes ignorar este mensaje.</p>'
            . '</body></html>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        if (!mail($email, $subject, $html, implode("\r\n", $headers))) {
            throw new \RuntimeException('No fue posible enviar el correo de restablecimiento.');
        }
    }
}
