<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\PasswordPolicy;
use App\Config\App;
use App\Repositories\AuditRepository;
use App\Repositories\PresenceRepository;
use App\Repositories\UserRepository;
use App\Security\LoginRateLimiter;
use App\Services\Users\TemporaryPasswordGenerator;
use PDO;

final class AuthController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirectAfterLogin();
        }

        $error = $_SESSION['_flash_error'] ?? null;
        $success = $_SESSION['_flash_success'] ?? null;
        unset($_SESSION['_flash_error'], $_SESSION['_flash_success']);

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
        $roles = Authorization::roles($this->pdo, $uid);

        try {
            if (in_array('AGENTE', $roles, true)) {
                // Iniciar sesión nunca coloca al agente en Disponible.
                // Debe seleccionar Disponible explícitamente después de entrar.
                (new PresenceRepository($this->pdo))->markOffline($uid, $uid, 'LOGIN');
            }
        } catch (\Throwable $e) {
            error_log('[AgentPresence][LOGIN] ' . $e->getMessage());
        }

        (new AuditRepository($this->pdo))->log(
            $uid,
            'LOGIN_SUCCESS',
            'USER',
            (string)$uid,
            [
                'must_change_password'=>(int)($user['must_change_password'] ?? 0),
            ]
        );

        if ((int)($user['must_change_password'] ?? 0) === 1) {
            $_SESSION['_flash_warning'] =
                'Por seguridad debes cambiar la contraseña temporal antes de continuar.';
            header('Location: /change-password');
            exit;
        }

        $this->redirectAfterLogin();
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

    public function showForgotPassword(): void
    {
        if (Auth::check()) {
            $this->redirectAfterLogin();
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

        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['_flash_error'] = 'Ingresa un correo electrónico válido.';
            header('Location: /forgot-password');
            exit;
        }

        $repo = new UserRepository($this->pdo);
        $attempts = $repo->passwordResetRequestsInLastHour($email);

        if ($attempts >= 3) {
            (new AuditRepository($this->pdo))->log(
                null,
                'PASSWORD_RESET_RATE_LIMIT',
                'SECURITY',
                null,
                ['email_hash'=>hash('sha256', $email), 'attempts'=>$attempts]
            );

            $_SESSION['_flash_success'] =
                'Si el correo existe en el sistema, recibirás un enlace para restablecer la contraseña.';
            header('Location: /forgot-password');
            exit;
        }

        $user = $repo->findActiveByEmail($email);

        if ($user) {
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);
            $expiresAt = (new \DateTimeImmutable('now'))->modify('+30 minutes')->format('Y-m-d H:i:s.u');

            $repo->storePasswordResetToken(
                (int)$user['id'],
                (string)$user['email'],
                $tokenHash,
                $expiresAt
            );

            (new AuditRepository($this->pdo))->log(
                (int)$user['id'],
                'PASSWORD_RESET_TOKEN_GENERATED',
                'SECURITY',
                (string)$user['id'],
                ['expires_in'=>'30 minutes']
            );

            $resetUrl = App::url('/reset-password?token=' . urlencode($plainToken));
            $sent = $this->sendPasswordResetEmail(
                (string)$user['email'],
                (string)($user['full_name'] ?: $user['username']),
                $resetUrl
            );

            (new AuditRepository($this->pdo))->log(
                (int)$user['id'],
                $sent ? 'PASSWORD_RESET_EMAIL_SENT' : 'PASSWORD_RESET_EMAIL_FAILED',
                'SECURITY',
                (string)$user['id']
            );
        } else {
            (new AuditRepository($this->pdo))->log(
                null,
                'PASSWORD_RESET_EMAIL_NOT_FOUND',
                'SECURITY',
                null,
                ['email_hash'=>hash('sha256', $email)]
            );
        }

        $_SESSION['_flash_success'] =
            'Si el correo existe en el sistema, recibirás un enlace para restablecer la contraseña.';
        header('Location: /forgot-password');
        exit;
    }

    public function showResetPassword(): void
    {
        if (Auth::check()) {
            $this->redirectAfterLogin();
        }

        $token = trim((string)($_GET['token'] ?? ''));

        if ($token === '') {
            http_response_code(400);
            echo 'Token inválido';
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
            $this->passwordResetError(
                'PASSWORD_RESET_INCOMPLETE',
                'Todos los campos son obligatorios.',
                $token
            );
        }

        if ($password !== $passwordConfirm) {
            $this->passwordResetError(
                'PASSWORD_RESET_MISMATCH',
                'Las contraseñas no coinciden.',
                $token
            );
        }

        $errors = PasswordPolicy::validate($password);

        if ($errors !== []) {
            $this->passwordResetError(
                'PASSWORD_RESET_WEAK_PASSWORD',
                implode(' ', $errors),
                $token
            );
        }

        $tokenHash = hash('sha256', $token);
        $repo = new UserRepository($this->pdo);
        $reset = $repo->findValidPasswordResetByTokenHash($tokenHash);

        if (!$reset) {
            (new AuditRepository($this->pdo))->log(
                null,
                'PASSWORD_RESET_INVALID_TOKEN',
                'SECURITY',
                null,
                ['token_hash'=>substr($tokenHash, 0, 8) . '...']
            );

            $_SESSION['_flash_error'] = 'El enlace no es válido o ya expiró.';
            header('Location: /forgot-password');
            exit;
        }

        $this->pdo->beginTransaction();

        try {
            $repo->updatePasswordHash(
                (int)$reset['user_id'],
                PasswordPolicy::hash($password)
            );
            $repo->markPasswordResetUsed((int)$reset['id']);
            $repo->invalidatePasswordResets((int)$reset['user_id']);

            $this->pdo->commit();

            (new AuditRepository($this->pdo))->log(
                (int)$reset['user_id'],
                'PASSWORD_RESET_SUCCESS',
                'SECURITY',
                (string)$reset['user_id'],
                ['reset_id'=>(int)$reset['id']]
            );

            $_SESSION['_flash_success'] =
                'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.';
            header('Location: /login');
            exit;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            (new AuditRepository($this->pdo))->log(
                (int)$reset['user_id'],
                'PASSWORD_RESET_ERROR',
                'SECURITY',
                (string)$reset['user_id'],
                ['error'=>$e->getMessage()]
            );

            $_SESSION['_flash_error'] = 'No se pudo actualizar la contraseña.';
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }
    }

    public function showChangePassword(): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        $forced = (int)($user['must_change_password'] ?? 0) === 1;

        $error = $_SESSION['_flash_error'] ?? null;
        $warning = $_SESSION['_flash_warning'] ?? null;
        unset($_SESSION['_flash_error'], $_SESSION['_flash_warning']);

        $view = dirname(__DIR__) . '/Views/auth/change_password.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::validate($_POST['_csrf'] ?? null);

        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($password === '' || $passwordConfirm === '') {
            $_SESSION['_flash_error'] = 'Todos los campos son obligatorios.';
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

        $uid = (int)Auth::id();
        $repo = new UserRepository($this->pdo);
        $currentHash = $repo->findPasswordHash($uid);

        if ($currentHash !== null && password_verify($password, $currentHash)) {
            $_SESSION['_flash_error'] = 'La nueva contraseña debe ser diferente a la temporal o actual.';
            header('Location: /change-password');
            exit;
        }

        try {
            $this->pdo->beginTransaction();

            $repo->updatePasswordHash($uid, PasswordPolicy::hash($password));
            $repo->invalidatePasswordResets($uid);

            $this->pdo->commit();

            $_SESSION['user']['must_change_password'] = 0;

            (new AuditRepository($this->pdo))->log(
                $uid,
                'PASSWORD_CHANGED',
                'SECURITY',
                (string)$uid
            );

            $this->redirectAfterLogin();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('[AuthController::changePassword] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No se pudo actualizar la contraseña.';
            header('Location: /change-password');
            exit;
        }
    }

    private function passwordResetError(
        string $event,
        string $message,
        string $token
    ): never {
        (new AuditRepository($this->pdo))->log(
            null,
            $event,
            'SECURITY',
            null
        );

        $_SESSION['_flash_error'] = $message;
        header('Location: /reset-password?token=' . urlencode($token));
        exit;
    }

    private function redirectAfterLogin(): never
    {
        $uid = (int)(Auth::id() ?? 0);

        if (in_array('AGENTE', Authorization::roles($this->pdo, $uid), true)) {
            header('Location: /cases');
            exit;
        }

        header('Location: /');
        exit;
    }

    private function sendPasswordResetEmail(
        string $toEmail,
        string $toName,
        string $resetUrl
    ): bool {
        $fromName = trim((string)($_ENV['MAIL_FROM_NAME'] ?? 'ICBF Reparto'));
        $fromEmail = trim((string)($_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@icbf.gov.co'));

        $subject = 'Recuperación de contraseña - ICBF Reparto';
        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        $body = '<!doctype html><html><body style="font-family:Arial,sans-serif">'
            . '<h2>Recuperación de contraseña</h2>'
            . '<p>Hola ' . $safeName . ',</p>'
            . '<p>Recibimos una solicitud para restablecer tu contraseña.</p>'
            . '<p><a href="' . $safeUrl . '">Restablecer contraseña</a></p>'
            . '<p>El enlace es válido durante 30 minutos y solo puede utilizarse una vez.</p>'
            . '<p>Si no realizaste esta solicitud, puedes ignorar este mensaje.</p>'
            . '</body></html>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        return mail(
            $toEmail,
            $subject,
            $body,
            implode("\r\n", $headers)
        );
    }
}
