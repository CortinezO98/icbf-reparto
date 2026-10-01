<div class="login-wrap">
    <div class="login-col px-3">
        <div class="reparto-login-mark"><i class="bi bi-key"></i></div>
        <div class="login-title">ICBF • Módulo de Reparto</div>

        <div class="card card-login shadow-lg">
            <div class="card-header">
                <h5 class="mb-0 text-white"><i class="bi bi-shield-lock me-2"></i>Recuperar contraseña</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars((string)$success, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <p class="text-muted small">
                    Ingresa el correo registrado. Si existe una cuenta activa, recibirás un enlace para restablecer la contraseña.
                </p>

                <form method="post" action="/forgot-password" autocomplete="off">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(AppAuthCsrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input id="email" class="form-control" name="email" type="email"
                               autocomplete="email" maxlength="180" required>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-brand" type="submit">
                            <i class="bi bi-envelope me-1"></i>Enviar enlace
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="/login" class="small text-decoration-none">Volver al inicio de sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>
