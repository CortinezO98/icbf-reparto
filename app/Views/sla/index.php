<?php
/** @var array<string,mixed> $dashboard */
$summary = $dashboard['summary'] ?? [];
$agentSummary = $dashboard['agent_summary'] ?? [];
$agents = $dashboard['agents'] ?? [];
$queues = $dashboard['queues'] ?? [];
$managementTypes = $dashboard['management_types'] ?? [];
$regions = $dashboard['regions'] ?? [];
$channels = $dashboard['channels'] ?? [];
$trend = $dashboard['trend'] ?? [];
$alerts = $dashboard['alerts'] ?? [];
$cases = $dashboard['cases'] ?? [];
$imports = $dashboard['imports'] ?? [];
$presence = $dashboard['presence'] ?? [];
$period = $dashboard['period'] ?? ['key'=>'today','label'=>'Hoy'];
$policy = $policy ?? [];

$fmt = static fn(mixed $value): string => number_format((float)$value, 0, ',', '.');
$hours = static function(mixed $minutes): string {
    if ($minutes === null || $minutes === '') return '—';
    $minutes = max(0, (float)$minutes);
    if ($minutes < 60) return number_format($minutes, 0, ',', '.') . ' min';
    return number_format($minutes / 60, 1, ',', '.') . ' h';
};
$percent = static function(mixed $value, mixed $total): string {
    $total = (float)$total;
    if ($total <= 0) return '0%';
    return number_format(((float)$value / $total) * 100, 1, ',', '.') . '%';
};

$openCases = (int)($summary['open_cases'] ?? 0);
$pendingAssignment = (int)($summary['pending_assignment'] ?? 0);
$assignedCases = (int)($summary['assigned_cases'] ?? 0);
$managedOpen = (int)($summary['managed_open'] ?? 0);
$closedCases = (int)($summary['closed_cases'] ?? 0);
$managedPeriod = (int)($summary['managed_period'] ?? 0);
$green = (int)($summary['sla_green'] ?? 0);
$yellow = (int)($summary['sla_yellow'] ?? 0);
$red = (int)($summary['sla_red'] ?? 0);
$breached = (int)($summary['sla_breached'] ?? 0);
$redTotal = $red + $breached;
$activeSla = $green + $yellow + $redTotal;
$responseRate = $openCases > 0 ? ($managedOpen / $openCases) * 100 : 0;
$max = static function(array $rows): int {
    $max = 0;
    foreach ($rows as $row) $max = max($max, (int)($row['total'] ?? $row['received'] ?? 0));
    return max(1, $max);
};
$stateLabel = [
    'PENDING_ASSIGNMENT'=>'Pendientes de asignación',
    'ASSIGNED'=>'Asignados',
    'CLOSED'=>'Cerrados',
];
$presenceLabel = [
    'AVAILABLE'=>'Disponibles',
    'TRAINING'=>'En capacitación',
    'MEETING'=>'En reunión',
    'BREAK'=>'Break',
    'ASYNC_ACTIVITY'=>'Actividades asincrónicas',
    'BATHROOM'=>'Baño',
    'TECH_FAILURE'=>'Falla tecnológica',
    'FEEDBACK'=>'Retroalimentación',
    'ACTIVE_BREAK'=>'Pausas activas',
    'OFFLINE'=>'Desconectados',
];
?>
<style>
.dashboard-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;margin-bottom:18px}
.dashboard-head h1{margin:0;font-weight:800;letter-spacing:.1px}
.dashboard-head p{margin:.3rem 0 0;color:#6c757d}
.dashboard-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.dashboard-card{height:100%;background:#fff;border:1px solid rgba(33,37,41,.12);border-radius:16px;box-shadow:0 2px 5px rgba(0,0,0,.035);overflow:hidden}
.dashboard-card .card-body{padding:18px}
.kpi-card{position:relative;min-height:145px}
.kpi-card .kpi-icon{position:absolute;right:18px;top:18px;width:52px;height:52px;border-radius:50%;display:grid;place-items:center;font-size:1.35rem}
.kpi-label{font-size:.82rem;color:#6c757d}
.kpi-value{font-size:2rem;line-height:1.1;font-weight:800;margin-top:5px}
.kpi-detail{font-size:.82rem;color:#6c757d;margin-top:7px}
.kpi-progress{height:6px;background:#e9ecef;border-radius:999px;overflow:hidden;margin-top:14px}.kpi-progress>span{display:block;height:100%;border-radius:999px}
.section-title{font-size:1rem;font-weight:800;margin:0}.section-subtitle{font-size:.78rem;color:#6c757d;margin-top:2px}
.dashboard-table{width:100%;border-collapse:collapse}.dashboard-table th{font-size:.73rem;text-transform:uppercase;letter-spacing:.35px;color:#6c757d;background:#f8f9fa;white-space:nowrap}.dashboard-table th,.dashboard-table td{padding:10px 11px;border-bottom:1px solid #edf0f2;text-align:left}.dashboard-table tbody tr:hover{background:rgba(76,175,80,.045)}
.status-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:.72rem;font-weight:700}.status-green{background:#d1e7dd;color:#146c43}.status-yellow{background:#fff3cd;color:#997404}.status-red{background:#f8d7da;color:#b02a37}.status-blue{background:#cfe2ff;color:#084298}.status-gray{background:#e9ecef;color:#495057}
.filter-box{background:#fff;border:1px solid rgba(33,37,41,.12);border-radius:14px;padding:13px 15px;margin-bottom:18px}
.filter-box label{font-size:.76rem;margin:0 0 4px;font-weight:700;color:#495057}.filter-box .form-select{font-size:.85rem}
.bar-row{display:grid;grid-template-columns:minmax(110px,1fr) 2fr 52px;gap:9px;align-items:center;margin:9px 0}.bar-label{font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bar-track{height:9px;background:#edf0f2;border-radius:999px;overflow:hidden}.bar-fill{height:100%;background:var(--color-primary);border-radius:999px}.bar-value{text-align:right;font-weight:700;font-size:.8rem}
.alert-row{border-left:4px solid #dc3545;background:#fff5f5;border-radius:8px;padding:10px 11px;margin-bottom:8px}.alert-row.warning{border-left-color:#ffc107;background:#fff9e6}.alert-row .title{font-weight:750;font-size:.84rem}.alert-row .meta{font-size:.75rem;color:#6c757d;margin-top:2px}
.trend{display:flex;align-items:flex-end;gap:9px;height:150px;padding-top:10px}.trend-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;height:100%;justify-content:flex-end}.trend-bars{width:100%;height:115px;display:flex;align-items:flex-end;justify-content:center;gap:3px}.trend-bar{width:38%;min-height:2px;border-radius:4px 4px 0 0;background:var(--color-primary)}.trend-bar.closed{background:#6c757d}.trend-label{font-size:.68rem;color:#6c757d}.trend-value{font-size:.68rem;font-weight:700}
.mini-stat{padding:11px 13px;border:1px solid #edf0f2;border-radius:10px;background:#fbfcfd}.mini-stat span{display:block;font-size:.75rem;color:#6c757d}.mini-stat strong{display:block;font-size:1.15rem;margin-top:3px}
.alert-count{font-size:.72rem;padding:4px 7px;border-radius:999px;background:#f8d7da;color:#b02a37;font-weight:800}
.empty-state{padding:22px;text-align:center;color:#6c757d;font-size:.85rem}

/* Resumen ANS: referencia visual solicitada; el resto del tablero conserva su diseño. */
.ans-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.ans-summary-card{position:relative;min-height:205px;background:#fff;border:1px solid #e1e5ea;border-radius:16px;padding:16px;box-shadow:0 2px 6px rgba(15,23,42,.05);overflow:hidden}
.ans-summary-card .ans-label{font-size:.78rem;color:#5f6b7a}.ans-summary-card .ans-value{font-size:2rem;line-height:1.05;font-weight:800;margin:5px 0}.ans-summary-card .ans-subtitle{font-size:.78rem;color:#667085;line-height:1.45}.ans-summary-card .ans-icon{position:absolute;right:16px;top:16px;width:58px;height:58px;border-radius:50%;display:grid;place-items:center;font-size:1.35rem}.ans-summary-card .ans-divider{height:1px;background:#e6e9ed;margin:15px 0 11px}.ans-summary-card .ans-stat{display:flex;justify-content:space-between;gap:10px;font-size:.78rem;color:#667085;margin-top:6px}.ans-summary-card .ans-stat strong{color:#1f2937}.ans-summary-card.danger{border:3px solid #dc3545;padding:14px}.ans-summary-card.danger .ans-value{color:#dc3545}.ans-summary-card.danger .ans-icon{background:#fdecef;color:#dc3545}.ans-summary-card.open .ans-icon{background:#eaf2ff;color:#0d6efd}.ans-summary-card.response .ans-icon{background:#e7f5ef;color:#198754}.ans-summary-card.semaphore .ans-icon{background:#fff7df;color:#f0ad00}
.ans-action{display:block;width:100%;margin-top:13px;text-align:center;padding:7px 10px;border:1px solid currentColor;border-radius:5px;background:#fff;text-decoration:none;font-size:.78rem}.ans-action:hover{filter:brightness(.96)}
.ans-semaphore-line{display:grid;grid-template-columns:55px 1fr 40px;align-items:center;gap:7px;margin-top:8px}.ans-semaphore-pill{font-size:.68rem;font-weight:800;color:#fff;border-radius:5px;padding:3px 7px;text-align:center}.ans-semaphore-track{height:6px;background:#e9ecef;border-radius:999px;overflow:hidden}.ans-semaphore-fill{height:100%;border-radius:999px}
.ans-summary-secondary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:14px}.ans-secondary-card{background:#fff;border:3px solid #adb5bd;border-radius:16px;padding:16px;min-height:194px;box-shadow:0 2px 6px rgba(15,23,42,.04)}.ans-secondary-card.process{border-color:#12bfe8}.ans-secondary-card.managed{border-color:#ff7417}.ans-secondary-card.closed{border-color:#737d87}.ans-secondary-head{display:flex;justify-content:space-between;align-items:center;gap:10px}.ans-secondary-title{font-size:.78rem;color:#536070}.ans-secondary-value{font-size:2rem;font-weight:800;line-height:1.05;margin-top:5px}.ans-secondary-card.process .ans-secondary-value{color:#08afd8}.ans-secondary-card.managed .ans-secondary-value{color:#f26f21}.ans-secondary-card.closed .ans-secondary-value{color:#65707c}.ans-secondary-icon{width:56px;height:56px;border-radius:50%;display:grid;place-items:center;font-size:1.3rem}.process .ans-secondary-icon{background:#e5f9fd;color:#08afd8}.managed .ans-secondary-icon{background:#fff0e5;color:#f26f21}.closed .ans-secondary-icon{background:#f0f1f3;color:#65707c}.ans-secondary-divider{height:1px;background:#dfe3e7;margin:14px 0 10px}.ans-secondary-desc{font-size:.78rem;color:#667085}.ans-secondary-card .ans-action{margin-top:12px}
.ans-status-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:14px}.ans-status-card{background:#fff;border:3px solid;border-radius:16px;padding:16px;min-height:226px;box-shadow:0 2px 6px rgba(15,23,42,.04)}.ans-status-card.green{border-color:#198754}.ans-status-card.yellow{border-color:#ffb400}.ans-status-card.red{border-color:#dc3545}.ans-status-head{display:flex;align-items:center;gap:14px}.ans-status-icon{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;font-size:1.35rem}.green .ans-status-icon{background:#e7f5ef;color:#198754}.yellow .ans-status-icon{background:#fff6dc;color:#ffb400}.red .ans-status-icon{background:#fdecef;color:#dc3545}.ans-status-name{font-size:.78rem;text-transform:uppercase;color:#495057;font-weight:700}.ans-status-value{font-size:2rem;line-height:1.05;margin-top:3px}.green .ans-status-value{color:#198754}.yellow .ans-status-value{color:#f0a800}.red .ans-status-value{color:#dc3545}.ans-status-threshold{font-size:.78rem;color:#667085;margin-top:3px}.ans-status-note{font-size:.72rem;line-height:1.35;border-radius:6px;padding:9px 10px;margin-top:16px}.green .ans-status-note{background:#dff2e8;border:1px solid #a8d7bf;color:#195c3d}.yellow .ans-status-note{background:#fff3d0;border:1px solid #ffd46b;color:#725500}.red .ans-status-note{background:#fde1e5;border:1px solid #f2a9b5;color:#8f1d2c}.ans-status-card .ans-action{margin-top:14px}
@media(max-width:1100px){.ans-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ans-summary-secondary,.ans-status-grid{grid-template-columns:1fr}}
@media(max-width:600px){.ans-summary-grid{grid-template-columns:1fr}.ans-summary-secondary,.ans-status-grid{grid-template-columns:1fr}.ans-summary-card,.ans-secondary-card,.ans-status-card{min-height:auto}}
@media(max-width:900px){.dashboard-table{min-width:760px}.dashboard-scroll{overflow:auto}.bar-row{grid-template-columns:105px 1fr 45px}}
</style>

<div class="dashboard-head">
    <div>
        <h1><i class="bi bi-speedometer2 text-brand me-2"></i>Tablero de Control • ICBF Reparto</h1>
        <p>Visión consolidada de casos, reparto, operación, ANS, agentes, cargas y actividad.</p>
        <?php if ($policy !== []): ?>
            <div class="small text-muted mt-1">
                Política ANS: <strong><?= htmlspecialchars((string)($policy['name'] ?? 'Vigente'), ENT_QUOTES, 'UTF-8') ?></strong>
                · Jornada <?= htmlspecialchars(substr((string)($policy['business_start'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?>
                a <?= htmlspecialchars(substr((string)($policy['business_end'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="dashboard-actions">
        <span class="badge text-bg-success-subtle border border-success text-success-emphasis px-3 py-2">
            <i class="bi bi-clock-history me-1"></i><?= htmlspecialchars((string)$period['label'], ENT_QUOTES, 'UTF-8') ?>
        </span>
        <a href="/sla" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</a>
    </div>
</div>

<form method="get" action="/" class="filter-box">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="period">Periodo de actividad</label>
            <select class="form-select" id="period" name="period">
                <?php foreach ([
                    'today'=>'Hoy',
                    '7d'=>'Últimos 7 días',
                    'month'=>'Mes actual',
                    'all'=>'Histórico'
                ] as $key=>$label): ?>
                    <option value="<?= $key ?>" <?= ($period['key'] ?? 'today') === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="queue_id">Cola</label>
            <select class="form-select" id="queue_id" name="queue_id">
                <option value="">Todas las colas</option>
                <?php foreach (($dashboard['filters']['queues'] ?? []) as $queue): ?>
                    <option value="<?= (int)$queue['id'] ?>" <?= (int)($_GET['queue_id'] ?? 0) === (int)$queue['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="agent_id">Agente</label>
            <select class="form-select" id="agent_id" name="agent_id">
                <option value="">Todos los agentes</option>
                <?php foreach (($dashboard['filters']['agents'] ?? []) as $agent): ?>
                    <option value="<?= (int)$agent['id'] ?>" <?= (int)($_GET['agent_id'] ?? 0) === (int)$agent['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-brand w-100" type="submit"><i class="bi bi-funnel me-1"></i>Aplicar filtros</button>
        </div>
    </div>
</form>

<div class="ans-summary-grid">
    <div class="ans-summary-card open">
        <div class="ans-label">Casos abiertos</div>
        <div class="ans-value"><?= $fmt($openCases) ?></div>
        <div class="ans-subtitle">Casos actualmente abiertos en el tablero</div>
        <div class="ans-icon"><i class="bi bi-folder2-open"></i></div>
        <div class="ans-divider"></div>
        <div class="ans-stat"><span>Nuevos</span><strong><?= $fmt($pendingAssignment) ?></strong></div>
        <div class="ans-stat"><span>Asignados</span><strong><?= $fmt($assignedCases) ?></strong></div>
    </div>
    <div class="ans-summary-card response">
        <div class="ans-label">Tasa de primera gestión</div>
        <div class="ans-value text-success"><?= number_format($responseRate, 1, ',', '.') ?>%</div>
        <div class="ans-subtitle">Sobre los casos abiertos del tablero</div>
        <div class="ans-icon"><i class="bi bi-check2-circle"></i></div>
        <div class="ans-divider"></div>
        <div class="ans-stat"><span>Promedio 1ª gestión</span><strong><?= $hours($summary['avg_first_management_minutes'] ?? null) ?></strong></div>
        <div class="ans-stat"><span>Casos con gestión</span><strong><?= $fmt($managedOpen) ?></strong></div>
    </div>
    <div class="ans-summary-card danger">
        <div class="ans-label">Incumplidos ANS</div>
        <div class="ans-value"><?= $fmt($breached) ?></div>
        <div class="ans-subtitle">Casos vencidos según la política ANS</div>
        <div class="ans-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="ans-divider"></div>
        <a class="ans-action text-danger" href="/cases?sla_status=BREACHED"><i class="bi bi-lightning-charge me-1"></i>Atender vencidos</a>
    </div>
    <div class="ans-summary-card semaphore">
        <div class="ans-label">Semáforo (activos)</div>
        <div class="ans-value" style="font-size:1.55rem"><?= $fmt($green) ?>/<?= $fmt($yellow) ?>/<?= $fmt($redTotal) ?></div>
        <div class="ans-subtitle">Verde / Amarillo / Rojo y vencidos</div>
        <div class="ans-icon"><i class="bi bi-speedometer2"></i></div>
        <div class="ans-semaphore-line"><span class="ans-semaphore-pill" style="background:#198754">Verde</span><div class="ans-semaphore-track"><div class="ans-semaphore-fill" style="width:<?= $percent($green,$activeSla) ?>;background:#198754"></div></div><span class="small text-muted"><?= $percent($green,$activeSla) ?></span></div>
        <div class="ans-semaphore-line"><span class="ans-semaphore-pill" style="background:#ffb400">Amarillo</span><div class="ans-semaphore-track"><div class="ans-semaphore-fill" style="width:<?= $percent($yellow,$activeSla) ?>;background:#ffb400"></div></div><span class="small text-muted"><?= $percent($yellow,$activeSla) ?></span></div>
        <div class="ans-semaphore-line"><span class="ans-semaphore-pill" style="background:#dc3545">Rojo</span><div class="ans-semaphore-track"><div class="ans-semaphore-fill" style="width:<?= $percent($redTotal,$activeSla) ?>;background:#dc3545"></div></div><span class="small text-muted"><?= $percent($redTotal,$activeSla) ?></span></div>
    </div>
</div>

<div class="ans-summary-secondary">
    <div class="ans-secondary-card process">
        <div class="ans-secondary-head"><div><div class="ans-secondary-title">En proceso</div><div class="ans-secondary-value"><?= $fmt($managedOpen) ?></div><div class="ans-secondary-desc">Casos abiertos con primera gestión</div></div><div class="ans-secondary-icon"><i class="bi bi-gear"></i></div></div>
        <div class="ans-secondary-divider"></div>
        <a class="ans-action" style="color:#08afd8" href="/cases?state=ASSIGNED&managed=1"><i class="bi bi-eye me-1"></i>Ver en proceso</a>
    </div>
    <div class="ans-secondary-card managed">
        <div class="ans-secondary-head"><div><div class="ans-secondary-title">Gestionados</div><div class="ans-secondary-value"><?= $fmt($managedPeriod) ?></div><div class="ans-secondary-desc">Casos con primera gestión en el periodo</div></div><div class="ans-secondary-icon"><i class="bi bi-chat-left-text"></i></div></div>
        <div class="ans-secondary-divider"></div>
        <a class="ans-action" style="color:#f26f21" href="/cases?managed=1"><i class="bi bi-eye me-1"></i>Ver gestionados</a>
    </div>
    <div class="ans-secondary-card closed">
        <div class="ans-secondary-head"><div><div class="ans-secondary-title">Cerrados</div><div class="ans-secondary-value"><?= $fmt($closedCases) ?></div><div class="ans-secondary-desc">Casos finalizados históricamente</div></div><div class="ans-secondary-icon"><i class="bi bi-check2-all"></i></div></div>
        <div class="ans-secondary-divider"></div>
        <a class="ans-action" style="color:#65707c" href="/cases?state=CLOSED"><i class="bi bi-archive me-1"></i>Ver cerrados</a>
    </div>
</div>

<div class="ans-status-grid">
    <div class="ans-status-card green">
        <div class="ans-status-head"><div class="ans-status-icon"><i class="bi bi-check-lg"></i></div><div><div class="ans-status-name">Verde</div><div class="ans-status-value"><?= $fmt($green) ?></div><div class="ans-status-threshold">0 a &lt; 2 horas hábiles</div></div></div>
        <div class="ans-status-note"><i class="bi bi-info-circle me-1"></i>Atención normal. Mantener flujo y priorización.</div>
        <a class="ans-action" style="color:#198754" href="/cases?sla_status=GREEN">Ver detalle</a>
    </div>
    <div class="ans-status-card yellow">
        <div class="ans-status-head"><div class="ans-status-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="ans-status-name">Amarillo</div><div class="ans-status-value"><?= $fmt($yellow) ?></div><div class="ans-status-threshold">2 a 4 horas hábiles</div></div></div>
        <div class="ans-status-note"><i class="bi bi-exclamation-triangle me-1"></i>Atención prioritaria. Evitar que pasen a ROJO.</div>
        <a class="ans-action" style="color:#f0a800" href="/cases?sla_status=YELLOW">Ver detalle</a>
    </div>
    <div class="ans-status-card red">
        <div class="ans-status-head"><div class="ans-status-icon"><i class="bi bi-exclamation-octagon-fill"></i></div><div><div class="ans-status-name">Rojo</div><div class="ans-status-value"><?= $fmt($redTotal) ?></div><div class="ans-status-threshold">&gt; 4 horas hábiles / vencidos</div></div></div>
        <div class="ans-status-note"><i class="bi bi-exclamation-octagon me-1"></i>Atención inmediata. Riesgo/incumplimiento de ANS.</div>
        <a class="ans-action" style="color:#dc3545" href="/cases?sla_status=RED_OR_BREACHED">Ver detalle</a>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-7">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="section-title">Carga por cola</div>
                <div class="section-subtitle">Casos activos actuales y movimiento del periodo seleccionado.</div>
                <?php if ($queues !== []): ?>
                    <div class="mt-3">
                        <?php $queueMax=max(1,...array_map(static fn($q)=>(int)$q['open_cases'],$queues)); ?>
                        <?php foreach ($queues as $queue): ?>
                            <div class="bar-row">
                                <div class="bar-label" title="<?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="bar-track"><div class="bar-fill" style="width:<?= ((int)$queue['open_cases']/$queueMax)*100 ?>%"></div></div>
                                <div class="bar-value"><?= $fmt($queue['open_cases']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dashboard-scroll mt-2">
                        <table class="dashboard-table">
                            <thead><tr><th>Cola</th><th>Activos</th><th>Pendientes</th><th>Asignados</th><th>Recibidos</th><th>Cerrados</th></tr></thead>
                            <tbody>
                            <?php foreach ($queues as $queue): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= $fmt($queue['open_cases']) ?></td>
                                    <td><?= $fmt($queue['pending_assignment']) ?></td>
                                    <td><?= $fmt($queue['assigned_cases']) ?></td>
                                    <td><?= $fmt($queue['received_period']) ?></td>
                                    <td><?= $fmt($queue['closed_period']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?><div class="empty-state">No hay colas activas.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="section-title">Evolución últimos 7 días</div>
                <div class="section-subtitle">Casos recibidos y cerrados por fecha de creación/cierre.</div>
                <?php if ($trend !== []): ?>
                    <?php $trendMax=max(1,...array_map(static fn($x)=>max((int)$x['received'],(int)$x['closed']),$trend)); ?>
                    <div class="trend">
                        <?php foreach ($trend as $day): ?>
                            <div class="trend-col">
                                <div class="trend-value"><?= $fmt($day['received']) ?></div>
                                <div class="trend-bars" title="<?= htmlspecialchars((string)$day['day'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="trend-bar" style="height:<?= max(2,((int)$day['received']/$trendMax)*100) ?>%"></div>
                                    <div class="trend-bar closed" style="height:<?= max(2,((int)$day['closed']/$trendMax)*100) ?>%"></div>
                                </div>
                                <div class="trend-label"><?= htmlspecialchars(date('d/m',strtotime((string)$day['day'])), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="small text-muted mt-1"><span class="me-3"><i class="bi bi-square-fill text-brand me-1"></i>Recibidos</span><span><i class="bi bi-square-fill text-secondary me-1"></i>Cerrados</span></div>
                <?php else: ?><div class="empty-state">Sin actividad registrada.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-7">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div><div class="section-title">Carga individual de agentes</div><div class="section-subtitle">Capacidad, carga actual y cierres de los últimos 30 días.</div></div>
                    <a href="/supervisor/agents" class="btn btn-sm btn-outline-secondary">Estado agentes</a>
                </div>
                <div class="dashboard-scroll">
                    <table class="dashboard-table">
                        <thead><tr><th>Agente</th><th>Presencia</th><th>Colas</th><th>Activos</th><th>Libre</th><th>Cerrados 30d</th></tr></thead>
                        <tbody>
                        <?php foreach ($agents as $agent): ?>
                            <?php
                                $presenceCode=(string)$agent['status_code'];
                                $presenceClass=$presenceCode==='AVAILABLE'?'status-green':($presenceCode==='OFFLINE'?'status-gray':'status-yellow');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?></strong><div class="text-muted small"><?= htmlspecialchars((string)$agent['username'], ENT_QUOTES, 'UTF-8') ?></div></td>
                                <td><span class="status-badge <?= $presenceClass ?>"><?= htmlspecialchars((string)$agent['status_label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string)($agent['queue_codes'] ?: 'Sin cola'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><strong><?= $fmt($agent['open_cases']) ?></strong> / <?= $fmt($agent['configured_capacity']) ?></td>
                                <td><?= $fmt($agent['free_capacity']) ?></td>
                                <td><?= $fmt($agent['closed_period']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($agents === []): ?><tr><td colspan="6" class="empty-state">No hay agentes activos.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="section-title">Tipos de gestión</div>
                <div class="section-subtitle">Gestiones registradas en el periodo seleccionado.</div>
                <?php if ($managementTypes !== []): ?>
                    <?php $mtMax=$max($managementTypes); ?>
                    <div class="mt-3">
                        <?php foreach ($managementTypes as $item): ?>
                            <div class="bar-row">
                                <div class="bar-label" title="<?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="bar-track"><div class="bar-fill" style="width:<?= ((int)$item['total']/$mtMax)*100 ?>%"></div></div>
                                <div class="bar-value"><?= $fmt($item['total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?><div class="empty-state">Sin gestiones en el periodo.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-6">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="section-title">Distribución por regional</div>
                <div class="section-subtitle">Top 10 regionales según los casos recibidos.</div>
                <?php if ($regions !== []): ?>
                    <?php $rMax=$max($regions); ?>
                    <div class="mt-3">
                        <?php foreach ($regions as $item): ?>
                            <div class="bar-row">
                                <div class="bar-label" title="<?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="bar-track"><div class="bar-fill" style="width:<?= ((int)$item['total']/$rMax)*100 ?>%"></div></div>
                                <div class="bar-value"><?= $fmt($item['total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?><div class="empty-state">Sin información regional.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="section-title">Canal de origen</div>
                <div class="section-subtitle">Distribución de los casos recibidos por canal.</div>
                <?php if ($channels !== []): ?>
                    <?php $cMax=$max($channels); ?>
                    <div class="mt-3">
                        <?php foreach ($channels as $item): ?>
                            <div class="bar-row">
                                <div class="bar-label" title="<?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="bar-track"><div class="bar-fill" style="width:<?= ((int)$item['total']/$cMax)*100 ?>%"></div></div>
                                <div class="bar-value"><?= $fmt($item['total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?><div class="empty-state">Sin información de canal.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="section-title">Casos activos</div>
                        <div class="section-subtitle">Consulta directamente la gestión, trazabilidad y estado ANS de cada caso.</div>
                    </div>
                    <a href="/cases" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-inboxes me-1"></i>Ver todos
                    </a>
                </div>

                <?php if ($cases !== []): ?>
                    <div class="dashboard-scroll mt-3">
                        <table class="dashboard-table">
                            <thead>
                            <tr>
                                <th>Caso</th>
                                <th>Estado</th>
                                <th>ANS</th>
                                <th>Cola</th>
                                <th>Agente</th>
                                <th>Última gestión</th>
                                <th>Vence</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cases as $case): ?>
                                <?php
                                $sla = (string)($case['sla_status'] ?? '');
                                $slaClass = match ($sla) {
                                    'GREEN' => 'status-green',
                                    'YELLOW' => 'status-yellow',
                                    'RED', 'BREACHED' => 'status-red',
                                    default => 'status-gray',
                                };
                                $state = (string)($case['current_state'] ?? '');
                                $stateLabel = match ($state) {
                                    'PENDING_ASSIGNMENT' => 'Pendiente de asignación',
                                    'ASSIGNED' => 'Asignado',
                                    'CLOSED' => 'Cerrado',
                                    default => $state !== '' ? $state : 'Sin estado',
                                };
                                ?>
                                <tr>
                                    <td>
                                        <a class="fw-bold text-decoration-none" href="/cases/<?= (int)$case['id'] ?>">
                                            <?= htmlspecialchars((string)$case['case_number'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars((string)$case['external_key'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-blue">
                                            <?= htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $slaClass ?>">
                                            <?= htmlspecialchars($sla !== '' ? $sla : 'PENDIENTE', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <div class="text-muted small">
                                            <?= $hours($case['sla_elapsed_minutes'] ?? null) ?> hábiles
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars((string)($case['queue_code'] ?: 'Sin cola'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($case['assigned_user_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($case['current_management_type_code'] ?: 'Sin gestión'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="small"><?= htmlspecialchars((string)($case['sla_due_at'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <a href="/cases/<?= (int)$case['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver gestión del caso">
                                            <i class="bi bi-eye me-1"></i>Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Se muestran los casos activos priorizados por estado ANS. El detalle conserva las validaciones de acceso del usuario.
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle text-success fs-4 d-block mb-2"></i>
                        No hay casos activos con los filtros seleccionados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-7">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div><div class="section-title">Alertas operativas</div><div class="section-subtitle">Casos que requieren atención según ANS.</div></div>
                    <span class="alert-count"><?= $fmt(count($alerts)) ?> abiertas</span>
                </div>
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert-row <?= $alert['severity'] === 'WARNING' ? 'warning' : '' ?>">
                        <div class="d-flex justify-content-between gap-2">
                            <div class="title"><?= htmlspecialchars((string)$alert['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <span class="status-badge <?= $alert['severity']==='CRITICAL'?'status-red':'status-yellow' ?>"><?= htmlspecialchars((string)$alert['severity'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="meta">
                            <?= htmlspecialchars((string)$alert['case_number'], ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars((string)($alert['queue_code'] ?: 'Sin cola'), ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars((string)($alert['assigned_user_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="small mt-1"><?= htmlspecialchars((string)$alert['message'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="mt-2">
                            <a href="/cases/<?= (int)$alert['case_id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>Ver gestión
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($alerts === []): ?><div class="empty-state"><i class="bi bi-check-circle me-1"></i>No hay alertas operativas abiertas.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="section-title">Últimas cargas</div>
                <div class="section-subtitle">Trazabilidad de los archivos importados.</div>
                <div class="dashboard-scroll mt-2">
                    <table class="dashboard-table">
                        <thead><tr><th>Lote</th><th>Estado</th><th>Filas</th><th>Fecha</th></tr></thead>
                        <tbody>
                        <?php foreach ($imports as $import): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$import['batch_number'], ENT_QUOTES, 'UTF-8') ?></strong><div class="text-muted small"><?= htmlspecialchars((string)$import['queue_code'], ENT_QUOTES, 'UTF-8') ?></div></td>
                                <td><span class="status-badge status-blue"><?= htmlspecialchars((string)$import['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= $fmt($import['total_rows']) ?></td>
                                <td class="small"><?= htmlspecialchars((string)$import['uploaded_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($imports === []): ?><tr><td colspan="4" class="empty-state">No hay cargas registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Los indicadores de <strong>casos abiertos</strong> y <strong>ANS</strong> representan el estado actual.
    Los indicadores de actividad se calculan con el periodo seleccionado. La capacidad de agentes usa la configuración vigente.
</div>
