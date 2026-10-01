<?php
$tokenValue = (string)($token ?? '');
?>
<div class="login-wrap">
    <div class="login-col px-3">
        <div class="reparto-login-mark"><i class="bi bi-shield-check"></i></div>
        <div class="login-title">ICBF • Módulo de Reparto</div>

        <div class="card card-login shadow-lg">
            <div class="card-header">
                <h5 class="mb-0 text-white"><i class="bi bi-lock me-2"></i>Nueva contraseña</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <p class="text-muted small">
                    La nueva contraseña debe tener al menos 12 caracteres, mayúscula, minúscula, número y símbolo.
                </p>

                <form method="post" action="/reset-password" autocomplete="off">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(AppAuthCsrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($tokenValue, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <input id="password" class="form-control" name="password" type="password"
                               minlength="12" maxlength="128" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar contraseña</label>
                        <input id="password_confirm" class="form-control" name="password_confirm" type="password"
                               minlength="12" maxlength="128" autocomplete="new-password" required>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-brand" type="submit">
                            <i class="bi bi-check-circle me-1"></i>Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
