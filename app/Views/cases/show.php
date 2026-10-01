<?php
/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $managements */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $managementTypes */
/** @var list<array<string,mixed>> $escalations */
/** @var list<array<string,mixed>> $petitionTypes */
/** @var bool $canManage */
/** @var bool $canReassign */
/** @var list<array<string,mixed>> $reassignmentCandidates */
/** @var string|null $success */
/** @var string|null $error */

$source = json_decode((string)($case['source_normalized_json'] ?? ''), true);
$source = is_array($source) ? $source : [];

$slaStatus = (string)($case['sla_status'] ?? '');
$slaClass = match ($slaStatus) {
    'GREEN' => 'text-bg-success',
    'YELLOW' => 'text-bg-warning',
    'RED', 'BREACHED' => 'text-bg-danger',
    default => 'text-bg-secondary',
};
$stateLabel = match ((string)($case['current_state'] ?? '')) {
    'PENDING_ASSIGNMENT' => 'Pendiente de asignación',
    'ASSIGNED' => 'Asignado',
    'CLOSED' => 'Cerrado',
    default => (string)($case['current_state'] ?? 'Sin estado'),
};
$elapsedMinutes = $case['sla_elapsed_minutes'] ?? null;
$elapsedLabel = $elapsedMinutes === null || $elapsedMinutes === ''
    ? '—'
    : ((float)$elapsedMinutes < 60
        ? number_format((float)$elapsedMinutes, 0, ',', '.') . ' min'
        : number_format((float)$elapsedMinutes / 60, 1, ',', '.') . ' h');
?>
<style>
.case-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start}.case-head h1{margin:0;font-weight:800}.case-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:18px;margin-top:18px}.case-card{background:#fff;border:1px solid rgba(33,37,41,.12);border-radius:16px;padding:18px;box-shadow:0 2px 5px rgba(0,0,0,.035)}.case-card h2{font-size:1rem;font-weight:800;margin-bottom:14px}.data-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.datum{background:#f8f9fa;border:1px solid #edf0f2;border-radius:10px;padding:11px}.datum small{display:block;color:#6c757d;font-size:.75rem}.datum strong{display:block;margin-top:3px}.timeline{display:grid;gap:10px}.timeline-item{border-left:3px solid var(--color-primary);padding:10px 0 10px 13px;background:#fbfcfd;border-radius:0 8px 8px 0}.flash-ok{padding:12px;background:#d1e7dd;color:#0f5132;border:1px solid #badbcc;border-radius:10px;margin:14px 0}.flash-err{padding:12px;background:#f8d7da;color:#842029;border:1px solid #f5c2c7;border-radius:10px;margin:14px 0}.case-status-card{background:#f8f9fa;border:1px solid #edf0f2;border-radius:12px;padding:14px}.case-status-card .label{font-size:.75rem;color:#6c757d}.case-status-card .value{font-weight:800;font-size:1.1rem;margin-top:2px}.management-form .form-label{font-weight:700;font-size:.85rem}@media(max-width:850px){.case-grid,.data-grid{grid-template-columns:1fr}}
</style>

<div class="case-head">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h1><?= htmlspecialchars((string)$case['case_number'], ENT_QUOTES, 'UTF-8') ?></h1>
            <span class="badge <?= $slaClass ?>"><?= htmlspecialchars($slaStatus !== '' ? $slaStatus : 'ANS pendiente', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="badge text-bg-light border"><?= htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="muted mt-1">Radicado/SIM: <?= htmlspecialchars((string)$case['external_key'], ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/sla"><i class="bi bi-speedometer2 me-1"></i>Tablero ANS</a>
        <a class="btn btn-light" href="/cases"><i class="bi bi-arrow-left me-1"></i>Volver a casos</a>
    </div>
</div>

<?php if ($success): ?><div class="flash-ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="case-grid">
    <div>
        <div class="case-card">
            <h2>Información del caso</h2>
            <div class="row g-2 mb-3">
                <div class="col-md-4"><div class="case-status-card"><div class="label">Tiempo ANS consumido</div><div class="value"><?= htmlspecialchars($elapsedLabel, ENT_QUOTES, 'UTF-8') ?></div></div></div>
                <div class="col-md-4"><div class="case-status-card"><div class="label">Vencimiento ANS</div><div class="value small"><?= htmlspecialchars((string)($case['sla_due_at'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
                <div class="col-md-4"><div class="case-status-card"><div class="label">Primera gestión</div><div class="value small"><?= htmlspecialchars((string)($case['first_management_at'] ?: 'Pendiente'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
            </div>
            <div class="data-grid">
                <div class="datum"><small>Estado</small><strong><?= htmlspecialchars((string)$case['current_state'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Cola</small><strong><?= htmlspecialchars((string)($case['queue_name'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Agente</small><strong><?= htmlspecialchars((string)($case['assigned_user_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Tipo petición</small><strong><?= htmlspecialchars((string)($case['petition_type'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Regional</small><strong><?= htmlspecialchars((string)($case['regional'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Canal origen</small><strong><?= htmlspecialchars((string)($case['origin_channel'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Radicación</small><strong><?= htmlspecialchars((string)($case['radicated_at'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Última gestión</small><strong><?= htmlspecialchars((string)($case['current_management_type_code'] ?: 'Sin gestión'), ENT_QUOTES, 'UTF-8') ?></strong></div>
            </div>

            <?php if ($source !== []): ?>
                <h3>Datos de origen</h3>
                <div class="data-grid">
                    <?php foreach ($source as $key=>$value): ?>
                        <div class="datum"><small><?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?></small><strong><?= htmlspecialchars(is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="case-card" style="margin-top:18px">
            <h2 style="margin-top:0">Historial de gestión</h2>
            <div class="timeline">
                <?php foreach ($managements as $m): ?>
                    <div class="timeline-item">
                        <strong><?= htmlspecialchars((string)$m['management_type_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if (!empty($m['escalation_category_code'])): ?> · <?= htmlspecialchars((string)$m['escalation_category_code'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        <div class="muted"><?= htmlspecialchars((string)$m['actor_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$m['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if (!empty($m['observation'])): ?><div><?= nl2br(htmlspecialchars((string)$m['observation'], ENT_QUOTES, 'UTF-8')) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($managements === []): ?><div class="muted">El caso todavía no tiene gestiones.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <?php if ($canManage): ?>
            <div class="case-card">
                <h2 style="margin-top:0">Registrar gestión</h2>
                <form class="management-form" method="post" action="/cases/<?= (int)$case['id'] ?>/manage" enctype="multipart/form-data" id="managementForm">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label>Tipo de gestión *</label>
                    <select name="management_type_code" id="managementType" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($managementTypes as $item): ?><option value="<?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>

                    <div id="escalationBlock" style="display:none">
                        <label>Categoría de escalamiento *</label>
                        <select name="escalation_category_code">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($escalations as $item): ?><option value="<?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <div id="petitionChangeBlock" style="display:none">
                        <label>Nuevo tipo de petición *</label>
                        <select name="new_petition_type">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($petitionTypes as $item): ?><option value="<?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <label>Observación</label>
                    <textarea name="observation" rows="5" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px"></textarea>

                    <label>Soporte</label>
                    <input type="file" name="support" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <small class="muted">Máximo 10 MB.</small>

                    <button class="btn btn-primary" type="submit" style="margin-top:14px"><i class="bi bi-check-circle me-1"></i>Guardar gestión</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($canReassign && (string)($case['current_state'] ?? '') !== 'CLOSED'): ?>
            <div class="case-card" style="margin-top:18px">
                <h2 style="margin-top:0">Reasignar caso</h2>
                <p class="muted small">
                    Solo se muestran agentes disponibles, con capacidad y habilitados para la cola y habilidades del caso.
                </p>
                <?php if ($reassignmentCandidates !== []): ?>
                    <form method="post" action="/cases/<?= (int)$case['id'] ?>/reassign">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <label class="form-label">Agente destino</label>
                        <select name="target_user_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($reassignmentCandidates as $candidate): ?>
                                <option value="<?= (int)$candidate['id'] ?>">
                                    <?= htmlspecialchars((string)$candidate['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                    — <?= (int)$candidate['open_cases'] ?>/<?= (int)$candidate['capacity'] ?> casos
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary w-100 mt-3" type="submit">
                            <i class="bi bi-arrow-left-right me-1"></i>Reasignar caso
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        No hay agentes disponibles con capacidad para recibir este caso en este momento.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="case-card" style="margin-top:18px">
            <h2 style="margin-top:0">Eventos</h2>
            <div class="timeline">
                <?php foreach ($events as $event): ?>
                    <div class="timeline-item">
                        <strong><?= htmlspecialchars((string)$event['event_type'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <div class="muted"><?= htmlspecialchars((string)($event['actor_name'] ?: 'Sistema'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$event['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const type=document.getElementById('managementType');
    const escalation=document.getElementById('escalationBlock');
    const petition=document.getElementById('petitionChangeBlock');
    const refresh=()=>{
        escalation.style.display=type.value==='ESCALATED'?'':'none';
        petition.style.display=type.value==='PETITION_TYPE_CHANGE'?'':'none';
    };
    type?.addEventListener('change',refresh);
    refresh();
})();
</script>
