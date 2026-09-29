<?php
/** @var array<string,mixed>|null $user */
/** @var list<string> $roles */
?>
<div class="card">
    <h1 style="margin-top:0">Fase 1 - Fundación</h1>
    <p>La aplicación está operativa y autenticada.</p>

    <div class="grid" style="margin-top:20px">
        <div class="card">
            <strong>Usuario</strong>
            <p><?= htmlspecialchars((string)($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="card">
            <strong>Roles</strong>
            <p><?= htmlspecialchars(implode(', ', $roles), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="card">
            <strong>Estado</strong>
            <p>Base técnica activa</p>
        </div>
    </div>
</div>
