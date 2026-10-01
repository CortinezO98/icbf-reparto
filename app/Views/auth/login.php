<?php
$year = date('Y');
?>
<div class="login-wrap">
    <div class="login-col px-3">
        <div class="reparto-login-mark animate__animated animate__fadeInDown" aria-hidden="true">
            <i class="bi bi-diagram-3"></i>
        </div>

        <div class="login-title animate__animated animate__fadeInDown">
            ICBF • Módulo de Reparto
        </div>

        <div class="card card-login shadow-lg animate__animated animate__fadeInUp">
            <div class="card-header">
                <h5 class="mb-0 text-white">
                    <i class="bi bi-person-lock me-2"></i>Inicio de Sesión
                </h5>
            </div>

            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/login" autocomplete="off">
                    <input type="hidden"
                           name="_csrf"
                           value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="login" class="form-label">Usuario o correo</label>
                        <input id="login"
                               class="form-control"
                               name="login"
                               type="text"
                               placeholder="Ingresa tu usuario"
                               autocomplete="username"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input id="password"
                               class="form-control"
                               name="password"
                               type="password"
                               placeholder="********"
                               autocomplete="current-password"
                               required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-brand">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Ingresar
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="/forgot-password">¿Olvidaste tu contraseña?</a>
                </div>
            </div>
        </div>

        <div class="login-footer">
            ICBF Reparto • <?= (int)$year ?>
        </div>
    </div>
</div>
