
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