<?php
/** @var list<array<string,mixed>> $shifts */
/** @var list<array<string,mixed>> $agents */
/** @var list<array<string,mixed>> $queues */
/** @var string|null $success */
/** @var string|null $error */
?>
<style>
.shift-grid{display:grid;grid-template-columns:minmax(320px,.75fr) minmax(500px,1.25fr);gap:18px}
.shift-card{background:#fff;border:1px solid rgba(33,37,41,.12);border-radius:16px;padding:18px;box-shadow:0 2px 5px rgba(0,0,0,.035)}
@media(max-width:900px){.shift-grid{grid-template-columns:1fr}}
</style>

<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Cronograma de agentes</h1>
        <div class="text-muted">Los turnos activos determinan cuándo un agente puede recibir casos automáticamente.</div>
    </div>
    <a class="btn btn-light" href="/supervisor/agents"><i class="bi bi-arrow-left me-1"></i>Estado de agentes</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="shift-grid">
    <div class="shift-card">
        <h2 class="h5 fw-bold">Crear turno</h2>
        <form method="post" action="/admin/shifts">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label class="form-label">Agente</label>
                <select name="user_id" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= (int)$agent['id'] ?>">
                            <?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?>
                            (<?= htmlspecialchars((string)$agent['username'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Cola</label>
                <select name="queue_id" class="form-select">
                    <option value="">Todas las colas del agente</option>
                    <?php foreach ($queues as $queue): ?>
                        <option value="<?= (int)$queue['id'] ?>">
                            <?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Inicio</label>
                <input type="datetime-local" name="starts_at" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Fin</label>
                <input type="datetime-local" name="ends_at" class="form-control" required>
            </div>

            <button class="btn btn-primary w-100" type="submit">
                <i class="bi bi-calendar-plus me-1"></i>Crear turno
            </button>
        </form>
    </div>

    <div class="shift-card">
        <h2 class="h5 fw-bold">Turnos activos</h2>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Agente</th>
                        <th>Cola</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($shifts as $shift): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string)$shift['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars((string)$shift['username'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><?= htmlspecialchars((string)($shift['queue_name'] ?? 'Todas'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)$shift['starts_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)$shift['ends_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end">
                            <form method="post" action="/admin/shifts/<?= (int)$shift['id'] ?>/deactivate" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Desactivar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($shifts === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay turnos activos configurados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
