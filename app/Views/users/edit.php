<?php
/** @var array<string,mixed> $editUser */
/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $queues */
/** @var string|null $error */
$selectedRoles = array_map('intval', (array)$editUser['role_ids']);
$selectedQueues = array_map('intval', (array)$editUser['queue_ids']);
?>
<style>
.ue-wrap{max-width:900px;margin:0 auto}.ue-head{display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap}.ue-head h1{margin:0}.ue-card{background:#fff;border:1px solid #dfe3e8;border-radius:11px;box-shadow:0 4px 13px rgba(15,23,42,.06);margin-top:20px;overflow:hidden}.ue-card-head{padding:10px 16px;background:#fafbfc;border-bottom:1px solid #dfe3e8;font-weight:750}.ue-body{padding:18px}.ue-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px 18px}.ue-section{font-weight:750;border-bottom:1px solid #d6dde5;padding-bottom:8px;margin:20px 0 12px}.ue-choice-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.ue-choice{display:flex!important;gap:8px;border:1px solid #dfe3e8;border-radius:8px;padding:10px;margin:0!important}.ue-choice input{width:auto}.ue-choice span{display:flex;flex-direction:column}.ue-choice small{color:#64748b;font-weight:400}.ue-toggle{display:flex!important;gap:8px;align-items:center}.ue-toggle input{width:auto}.ue-actions{display:flex;justify-content:space-between;border-top:1px solid #e5e7eb;padding-top:14px;margin-top:18px}.ue-outline{background:#fff;border:1px solid #94a3b8;color:#475569;border-radius:7px;padding:9px 12px;text-decoration:none}.ue-green{background:#198f4c;border:1px solid #198f4c;color:#fff;border-radius:7px;padding:9px 14px;font-weight:700}.ue-alert{background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-top:15px}@media(max-width:700px){.ue-grid,.ue-choice-grid{grid-template-columns:1fr}}
</style>

<div class="ue-wrap">
    <div class="ue-head">
        <div><h1>✎ Editar Usuario</h1><div class="muted">Editando: <strong><?= htmlspecialchars((string)$editUser['full_name'], ENT_QUOTES, 'UTF-8') ?></strong> · ID <?= (int)$editUser['id'] ?></div></div>
        <a class="ue-outline" href="/admin/users">← Volver a la lista</a>
    </div>

    <?php if ($error): ?><div class="ue-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <form method="post" action="/admin/users/<?= (int)$editUser['id'] ?>/edit">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="ue-card">
            <div class="ue-card-head">⚙ Actualizar Información</div>
            <div class="ue-body">
                <div class="ue-grid">
                    <div><label>Documento *</label><input name="document_number" required value="<?= htmlspecialchars((string)$editUser['document_number'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div><label>Usuario *</label><input name="username" required value="<?= htmlspecialchars((string)$editUser['username'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div><label>Correo *</label><input type="email" name="email" required value="<?= htmlspecialchars((string)$editUser['email'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div><label>Nombre completo *</label><input name="full_name" required value="<?= htmlspecialchars((string)$editUser['full_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div><label>Nueva contraseña</label><input type="password" name="password" autocomplete="new-password"><small class="muted">Vacía para conservar la actual.</small></div>
                    <div>
                        <label>Estado y reparto</label>
                        <label class="ue-toggle"><input type="checkbox" name="is_active" value="1" <?= (int)$editUser['is_active'] === 1 ? 'checked' : '' ?>> Activo</label>
                        <label class="ue-toggle" id="assignToggle"><input type="checkbox" name="assign_enabled" value="1" <?= (int)$editUser['assign_enabled'] === 1 ? 'checked' : '' ?>> Habilitado para asignación</label>
                    </div>
                </div>

                <div class="ue-section">Roles y permisos</div>
                <div class="ue-choice-grid">
                    <?php foreach ($roles as $role): ?>
                        <label class="ue-choice">
                            <input type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>" data-role-code="<?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array((int)$role['id'], $selectedRoles, true) ? 'checked' : '' ?>>
                            <span><strong><?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$role['description'], ENT_QUOTES, 'UTF-8') ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div id="queueSection">
                    <div class="ue-section">Colas del agente</div>
                    <div class="ue-choice-grid">
                        <?php foreach ($queues as $queue): ?>
                            <label class="ue-choice">
                                <input type="checkbox" name="queue_ids[]" value="<?= (int)$queue['id'] ?>" <?= in_array((int)$queue['id'], $selectedQueues, true) ? 'checked' : '' ?>>
                                <span><strong><?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?> · capacidad <?= (int)$queue['default_capacity'] ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ue-actions">
                    <a class="ue-outline" href="/admin/users">Cancelar</a>
                    <button class="ue-green" type="submit">✓ Guardar cambios</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const queueSection=document.getElementById('queueSection');
    const assignToggle=document.getElementById('assignToggle');
    const roles=[...document.querySelectorAll('[data-role-code]')];
    const refresh=()=>{
        const isAgent=roles.some(i=>i.checked&&i.dataset.roleCode==='AGENTE');
        queueSection.style.display=isAgent?'':'none';
        assignToggle.style.display=isAgent?'flex':'none';
        queueSection.querySelectorAll('input').forEach(i=>i.disabled=!isAgent);
        if(!isAgent) document.querySelector('input[name="assign_enabled"]').checked=false;
    };
    roles.forEach(i=>i.addEventListener('change',refresh));
    refresh();
})();
</script>
