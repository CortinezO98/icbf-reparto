<div class="card" style="max-width:460px;margin:70px auto">
    <h1 style="margin-top:0">Iniciar sesión</h1>
    <p class="muted">Módulo de Reparto de Peticiones</p>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/login" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <label for="login">Usuario o correo</label>
        <input id="login" name="login" required autocomplete="username">

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        <button class="btn btn-primary" type="submit" style="margin-top:18px;width:100%">Ingresar</button>
    </form>
</div>
