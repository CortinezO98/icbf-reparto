<?php
/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $queues */
/** @var list<array<string,mixed>> $supervisors */
/** @var string|null $error */
/** @var array<string,mixed> $old */

$old = is_array($old ?? null) ? $old : [];
$oldRoles = array_map('intval', (array)($old['role_ids'] ?? []));
$oldQueues = array_map('intval', (array)($old['queue_ids'] ?? []));
$oldAllQueues = (int)($old['all_queues'] ?? 0) === 1;
$oldSupervisor = (int)($old['supervisor_user_id'] ?? 0);
$oldActive = !array_key_exists('is_active', $old) || (int)($old['is_active'] ?? 0) === 1;
$oldAssign = (int)($old['assign_enabled'] ?? 0) === 1;
?>
<style>
.admin-user-form{max-width:760px;margin:0 auto}
.admin-sticky-actions{
    position:sticky;
    bottom:0;
    background:#fff;
    z-index:50;
    padding-top:12px;
    margin-top:16px;
    box-shadow:0 -10px 20px rgba(0,0,0,.05)
}
.admin-sticky-actions::before{
    content:'';
    position:absolute;
    left:0;right:0;top:-12px;height:12px;
    background:linear-gradient(180deg,rgba(255,255,255,0),#fff);
    pointer-events:none
}
.form-section-title{
    font-weight:600;
    border-bottom:1px solid #dee2e6;
    padding-bottom:.5rem;
    margin-bottom:1rem
}
.compact-help{font-size:.82rem;color:#6c757d}
.roles-select,.queues-select{min-height:112px}
.info-box{background:#cff4fc;border:1px solid #9eeaf9;border-radius:.5rem}
@media(max-width:767.98px){
    .admin-user-form{max-width:100%}
}
</style>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-person-plus text-primary me-2"></i>Crear Nuevo Usuario
            </h1>
            <p class="text-muted mb-0">Completa el formulario para registrar un nuevo usuario en el sistema</p>
        </div>

        <a href="/admin/users" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver a la lista
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger admin-user-form">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="admin-user-form">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="bi bi-person-fill-add me-2"></i>Datos del Usuario
                </h6>
            </div>

            <div class="card-body">
                <form method="post"
                      action="/admin/users/create"
                      id="userForm"
                      autocomplete="off"
                      novalidate>
                    <input type="hidden"
                           name="_csrf"
                           value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <h6 class="form-section-title">
                        <i class="bi bi-info-circle me-2"></i>Información Básica
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="document_number" class="form-label">
                                <i class="bi bi-card-text me-1"></i>Documento (Cédula) *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="document_number"
                                   name="document_number"
                                   maxlength="50"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   placeholder="Ej: 1012345678"
                                   value="<?= htmlspecialchars((string)($old['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                            <div class="form-text">Número de identificación único</div>
                            <div class="invalid-feedback">El documento es obligatorio.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="username" class="form-label">
                                <i class="bi bi-person-badge me-1"></i>Nombre de Usuario *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="username"
                                   name="username"
                                   minlength="3"
                                   maxlength="100"
                                   pattern="[A-Za-z0-9._-]{3,100}"
                                   autocomplete="off"
                                   placeholder="Ej: jperez"
                                   value="<?= htmlspecialchars((string)($old['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                            <div class="form-text">Será usado para iniciar sesión en el sistema</div>
                            <div class="invalid-feedback">Ingresa un usuario válido.</div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope me-1"></i>Correo Electrónico *
                            </label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   maxlength="180"
                                   autocomplete="email"
                                   placeholder="usuario@ejemplo.com"
                                   value="<?= htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                            <div class="form-text">Para identificación y contacto del usuario</div>
                            <div class="invalid-feedback">Ingresa un correo electrónico válido.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="full_name" class="form-label">
                                <i class="bi bi-person-vcard me-1"></i>Nombre Completo *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="full_name"
                                   name="full_name"
                                   maxlength="180"
                                   autocomplete="name"
                                   placeholder="Ej: Juan Pérez"
                                   value="<?= htmlspecialchars((string)($old['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                            <div class="invalid-feedback">El nombre completo es obligatorio.</div>
                        </div>
                    </div>

                    <h6 class="form-section-title mt-4">
                        <i class="bi bi-shield-lock me-2"></i>Configuración de Acceso
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="passwordField" class="form-label">
                                <i class="bi bi-key me-1"></i>Contraseña (Opcional)
                            </label>

                            <div class="input-group">
                                <input type="password"
                                       class="form-control"
                                       id="passwordField"
                                       name="password"
                                       minlength="12"
                                       maxlength="128"
                                       autocomplete="new-password"
                                       placeholder="Vacía = se genera automáticamente">
                                <button class="btn btn-outline-secondary"
                                        type="button"
                                        id="togglePassword"
                                        title="Mostrar/ocultar contraseña">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>

                            <div class="form-text">
                                Si la defines manualmente, usa al menos 12 caracteres con mayúscula, minúscula, número y símbolo.
                            </div>

                            <button class="btn btn-sm btn-outline-primary mt-2"
                                    type="button"
                                    id="generatePassword">
                                <i class="bi bi-shuffle me-1"></i>Generar contraseña segura
                            </button>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="bi bi-toggle-on me-1"></i>Estado del Usuario
                            </label>

                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="is_active_radio"
                                           id="activeYes"
                                           value="1"
                                           <?= $oldActive ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="activeYes">
                                        <span class="badge bg-success">Activo</span>
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="is_active_radio"
                                           id="activeNo"
                                           value="0"
                                           <?= !$oldActive ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="activeNo">
                                        <span class="badge bg-secondary">Inactivo</span>
                                    </label>
                                </div>
                            </div>

                            <input type="hidden"
                                   name="is_active"
                                   id="is_active_hidden"
                                   value="<?= $oldActive ? '1' : '0' ?>">

                            <div class="form-text mb-3">
                                Los usuarios inactivos no pueden iniciar sesión.
                            </div>

                            <div class="form-check form-switch" id="assignBlock">
                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       id="assign_enabled"
                                       name="assign_enabled"
                                       value="1"
                                       <?= $oldAssign ? 'checked' : '' ?>>
                                <label class="form-check-label" for="assign_enabled">
                                    <i class="bi bi-person-check me-1"></i>Habilitar para asignación
                                </label>
                            </div>
                            <div class="form-text">Permite asignarle casos automáticamente cuando esté Disponible.</div>
                        </div>
                    </div>

                    <h6 class="form-section-title mt-4">
                        <i class="bi bi-person-badge me-2"></i>Roles y Permisos
                    </h6>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="rolesSelect" class="form-label mb-0">
                            <i class="bi bi-tags me-1"></i>Roles Asignados *
                        </label>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="rolesSelectAll">Seleccionar todos</button>
                            <button type="button" class="btn btn-outline-secondary" id="rolesClear">Limpiar</button>
                        </div>
                    </div>

                    <select class="form-select roles-select"
                            id="rolesSelect"
                            name="role_ids[]"
                            multiple
                            required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int)$role['id'] ?>"
                                    data-role-code="<?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= in_array((int)$role['id'], $oldRoles, true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?>
                                — <?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">
                        Ctrl (Cmd en Mac) para seleccionar múltiples.
                    </div>

                    <div class="mt-3" id="supervisorBlock">
                        <label for="supervisor_user_id" class="form-label">
                            <i class="bi bi-person-check me-1"></i>Supervisor del agente
                        </label>
                        <select class="form-select" id="supervisor_user_id" name="supervisor_user_id">
                            <option value="">Sin supervisor asignado</option>
                            <?php foreach ($supervisors as $supervisor): ?>
                                <option value="<?= (int)$supervisor['id'] ?>" <?= $oldSupervisor === (int)$supervisor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$supervisor['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Se utiliza para organizar el equipo y filtrar la reportería.</div>
                    </div>

                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <?php foreach ($roles as $role): ?>
                            <?php
                            $roleCode = (string)$role['code'];
                            $badgeClass = match ($roleCode) {
                                'ADMIN' => 'text-bg-danger',
                                'AGENTE' => 'text-bg-primary',
                                'SUPERVISOR' => 'text-bg-warning',
                                default => 'text-bg-secondary',
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($roleCode, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div id="queueSection" class="mt-4">
                        <h6 class="form-section-title">
                            <i class="bi bi-diagram-2 me-2"></i>Colas del Agente
                        </h6>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="queuesSelect" class="form-label mb-0">
                                <i class="bi bi-inboxes me-1"></i>Colas Asignadas *
                            </label>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary" id="queuesSelectAll">
                                    Todas
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="queuesClear">
                                    Limpiar
                                </button>
                            </div>
                        </div>

                        <select class="form-select queues-select"
                                id="queuesSelect"
                                name="queue_ids[]"
                                multiple>
                            <?php foreach ($queues as $queue): ?>
                                <option value="<?= (int)$queue['id'] ?>"
                                        <?= in_array((int)$queue['id'], $oldQueues, true) || $oldAllQueues ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?>
                                    — <?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?>
                                    (cap. <?= (int)$queue['default_capacity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="hidden"
                               name="all_queues"
                               id="allQueuesHidden"
                               value="<?= $oldAllQueues ? '1' : '0' ?>">

                        <div class="form-text">
                            Las habilidades obligatorias se asignan automáticamente según las colas seleccionadas.
                        </div>
                    </div>

                    <div class="admin-sticky-actions">
                        <div class="d-flex justify-content-between gap-2 pb-2">
                            <a href="/admin/users" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Cancelar
                            </a>

                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="bi bi-check-circle me-1"></i>Crear Usuario
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="info-box p-3 mt-3">
            <div class="fw-semibold mb-2">
                <i class="bi bi-info-circle-fill me-2"></i>Información importante
            </div>
            <ul class="mb-0 small">
                <li>El sistema validará que usuario, email y documento sean únicos.</li>
                <li>Si no especificas contraseña, se generará una temporal automáticamente.</li>
                <li>Los agentes deben tener al menos una cola asignada.</li>
                <li>La habilitación para reparto no reemplaza el estado Disponible del agente.</li>
            </ul>
        </div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('userForm');
    const passwordInput = document.getElementById('passwordField');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const generatePasswordBtn = document.getElementById('generatePassword');
    const rolesSelect = document.getElementById('rolesSelect');
    const rolesSelectAll = document.getElementById('rolesSelectAll');
    const rolesClear = document.getElementById('rolesClear');
    const queueSection = document.getElementById('queueSection');
    const queuesSelect = document.getElementById('queuesSelect');
    const queuesSelectAll = document.getElementById('queuesSelectAll');
    const queuesClear = document.getElementById('queuesClear');
    const allQueuesHidden = document.getElementById('allQueuesHidden');
    const assignBlock = document.getElementById('assignBlock');
    const assignEnabled = document.getElementById('assign_enabled');
    const supervisorBlock = document.getElementById('supervisorBlock');
    const supervisorSelect = document.getElementById('supervisor_user_id');
    const activeYes = document.getElementById('activeYes');
    const activeNo = document.getElementById('activeNo');
    const activeHidden = document.getElementById('is_active_hidden');
    const submitBtn = document.getElementById('submitBtn');

    const selectedRoleCodes = () =>
        [...rolesSelect.selectedOptions].map(o => o.dataset.roleCode || '');

    const isAgent = () => selectedRoleCodes().includes('AGENTE');

    const refreshAgentFields = () => {
        const agent = isAgent();
        queueSection.style.display = agent ? '' : 'none';
        assignBlock.style.display = agent ? '' : 'none';
        if (supervisorBlock) supervisorBlock.style.display = agent ? '' : 'none';
        queuesSelect.disabled = !agent;
        assignEnabled.disabled = !agent;
        if (supervisorSelect) supervisorSelect.disabled = !agent;

        if (!agent) {
            assignEnabled.checked = false;
            if (supervisorSelect) supervisorSelect.value = '';
        }
    };

    const syncAllQueuesFlag = () => {
        const selected = [...queuesSelect.options].filter(o => o.selected).length;
        allQueuesHidden.value =
            selected > 0 && selected === queuesSelect.options.length ? '1' : '0';
    };

    togglePasswordBtn?.addEventListener('click', () => {
        const visible = passwordInput.type === 'text';
        passwordInput.type = visible ? 'password' : 'text';
        togglePasswordBtn.innerHTML = visible
            ? '<i class="bi bi-eye"></i>'
            : '<i class="bi bi-eye-slash"></i>';
    });

    generatePasswordBtn?.addEventListener('click', () => {
        const lower = 'abcdefghijkmnopqrstuvwxyz';
        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const digits = '23456789';
        const symbols = '!@#$%&*?';
        const all = lower + upper + digits + symbols;

        const pick = chars => {
            const data = new Uint32Array(1);
            crypto.getRandomValues(data);
            return chars[data[0] % chars.length];
        };

        const chars = [pick(lower), pick(upper), pick(digits), pick(symbols)];

        while (chars.length < 16) chars.push(pick(all));

        for (let i = chars.length - 1; i > 0; i--) {
            const data = new Uint32Array(1);
            crypto.getRandomValues(data);
            const j = data[0] % (i + 1);
            [chars[i], chars[j]] = [chars[j], chars[i]];
        }

        passwordInput.value = chars.join('');
        passwordInput.type = 'text';
        togglePasswordBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    });

    rolesSelectAll?.addEventListener('click', () => {
        [...rolesSelect.options].forEach(o => o.selected = true);
        refreshAgentFields();
    });

    rolesClear?.addEventListener('click', () => {
        [...rolesSelect.options].forEach(o => o.selected = false);
        refreshAgentFields();
    });

    rolesSelect?.addEventListener('change', refreshAgentFields);

    queuesSelectAll?.addEventListener('click', () => {
        [...queuesSelect.options].forEach(o => o.selected = true);
        syncAllQueuesFlag();
    });

    queuesClear?.addEventListener('click', () => {
        [...queuesSelect.options].forEach(o => o.selected = false);
        syncAllQueuesFlag();
    });

    queuesSelect?.addEventListener('change', syncAllQueuesFlag);

    activeYes?.addEventListener('change', () => {
        if (activeYes.checked) activeHidden.value = '1';
    });

    activeNo?.addEventListener('change', () => {
        if (activeNo.checked) activeHidden.value = '0';
    });

    form?.addEventListener('submit', event => {
        refreshAgentFields();
        syncAllQueuesFlag();

        if (rolesSelect.selectedOptions.length === 0) {
            event.preventDefault();
            rolesSelect.setCustomValidity('Selecciona al menos un rol.');
        } else {
            rolesSelect.setCustomValidity('');
        }

        if (isAgent() && queuesSelect.selectedOptions.length === 0) {
            event.preventDefault();
            queuesSelect.setCustomValidity('Selecciona al menos una cola.');
        } else {
            queuesSelect.setCustomValidity('');
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');
            form.querySelector(':invalid')?.scrollIntoView({behavior:'smooth', block:'center'});
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creando usuario...';
    });

    refreshAgentFields();
    syncAllQueuesFlag();
})();
</script>
