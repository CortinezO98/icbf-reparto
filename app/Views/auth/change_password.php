<?php
$forced = (bool)($forced ?? false);
?>
<div class="login-wrap">
    <div class="login-col px-3">
        <div class="reparto-login-mark"><i class="bi bi-shield-lock"></i></div>
        <div class="login-title">ICBF • Módulo de Reparto</div>

        <div class="card card-login shadow-lg">
            <div class="card-header">
                <h5 class="mb-0 text-white"><i class="bi bi-key me-2"></i>
                    <?= $forced ? 'Cambio obligatorio de contraseña' : 'Cambiar contraseña' ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($warning)): ?>
                    <div class="alert alert-warning py-2"><?= htmlspecialchars((string)$warning, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if ($forced): ?>
                    <div class="alert alert-info py-2 small">
                        Esta cuenta tiene una contraseña temporal. Debes definir una contraseña propia para continuar.
                    </div>
                <?php endif; ?>

                <form method="post" action="/change-password" autocomplete="off">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

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

                    <div class="small text-muted mb-3">
                        Mínimo 12 caracteres, con mayúscula, minúscula, número y símbolo.
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-brand" type="submit">
                            <i class="bi bi-check-circle me-1"></i>Guardar contraseña
                        </button>
                    </div>
                </form>

                <?php if (!$forced): ?>
                    <div class="text-center mt-3">
                        <a href="/cases" class="small text-decoration-none">Volver</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>