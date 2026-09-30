<?php
/** @var array<string,int> $summary */
/** @var list<array<string,mixed>> $alerts */
/** @var array<string,mixed> $policy */
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-speedometer2 text-primary me-2"></i>Tablero ANS
            </h1>
            <p class="text-muted mb-0">
                Seguimiento del tiempo hábil y alertas operativas de los casos.
            </p>
        </div>
        <span class="badge text-bg-light border px-3 py-2">
            <?= htmlspecialchars((string)$policy['name'], ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg">
            <div class="card shadow-sm h-100 border-success-subtle">
                <div class="card-body">
                    <div class="text-muted small">Verde</div>
                    <div class="fs-3 fw-bold text-success"><?= (int)$summary['green'] ?></div>
                    <div class="small text-muted">Menos de 2 h</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card shadow-sm h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="text-muted small">Amarillo</div>
                    <div class="fs-3 fw-bold text-warning-emphasis"><?= (int)$summary['yellow'] ?></div>
                    <div class="small text-muted">2 h a &lt;5 h</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card shadow-sm h-100 border-danger-subtle">
                <div class="card-body">
                    <div class="text-muted small">Rojo</div>
                    <div class="fs-3 fw-bold text-danger"><?= (int)$summary['red'] ?></div>
                    <div class="small text-muted">5 h a &lt;6 h</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card shadow-sm h-100 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Vencidos</div>
                    <div class="fs-3 fw-bold text-danger"><?= (int)$summary['breached'] ?></div>
                    <div class="small text-muted">6 h o más</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Alertas abiertas</div>
                    <div class="fs-3 fw-bold"><?= (int)$summary['alerts'] ?></div>
                    <div class="small text-muted">Requieren revisión</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bi bi-bell me-2"></i>Alertas operativas
            </h6>
            <span class="badge bg-secondary"><?= count($alerts) ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Severidad</th>
                    <th>Caso</th>
                    <th>Cola</th>
                    <th>Agente</th>
                    <th>ANS</th>
                    <th>Alerta</th>
                    <th>Vence</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($alerts as $alert): ?>
                    <?php
                    $severityClass = match ((string)$alert['severity']) {
                        'CRITICAL' => 'bg-danger',
                        'WARNING' => 'bg-warning text-dark',
                        default => 'bg-info text-dark',
                    };

                    $slaClass = match ((string)$alert['sla_status']) {
                        'GREEN' => 'bg-success',
                        'YELLOW' => 'bg-warning text-dark',
                        'RED', 'BREACHED' => 'bg-danger',
                        default => 'bg-secondary',
                    };
                    ?>
                    <tr>
                        <td>
                            <span class="badge <?= $severityClass ?>">
                                <?= htmlspecialchars((string)$alert['severity'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars((string)$alert['case_number'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <div class="text-muted small">
                                <?= htmlspecialchars((string)$alert['external_key'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string)($alert['queue_code'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($alert['assigned_user_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge <?= $slaClass ?>">
                                <?= htmlspecialchars((string)($alert['sla_status'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <div class="text-muted small">
                                <?= (int)($alert['sla_elapsed_minutes'] ?? 0) ?> min hábiles
                            </div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars((string)$alert['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <div class="text-muted small">
                                <?= htmlspecialchars((string)$alert['message'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string)($alert['sla_due_at'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary"
                               href="/cases/<?= (int)$alert['case_id'] ?>">
                                <i class="bi bi-eye me-1"></i>Ver
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($alerts === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>
                            No hay alertas abiertas.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-light border mt-3 small">
        <i class="bi bi-clock-history me-1"></i>
        Jornada configurada:
        <strong><?= htmlspecialchars(substr((string)$policy['business_start'], 0, 5), ENT_QUOTES, 'UTF-8') ?></strong>
        a
        <strong><?= htmlspecialchars(substr((string)$policy['business_end'], 0, 5), ENT_QUOTES, 'UTF-8') ?></strong>,
        lunes a viernes.
        Objetivo:
        <strong><?= (int)$policy['target_minutes'] / 60 ?> horas hábiles</strong>.
    </div>
</div>
