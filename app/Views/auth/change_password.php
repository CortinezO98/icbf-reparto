<?php
$year = date('Y');
?>
<div class="login-wrap">
    <div class="login-col px-3">
        <div class="reparto-login-mark animate__animated animate__fadeInDown"><i class="bi bi-shield-lock"></i></div>
        <div class="login-title animate__animated animate__fadeInDown">ICBF • Módulo de Reparto</div>

        <div class="card card-login shadow-lg animate__animated animate__fadeInUp">
            <div class="card-header">
                <h5 class="mb-0 text-white"><i class="bi bi-key-fill me-2"></i>Cambiar contraseña</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($mustChange)): ?>
                    <div class="alert alert-warning py-2">
                        Tu cuenta tiene una contraseña temporal. Debes definir una contraseña propia para continuar.
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" action="/change-password">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña actual</label>
                        <input id="current_password" name="current_password" type="password" class="form-control" required autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <input id="password" name="password" type="password" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar nueva contraseña</label>
                        <input id="password_confirm" name="password_confirm" type="password" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>

                    <div class="small text-muted mb-3">
                        Mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo.
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-brand" type="submit">
                            <i class="bi bi-check-circle me-1"></i>Guardar nueva contraseña
                        </button>
                    </div>
                </form>

                <form method="post" action="/logout" class="mt-3 text-center">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-link text-muted" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </div>
        <div class="login-footer">ICBF Reparto • <?= (int)$year ?></div>
    </div>
</div>
