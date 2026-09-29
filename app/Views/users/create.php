<?php
/** @var list<array<string,mixed>> $roles */
/** @var string|null $error */
?>
<div class="card" style="max-width:760px">
    <h1 style="margin-top:0">Crear usuario</h1>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/users/create" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <label>Documento</label>
        <input name="document_number" required maxlength="50">

        <label>Usuario</label>
        <input name="username" required maxlength="100">

        <label>Nombre completo</label>
        <input name="full_name" required maxlength="180">

        <label>Correo</label>
        <input name="email" type="email" required maxlength="180">

        <label>Contraseña inicial</label>
        <input name="password" type="password" required autocomplete="new-password">
        <p class="muted">Mínimo 12 caracteres, mayúscula, minúscula, número y símbolo.</p>

        <label>Roles</label>
        <?php foreach ($roles as $role): ?>
            <label style="font-weight:400">
                <input type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>" style="width:auto">
                <?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?>
            </label>
        <?php endforeach; ?>

        <div style="margin-top:18px">
            <button class="btn btn-primary" type="submit">Crear usuario</button>
            <a class="btn btn-light" href="/admin/users">Cancelar</a>
        </div>
    </form>
</div>
