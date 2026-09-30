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
$imports = $dashboard['imports'] ?? [];
$presence = $dashboard['presence'] ?? [];
$period = $dashboard['period'] ?? ['key'=>'today','label'=>'Hoy'];

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
@media(max-width:900px){.dashboard-table{min-width:760px}.dashboard-scroll{overflow:auto}.bar-row{grid-template-columns:105px 1fr 45px}}
</style>

<div class="dashboard-head">
    <div>
        <h1><i class="bi bi-speedometer2 text-brand me-2"></i>Tablero de Control • ICBF Reparto</h1>
        <p>Visión consolidada de casos, reparto, operación, ANS, agentes, cargas y actividad.</p>
    </div>
    <div class="dashboard-actions">
        <span class="badge text-bg-success-subtle border border-success text-success-emphasis px-3 py-2">
            <i class="bi bi-clock-history me-1"></i><?= htmlspecialchars((string)$period['label'], ENT_QUOTES, 'UTF-8') ?>
        </span>
        <a href="/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</a>
        <a href="/sla" class="btn btn-outline-brand btn-sm"><i class="bi bi-speedometer2 me-1"></i>Detalle ANS</a>
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

<div class="row g-3">
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card kpi-card">
            <div class="card-body">
                <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-inbox"></i></div>
                <div class="kpi-label">Casos abiertos</div>
                <div class="kpi-value"><?= $fmt($summary['open_cases'] ?? 0) ?></div>
                <div class="kpi-detail">Pendientes de asignación: <strong><?= $fmt($summary['pending_assignment'] ?? 0) ?></strong></div>
                <div class="kpi-detail">Asignados: <strong><?= $fmt($summary['assigned_cases'] ?? 0) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card kpi-card">
            <div class="card-body">
                <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div>
                <div class="kpi-label">Actividad del periodo</div>
                <div class="kpi-value"><?= $fmt($summary['received'] ?? 0) ?></div>
                <div class="kpi-detail">Casos recibidos</div>
                <div class="kpi-detail">Cerrados: <strong><?= $fmt($summary['closed_period'] ?? 0) ?></strong> · Gestionados: <strong><?= $fmt($summary['managed_period'] ?? 0) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card kpi-card">
            <div class="card-body">
                <div class="kpi-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-stopwatch"></i></div>
                <div class="kpi-label">Tiempos promedio</div>
                <div class="kpi-value"><?= $hours($summary['avg_first_management_minutes'] ?? null) ?></div>
                <div class="kpi-detail">Hasta primera gestión</div>
                <div class="kpi-detail">Resolución: <strong><?= $hours($summary['avg_resolution_minutes'] ?? null) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card kpi-card">
            <div class="card-body">
                <div class="kpi-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="kpi-label">Incumplidos ANS</div>
                <div class="kpi-value text-danger"><?= $fmt($summary['sla_breached'] ?? 0) ?></div>
                <div class="kpi-detail">Rojo: <strong><?= $fmt($summary['sla_red'] ?? 0) ?></strong> · Amarillo: <strong><?= $fmt($summary['sla_yellow'] ?? 0) ?></strong></div>
                <div class="kpi-detail">Alertas abiertas: <strong><?= $fmt(count($alerts)) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-8">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div><div class="section-title">Semáforo ANS de casos activos</div><div class="section-subtitle">Estado calculado con la política ANS vigente.</div></div>
                    <a href="/sla" class="btn btn-sm btn-outline-brand">Ver detalle</a>
                </div>
                <?php $activeSla = (int)($summary['sla_green'] ?? 0)+(int)($summary['sla_yellow'] ?? 0)+(int)($summary['sla_red'] ?? 0)+(int)($summary['sla_breached'] ?? 0); ?>
                <div class="row g-2 mt-2">
                    <?php foreach ([
                        ['label'=>'Verde','value'=>$summary['sla_green'] ?? 0,'class'=>'status-green','icon'=>'bi-check-circle'],
                        ['label'=>'Amarillo','value'=>$summary['sla_yellow'] ?? 0,'class'=>'status-yellow','icon'=>'bi-exclamation-triangle'],
                        ['label'=>'Rojo','value'=>$summary['sla_red'] ?? 0,'class'=>'status-red','icon'=>'bi-exclamation-octagon'],
                        ['label'=>'Vencido','value'=>$summary['sla_breached'] ?? 0,'class'=>'status-red','icon'=>'bi-x-octagon']
                    ] as $item): ?>
                        <div class="col-md-3">
                            <div class="mini-stat">
                                <span><span class="status-badge <?= $item['class'] ?>"><i class="bi <?= $item['icon'] ?> me-1"></i><?= $item['label'] ?></span></span>
                                <strong><?= $fmt($item['value']) ?></strong>
                                <span><?= $percent($item['value'],$activeSla) ?> de activos evaluados</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <div class="kpi-progress"><span style="width:<?= $percent($summary['sla_green'] ?? 0,$activeSla) ?>;background:#198754"></span></div>
                    <div class="d-flex justify-content-between mt-1 small text-muted">
                        <span>Verde <?= $percent($summary['sla_green'] ?? 0,$activeSla) ?></span>
                        <span>Amarillo <?= $percent($summary['sla_yellow'] ?? 0,$activeSla) ?></span>
                        <span>Rojo/Vencido <?= $percent((int)($summary['sla_red'] ?? 0)+(int)($summary['sla_breached'] ?? 0),$activeSla) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dashboard-card">
            <div class="card-body">
                <div class="section-title">Estado de agentes</div>
                <div class="section-subtitle">Presencia operacional actual.</div>
                <div class="row g-2 mt-2">
                    <div class="col-6"><div class="mini-stat"><span>Total agentes</span><strong><?= $fmt($agentSummary['total_agents'] ?? 0) ?></strong></div></div>
                    <div class="col-6"><div class="mini-stat"><span>Disponibles</span><strong><?= $fmt($agentSummary['available_agents'] ?? 0) ?></strong></div></div>
                    <div class="col-6"><div class="mini-stat"><span>Carga activa</span><strong><?= $fmt($agentSummary['active_load'] ?? 0) ?></strong></div></div>
                    <div class="col-6"><div class="mini-stat"><span>Capacidad libre</span><strong><?= $fmt($agentSummary['free_capacity'] ?? 0) ?></strong></div></div>
                </div>
                <div class="mt-3">
                    <?php foreach ($presence as $code=>$count): ?>
                        <div class="d-flex justify-content-between align-items-center py-1 small">
                            <span><?= htmlspecialchars($presenceLabel[$code] ?? $code, ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= $fmt($count) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($presence === []): ?><div class="empty-state">Sin información de presencia.</div><?php endif; ?>
                </div>
            </div>
        </div>
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
