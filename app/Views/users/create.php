<?php
/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $queues */
/** @var string|null $error */
/** @var array<string,mixed> $old */
$old = is_array($old ?? null) ? $old : [];
$oldRoles = array_map('intval', (array)($old['role_ids'] ?? []));
$oldQueues = array_map('intval', (array)($old['queue_ids'] ?? []));
?>
<style>
.uc-wrap{max-width:900px;margin:0 auto}.uc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;flex-wrap:wrap}.uc-head h1{margin:0}.uc-card{background:#fff;border:1px solid #dfe3e8;border-radius:11px;box-shadow:0 4px 13px rgba(15,23,42,.06);margin-top:20px;overflow:hidden}.uc-card-head{padding:10px 16px;background:#fafbfc;border-bottom:1px solid #dfe3e8;font-weight:750}.uc-body{padding:18px}.uc-section{font-weight:750;border-bottom:1px solid #d6dde5;padding-bottom:8px;margin:0 0 14px}.uc-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px 18px}.uc-field small{display:block;color:#64748b;margin-top:5px}.uc-input-row{display:flex;gap:7px}.uc-input-row input{flex:1}.uc-outline{background:#fff;border:1px solid #94a3b8;color:#475569;border-radius:7px;padding:9px 12px;text-decoration:none;cursor:pointer}.uc-green{background:#198f4c;border:1px solid #198f4c;color:#fff;border-radius:7px;padding:9px 14px;font-weight:700;cursor:pointer}.uc-toggle{display:flex;align-items:center;gap:10px;margin-top:8px}.uc-toggle input{width:auto}.uc-role-grid,.uc-queue-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.uc-choice{display:flex!important;align-items:flex-start;gap:8px;border:1px solid #dfe3e8;border-radius:8px;padding:10px;margin:0!important;cursor:pointer}.uc-choice:hover{border-color:#5ab064;background:#f6fbf7}.uc-choice input{width:auto;margin-top:3px}.uc-choice span{display:flex;flex-direction:column}.uc-choice small{font-weight:400;color:#64748b}.uc-actions{display:flex;justify-content:space-between;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid #e5e7eb}.uc-alert{background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-top:15px}.uc-info{background:#dff7ff;color:#0f5f70;padding:14px;border-radius:8px;margin-top:18px}@media(max-width:700px){.uc-grid,.uc-role-grid,.uc-queue-grid{grid-template-columns:1fr}}
</style>

<div class="uc-wrap">
    <div class="uc-head">
        <div><h1>⊕ Crear Nuevo Usuario</h1><div class="muted">Completa el formulario para registrar un nuevo usuario en el sistema.</div></div>
        <a class="uc-outline" href="/admin/users">← Volver a la lista</a>
    </div>

    <?php if ($error): ?><div class="uc-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <form method="post" action="/admin/users/create" autocomplete="off" id="createUserForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="uc-card">
            <div class="uc-card-head">♣ Datos del Usuario</div>
            <div class="uc-body">
                <div class="uc-section">ⓘ Información Básica</div>
                <div class="uc-grid">
                    <div class="uc-field"><label>Documento (Cédula) *</label><input name="document_number" required value="<?= htmlspecialchars((string)($old['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><small>Número de identificación único.</small></div>
                    <div class="uc-field"><label>Nombre de Usuario *</label><input name="username" required value="<?= htmlspecialchars((string)($old['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><small>Será usado para iniciar sesión.</small></div>
                    <div class="uc-field"><label>Correo Electrónico *</label><input type="email" name="email" required value="<?= htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><small>Debe ser único en el sistema.</small></div>
                    <div class="uc-field"><label>Nombre Completo *</label><input name="full_name" required value="<?= htmlspecialchars((string)($old['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>

                <div class="uc-section" style="margin-top:22px">⚿ Configuración de Acceso</div>
                <div class="uc-grid">
                    <div class="uc-field">
                        <label>Contraseña (Opcional)</label>
                        <div class="uc-input-row">
                            <input id="passwordField" type="password" name="password" autocomplete="new-password" placeholder="Vacía = se genera automáticamente">
                            <button class="uc-outline" type="button" id="togglePassword">◉</button>
                        </div>
                        <small>Mínimo 12 caracteres con mayúscula, minúscula, número y símbolo.</small>
                        <button class="uc-outline" type="button" id="generatePassword" style="margin-top:7px">⤨ Generar contraseña segura</button>
                    </div>

                    <div>
                        <label>Estado del Usuario</label>
                        <label class="uc-toggle"><input type="checkbox" name="is_active" value="1" checked> <strong>Activo</strong></label>
                        <small class="muted">Los usuarios inactivos no pueden iniciar sesión.</small>

                        <label class="uc-toggle" id="assignToggle"><input type="checkbox" name="assign_enabled" value="1" checked> <strong>Habilitar para asignación</strong></label>
                        <small class="muted">Solo aplica a usuarios con rol AGENTE.</small>
                    </div>
                </div>

                <div class="uc-section" style="margin-top:22px">▣ Roles y Permisos</div>
                <div class="uc-role-grid">
                    <?php foreach ($roles as $role): ?>
                        <label class="uc-choice">
                            <input type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>"
                                   data-role-code="<?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?>"
                                   <?= in_array((int)$role['id'], $oldRoles, true) ? 'checked' : '' ?>>
                            <span><strong><?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$role['description'], ENT_QUOTES, 'UTF-8') ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div id="queueSection">
                    <div class="uc-section" style="margin-top:22px">⇄ Colas del Agente</div>
                    <div class="uc-queue-grid">
                        <?php foreach ($queues as $queue): ?>
                            <label class="uc-choice">
                                <input type="checkbox" name="queue_ids[]" value="<?= (int)$queue['id'] ?>" <?= in_array((int)$queue['id'], $oldQueues, true) ? 'checked' : '' ?>>
                                <span><strong><?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?> · Capacidad <?= (int)$queue['default_capacity'] ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="muted">Las habilidades obligatorias se asignan automáticamente según las colas seleccionadas.</small>
                </div>

                <div class="uc-actions">
                    <a class="uc-outline" href="/admin/users">⊗ Cancelar</a>
                    <button class="uc-green" type="submit">✓ Crear Usuario</button>
                </div>
            </div>
        </div>

        <div class="uc-info"><strong>ⓘ Información importante</strong><br>Documento, usuario y correo deben ser únicos. Si no especificas contraseña, el sistema generará una temporal. Los agentes deben tener al menos una cola y solo reciben casos cuando estén en estado Disponible.</div>
    </form>
</div>

<script>
(() => {
    const pass = document.getElementById('passwordField');
    const toggle = document.getElementById('togglePassword');
    const generate = document.getElementById('generatePassword');
    const queueSection = document.getElementById('queueSection');
    const assignToggle = document.getElementById('assignToggle');
    const roleInputs = [...document.querySelectorAll('[data-role-code]')];

    toggle?.addEventListener('click', () => {
        pass.type = pass.type === 'password' ? 'text' : 'password';
    });

    generate?.addEventListener('click', () => {
        const lower='abcdefghijkmnopqrstuvwxyz', upper='ABCDEFGHJKLMNPQRSTUVWXYZ', digits='23456789', symbols='!@#$%&*?';
        const all=lower+upper+digits+symbols;
        const pick=s=>s[crypto.getRandomValues(new Uint32Array(1))[0]%s.length];
        let chars=[pick(lower),pick(upper),pick(digits),pick(symbols)];
        while(chars.length<16) chars.push(pick(all));
        for(let i=chars.length-1;i>0;i--){const j=crypto.getRandomValues(new Uint32Array(1))[0]%(i+1);[chars[i],chars[j]]=[chars[j],chars[i]];}
        pass.value=chars.join('');
        pass.type='text';
    });

    const refresh = () => {
        const isAgent = roleInputs.some(i => i.checked && i.dataset.roleCode === 'AGENTE');
        queueSection.style.display = isAgent ? '' : 'none';
        assignToggle.style.display = isAgent ? 'flex' : 'none';
        queueSection.querySelectorAll('input').forEach(i => i.disabled = !isAgent);
        if (!isAgent) document.querySelector('input[name="assign_enabled"]').checked = false;
    };
    roleInputs.forEach(i => i.addEventListener('change', refresh));
    refresh();
})();
</script>
