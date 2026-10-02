<?php
/** @var list<array<string,mixed>> $shifts */
/** @var list<array<string,mixed>> $schedules */
/** @var list<array<string,mixed>> $agents */
/** @var list<array<string,mixed>> $queues */
/** @var string|null $error */
/** @var string|null $success */

$weekdays = [
    1=>'Lunes',
    2=>'Martes',
    3=>'Miércoles',
    4=>'Jueves',
    5=>'Viernes',
    6=>'Sábado',
    7=>'Domingo',
];

$csrf = \App\Auth\Csrf::token();
?>

<div class="page-heading">
    <div>
        <h1><i class="bi bi-calendar3 text-brand me-2"></i>Turnos y cronograma</h1>
        <p class="muted mb-0">
            Define los turnos operativos y determina cuándo un agente puede recibir casos en cada cola.
        </p>
    </div>
    <span class="badge text-bg-light border px-3 py-2">
        <i class="bi bi-clock me-1"></i>Zona horaria: America/Bogota
    </span>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-danger shadow-sm">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($success !== null): ?>
    <div class="alert alert-success shadow-sm">
        <i class="bi bi-check-circle me-2"></i>
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">Catálogo de turnos</h2>
                        <div class="text-muted small">Ventanas horarias reutilizables.</div>
                    </div>
                    <span class="badge rounded-pill text-bg-primary"><?= count($shifts) ?> turnos</span>
                </div>

                <?php if ($shifts !== []): ?>
                    <div class="table-responsive mb-4">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Código</th>
                                <th>Turno</th>
                                <th>Horario</th>
                                <th>Estado</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($shifts as $shift): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars((string)$shift['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td><?= htmlspecialchars((string)$shift['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars(substr((string)$shift['start_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?></strong>
                                        -
                                        <strong><?= htmlspecialchars(substr((string)$shift['end_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </td>
                                    <td>
                                        <?php if ((int)$shift['is_active'] === 1): ?>
                                            <span class="badge text-bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-muted small mb-4">
                        Aún no existen turnos. Crea el primero para poder asignarlo a agentes.
                    </div>
                <?php endif; ?>

                <form method="post" action="/admin/shifts/create" class="border-top pt-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <h3 class="h6 mb-3">Crear turno</h3>

                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label">Código</label>
                            <input class="form-control" name="code" maxlength="100"
                                   placeholder="TURNO_1" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" name="name" maxlength="180"
                                   placeholder="Turno mañana" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Inicio</label>
                            <input class="form-control" type="time" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fin</label>
                            <input class="form-control" type="time" name="end_time" required>
                        </div>
                    </div>

                    <button class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle me-1"></i>Crear turno
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">Asignar cronograma</h2>
                        <div class="text-muted small">
                            La asociación es por agente + cola + turno.
                        </div>
                    </div>
                    <span class="badge rounded-pill text-bg-light border"><?= count($schedules) ?> registros</span>
                </div>

                <?php if ($agents === [] || $queues === [] || $shifts === []): ?>
                    <div class="alert alert-warning">
                        Necesitas tener al menos un agente activo, una cola activa y un turno activo.
                    </div>
                <?php else: ?>
                    <form method="post" action="/admin/shifts/schedules/create">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Agente</label>
                                <select class="form-select" name="user_id" required>
                                    <option value="">Seleccionar agente</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= (int)$agent['id'] ?>">
                                            <?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                            (<?= htmlspecialchars((string)$agent['username'], ENT_QUOTES, 'UTF-8') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Cola</label>
                                <select class="form-select" name="queue_id" required>
                                    <option value="">Seleccionar cola</option>
                                    <?php foreach ($queues as $queue): ?>
                                        <option value="<?= (int)$queue['id'] ?>">
                                            <?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?>
                                            - <?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Turno</label>
                                <select class="form-select" name="shift_id" required>
                                    <option value="">Seleccionar turno</option>
                                    <?php foreach ($shifts as $shift): ?>
                                        <?php if ((int)$shift['is_active'] !== 1) continue; ?>
                                        <option value="<?= (int)$shift['id'] ?>">
                                            <?= htmlspecialchars((string)$shift['code'], ENT_QUOTES, 'UTF-8') ?>
                                            · <?= htmlspecialchars(substr((string)$shift['start_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                            - <?= htmlspecialchars(substr((string)$shift['end_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tipo de cronograma</label>
                                <select class="form-select" name="schedule_mode" id="scheduleMode">
                                    <option value="WEEKLY">Recurrente por día de semana</option>
                                    <option value="DATE">Fecha específica</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="weekdayField">
                                <label class="form-label">Día</label>
                                <select class="form-select" name="weekday">
                                    <?php foreach ($weekdays as $dayNumber => $dayLabel): ?>
                                        <option value="<?= $dayNumber ?>"><?= $dayLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 d-none" id="dateField">
                                <label class="form-label">Fecha específica</label>
                                <input class="form-control" type="date" name="schedule_date">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Válido desde <span class="text-muted">(opcional)</span></label>
                                <input class="form-control" type="date" name="valid_from">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Válido hasta <span class="text-muted">(opcional)</span></label>
                                <input class="form-control" type="date" name="valid_to">
                            </div>
                        </div>

                        <button class="btn btn-primary mt-3">
                            <i class="bi bi-calendar-plus me-1"></i>Asignar cronograma
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1">Cronograma configurado</h2>
                <div class="text-muted small">
                    Solo los registros activos participan en el motor de reparto.
                </div>
            </div>
        </div>

        <?php if ($schedules !== []): ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Agente</th>
                        <th>Cola</th>
                        <th>Turno</th>
                        <th>Aplicación</th>
                        <th>Vigencia</th>
                        <th>Estado</th>
                        <th class="text-end">Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <?php
                        if ($schedule['schedule_date'] !== null) {
                            $application = 'Fecha: ' . (string)$schedule['schedule_date'];
                        } else {
                            $application = $weekdays[(int)$schedule['weekday']] ?? 'Día no definido';
                        }

                        $validity = trim(
                            ((string)($schedule['valid_from'] ?? '') !== ''
                                ? (string)$schedule['valid_from']
                                : 'Inicio')
                            . ' → ' .
                            ((string)($schedule['valid_to'] ?? '') !== ''
                                ? (string)$schedule['valid_to']
                                : 'Sin fin')
                        );
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars((string)$schedule['agent_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="text-muted small"><?= htmlspecialchars((string)$schedule['agent_username'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><?= htmlspecialchars((string)$schedule['queue_code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <strong><?= htmlspecialchars((string)$schedule['shift_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="text-muted small">
                                    <?= htmlspecialchars(substr((string)$schedule['start_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                    -
                                    <?= htmlspecialchars(substr((string)$schedule['end_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($application, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small"><?= htmlspecialchars($validity, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ((int)$schedule['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="post" action="/admin/shifts/schedules/<?= (int)$schedule['id'] ?>/toggle" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <?= (int)$schedule['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                No hay cronogramas configurados.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-light border mt-3 mb-0">
    <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Regla operacional</div>
    <div class="small text-muted">
        Para recibir una petición, el agente debe seguir cumpliendo las condiciones existentes
        de activo, rol, cola, habilidades, presencia Disponible, heartbeat y capacidad.
        El cronograma agrega una condición adicional: <strong>estar dentro de un turno vigente</strong>.
    </div>
</div>

<script>
(() => {
    const mode = document.getElementById('scheduleMode');
    const weekdayField = document.getElementById('weekdayField');
    const dateField = document.getElementById('dateField');

    const refresh = () => {
        const dateMode = mode?.value === 'DATE';
        weekdayField?.classList.toggle('d-none', dateMode);
        dateField?.classList.toggle('d-none', !dateMode);
    };

    mode?.addEventListener('change', refresh);
    refresh();
})();
</script>
