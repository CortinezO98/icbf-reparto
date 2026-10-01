<?php
$year = date('Y');
?>
<div class="login-wrap">
    <div class="login-col px-3">
        <div class="reparto-login-mark animate__animated animate__fadeInDown"><i class="bi bi-key"></i></div>
        <div class="login-title animate__animated animate__fadeInDown">ICBF • Módulo de Reparto</div>

        <div class="card card-login shadow-lg animate__animated animate__fadeInUp">
            <div class="card-header">
                <h5 class="mb-0 text-white"><i class="bi bi-shield-lock me-2"></i>Restablecer contraseña</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars((string)$success, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <p class="text-muted small">
                    Ingresa tu usuario o correo. Si la cuenta está registrada, recibirás un enlace de restablecimiento.
                </p>

                <form method="post" action="/forgot-password">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label for="login" class="form-label">Usuario o correo</label>
                        <input id="login" name="login" class="form-control" required autocomplete="username">
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-brand" type="submit">
                            <i class="bi bi-envelope me-1"></i>Enviar enlace
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="/login">Volver al inicio de sesión</a>
                </div>
            </div>
        </div>
        <div class="login-footer">ICBF Reparto • <?= (int)$year ?></div>
    </div>
</div>
