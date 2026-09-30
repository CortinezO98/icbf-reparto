<?php
/** @var array<string,mixed>|null $user */
/** @var list<string> $roles */
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-speedometer2 text-brand me-2"></i>Inicio
        </h1>
        <p class="text-muted mb-0">Módulo de Reparto de Peticiones ICBF</p>
    </div>
    <span class="badge text-bg-success-subtle border border-success text-success-emphasis px-3 py-2">
        <i class="bi bi-check-circle me-1"></i>Sistema operativo
    </span>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-subtle text-primary d-grid"
                         style="width:46px;height:46px;place-items:center">
                        <i class="bi bi-person fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Usuario</div>
                        <div class="fw-semibold"><?= htmlspecialchars((string)($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis d-grid"
                         style="width:46px;height:46px;place-items:center">
                        <i class="bi bi-shield-check fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Roles</div>
                        <div class="fw-semibold"><?= htmlspecialchars(implode(', ', $roles), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success-subtle text-success d-grid"
                         style="width:46px;height:46px;place-items:center">
                        <i class="bi bi-activity fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Estado</div>
                        <div class="fw-semibold">Base técnica activa</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
