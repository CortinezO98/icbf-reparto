<?php
/** @var array<string,mixed> $editUser */
/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $queues */
/** @var string|null $error */

$selectedRoles = array_map('intval', (array)($editUser['role_ids'] ?? []));
$selectedQueues = array_map('intval', (array)($editUser['queue_ids'] ?? []));
$activeQueueIds = array_map(
    static fn(array $queue): int => (int)$queue['id'],
    $queues
);
$allQueuesSelected = $activeQueueIds !== []
    && count(array_diff($activeQueueIds, $selectedQueues)) === 0;
?>
<style>
.admin-user-form{max-width:760px;margin:0 auto}
.form-section-title{font-weight:600;border-bottom:1px solid #dee2e6;padding-bottom:.5rem;margin-bottom:1rem}
.roles-select,.queues-select{min-height:112px}
</style>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-pencil-square text-primary me-2"></i>Editar Usuario
            </h1>
            <p class="text-muted mb-0">
                Editando: <strong><?= htmlspecialchars((string)$editUser['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </div>

        <a href="/admin/users" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver a la lista
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger admin-user-form">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success admin-user-form">
            <?= htmlspecialchars((string)$success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="admin-user-form">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-person-gear me-2"></i>Actualizar Información</h6>
            </div>

            <div class="card-body">
                <form method="post" action="/admin/users/<?= (int)$editUser['id'] ?>/edit" id="editForm">
                    <input type="hidden" name="_csrf"
                           value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <h6 class="form-section-title">
                        <i class="bi bi-info-circle me-2"></i>Información Básica
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Documento *</label>
                            <input class="form-control" name="document_number" maxlength="50" required
                                   value="<?= htmlspecialchars((string)$editUser['document_number'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre de Usuario *</label>
                            <input class="form-control" name="username" maxlength="100" required
                                   value="<?= htmlspecialchars((string)$editUser['username'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo Electrónico *</label>
                            <input class="form-control" type="email" name="email" maxlength="180" required
                                   value="<?= htmlspecialchars((string)$editUser['email'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre Completo *</label>
                            <input class="form-control" name="full_name" maxlength="180" required
                                   value="<?= htmlspecialchars((string)$editUser['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <h6 class="form-section-title mt-4">
                        <i class="bi bi-shield-lock me-2"></i>Configuración de Acceso
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nueva Contraseña</label>
                            <input class="form-control" type="password" name="password"
                                   maxlength="128" autocomplete="new-password"
                                   placeholder="Vacía para conservar la actual">
                            <div class="form-text">
                                Si defines una nueva contraseña desde administración, se tratará como temporal y el usuario deberá cambiarla al ingresar.
                            </div>

                            <button type="submit"
                                    class="btn btn-sm btn-outline-warning mt-2"
                                    formaction="/admin/users/<?= (int)$editUser['id'] ?>/reset-temporary-password"
                                    formmethod="post"
                                    onclick="return confirm('¿Deseas generar una nueva contraseña temporal para este usuario?');">
                                <i class="bi bi-arrow-repeat me-1"></i>Renovar contraseña temporal
                            </button>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox"
                                       id="is_active" name="is_active" value="1"
                                       <?= (int)$editUser['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Usuario activo</label>
                            </div>

                            <div class="form-check form-switch" id="assignBlock">
                                <input class="form-check-input" type="checkbox"
                                       id="assign_enabled" name="assign_enabled" value="1"
                                       <?= (int)$editUser['assign_enabled'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="assign_enabled">Habilitar para asignación</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="form-section-title mt-4">
                        <i class="bi bi-person-badge me-2"></i>Roles y Permisos
                    </h6>

                    <select class="form-select roles-select" id="rolesSelect" name="role_ids[]" multiple required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int)$role['id'] ?>"
                                    data-role-code="<?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= in_array((int)$role['id'], $selectedRoles, true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$role['code'], ENT_QUOTES, 'UTF-8') ?>
                                — <?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="queueSection" class="mt-4">
                        <h6 class="form-section-title">
                            <i class="bi bi-diagram-2 me-2"></i>Colas del Agente
                        </h6>

                        <div class="d-flex justify-content-end gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="queuesSelectAll">Todas</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="queuesClear">Limpiar</button>
                        </div>

                        <select class="form-select queues-select" id="queuesSelect" name="queue_ids[]" multiple>
                            <?php foreach ($queues as $queue): ?>
                                <option value="<?= (int)$queue['id'] ?>"
                                        <?= in_array((int)$queue['id'], $selectedQueues, true) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?>
                                    — <?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?>
                                    (cap. <?= (int)$queue['default_capacity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="hidden"
                               name="all_queues"
                               id="allQueuesHidden"
                               value="<?= $allQueuesSelected ? '1' : '0' ?>">
                    </div>

                    <div class="d-flex justify-content-between gap-2 mt-4">
                        <a href="/admin/users" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const rolesSelect = document.getElementById('rolesSelect');
    const queueSection = document.getElementById('queueSection');
    const queuesSelect = document.getElementById('queuesSelect');
    const queuesSelectAll = document.getElementById('queuesSelectAll');
    const queuesClear = document.getElementById('queuesClear');
    const allQueuesHidden = document.getElementById('allQueuesHidden');
    const assignBlock = document.getElementById('assignBlock');
    const assignEnabled = document.getElementById('assign_enabled');

    const selectedRoleCodes = () =>
        [...rolesSelect.selectedOptions].map(o => o.dataset.roleCode || '');

    const refresh = () => {
        const agent = selectedRoleCodes().includes('AGENTE');
        queueSection.style.display = agent ? '' : 'none';
        assignBlock.style.display = agent ? '' : 'none';
        queuesSelect.disabled = !agent;
        assignEnabled.disabled = !agent;

        if (!agent) assignEnabled.checked = false;
    };

    const syncAll = () => {
        const selected = [...queuesSelect.options].filter(o => o.selected).length;
        allQueuesHidden.value =
            selected > 0 && selected === queuesSelect.options.length ? '1' : '0';
    };

    rolesSelect.addEventListener('change', refresh);

    queuesSelectAll.addEventListener('click', () => {
        [...queuesSelect.options].forEach(o => o.selected = true);
        syncAll();
    });

    queuesClear.addEventListener('click', () => {
        [...queuesSelect.options].forEach(o => o.selected = false);
        syncAll();
    });

    queuesSelect.addEventListener('change', syncAll);

    refresh();
    syncAll();
})();
</script>