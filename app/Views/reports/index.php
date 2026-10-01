<?php
use App\Auth\Auth;
use App\Auth\Authorization;
use App\Config\Database;

/** @var array<string,mixed> $data */
$summary = $data['summary'] ?? [];
$cases = $data['cases'] ?? [];
$queues = $data['queues'] ?? [];
$agents = $data['agents'] ?? [];
$states = $data['states'] ?? [];
$sla = $data['sla'] ?? [];
$filters = $data['filters'] ?? ['queues'=>[],'agents'=>[]];
$selected = $data['selected'] ?? [];
$period = (string)($data['period'] ?? 'Hoy');

$fmt = static fn(mixed $value): string => number_format((float)$value, 0, ',', '.');
$hours = static function(mixed $minutes): string {
    if ($minutes === null || $minutes === '') return '—';
    $minutes = max(0, (float)$minutes);
    return $minutes < 60
        ? $fmt($minutes) . ' min'
        : number_format($minutes / 60, 1, ',', '.') . ' h';
};
$badge = static function(mixed $status): string {
    return match ((string)$status) {
        'GREEN' => 'status-green',
        'YELLOW' => 'status-yellow',
        'RED','BREACHED' => 'status-red',
        default => 'status-gray',
    };
};
$stateText = static fn(mixed $state): string => match ((string)$state) {
    'PENDING_ASSIGNMENT' => 'Pendiente de asignación',
    'ASSIGNED' => 'Asignado',
    'CLOSED' => 'Cerrado',
    default => (string)$state,
};
$slaText = static fn(mixed $value): string => match ((string)$value) {
    'GREEN' => 'Verde',
    'YELLOW' => 'Amarillo',
    'RED' => 'Rojo',
    'BREACHED' => 'Vencido',
    'PENDING' => 'Pendiente',
    default => (string)$value,
};
?>
<style>
.report-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px}
.report-head h1{font-weight:800;margin:0}.report-head p{margin:4px 0 0;color:#6c757d}
.report-filters{background:#fff;border:1px solid rgba(33,37,41,.12);border-radius:14px;padding:15px;margin-bottom:18px}
.report-filters label{font-size:.74rem;font-weight:700;color:#495057;margin-bottom:4px}
.report-card{height:100%;background:#fff;border:1px solid rgba(33,37,41,.12);border-radius:16px;box-shadow:0 2px 5px rgba(0,0,0,.035);overflow:hidden}
.report-card .card-body{padding:18px}
.report-kpi{min-height:125px}.report-kpi-label{font-size:.78rem;color:#6c757d}.report-kpi-value{font-size:1.9rem;font-weight:800;margin-top:4px}.report-kpi-detail{font-size:.76rem;color:#6c757d;margin-top:5px}
.report-table{width:100%;border-collapse:collapse}.report-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.3px;color:#6c757d;background:#f8f9fa;white-space:nowrap}.report-table th,.report-table td{padding:9px 10px;border-bottom:1px solid #edf0f2}.report-table tbody tr:hover{background:rgba(76,175,80,.045)}
.report-scroll{overflow:auto}.report-bar{display:grid;grid-template-columns:120px 1fr 45px;gap:8px;align-items:center;margin:9px 0}.report-bar-label{font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.report-bar-track{height:8px;background:#edf0f2;border-radius:999px;overflow:hidden}.report-bar-fill{height:100%;background:var(--color-primary);border-radius:999px}.report-bar-value{text-align:right;font-size:.76rem;font-weight:700}
.report-empty{text-align:center;color:#6c757d;padding:22px;font-size:.85rem}
</style>

<div class="report-head">
    <div>
        <h1><i class="bi bi-file-earmark-bar-graph text-brand me-2"></i>Reportes operativos</h1>
        <p>Consulta y exporta información de casos, ANS, colas y agentes con los filtros seleccionados.</p>
    </div>
    <?php if (Authorization::hasPermission(Database::connection(), (int)Auth::id(), 'REPORT_EXPORT')): ?>
        <?php
        $query = $_GET;
        $query['export'] = null;
        $exportUrl = '/reports/export?' . http_build_query($query);
        ?>
        <a class="btn btn-success" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-filetype-csv me-1"></i>Exportar CSV
        </a>
    <?php endif; ?>
</div>

<form class="report-filters" method="get" action="/reports">
    <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-lg-2">
            <label for="period">Periodo</label>
            <select class="form-select" id="period" name="period">
                <?php foreach (['today'=>'Hoy','7d'=>'Últimos 7 días','month'=>'Mes actual','all'=>'Histórico'] as $key=>$label): ?>
                    <option value="<?= $key ?>" <?= ($selected['period'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label for="queue_id">Cola</label>
            <select class="form-select" id="queue_id" name="queue_id">
                <option value="">Todas</option>
                <?php foreach ($filters['queues'] as $queue): ?>
                    <option value="<?= (int)$queue['id'] ?>" <?= (int)($selected['queue_id'] ?? 0) === (int)$queue['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label for="agent_id">Agente</label>
            <select class="form-select" id="agent_id" name="agent_id">
                <option value="">Todos</option>
                <?php foreach ($filters['agents'] as $agent): ?>
                    <option value="<?= (int)$agent['id'] ?>" <?= (int)($selected['agent_id'] ?? 0) === (int)$agent['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label for="state">Estado</label>
            <select class="form-select" id="state" name="state">
                <option value="">Todos</option>
                <?php foreach (['PENDING_ASSIGNMENT'=>'Pendiente de asignación','ASSIGNED'=>'Asignado','CLOSED'=>'Cerrado'] as $key=>$label): ?>
                    <option value="<?= $key ?>" <?= ($selected['state'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label for="sla">ANS</label>
            <select class="form-select" id="sla" name="sla">
                <option value="">Todos</option>
                <?php foreach (['GREEN'=>'Verde','YELLOW'=>'Amarillo','RED'=>'Rojo','BREACHED'=>'Vencido'] as $key=>$label): ?>
                    <option value="<?= $key ?>" <?= ($selected['sla'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Aplicar</button>
            <a class="btn btn-outline-secondary" href="/reports" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
<?php
$kpis = [
    ['Total casos',$summary['total_cases'] ?? 0,'Periodo seleccionado','bi-collection'],
    ['Abiertos',$summary['open_cases'] ?? 0,'Sin cierre','bi-inbox'],
    ['Pendientes',$summary['pending_assignment'] ?? 0,'Sin asignación','bi-person-plus'],
    ['Cerrados',$summary['closed_cases'] ?? 0,'Con cierre registrado','bi-check2-circle'],
    ['Con gestión',$summary['managed_cases'] ?? 0,'Primera gestión registrada','bi-chat-left-text'],
    ['Cumplimiento ANS',$summary['sla_compliance_percent'] === null ? '—' : $summary['sla_compliance_percent'].'%','Casos cerrados dentro de ANS','bi-clock-history'],
];
foreach ($kpis as [$label,$value,$detail,$icon]):
?>
    <div class="col-6 col-xl-2">
        <div class="report-card report-kpi"><div class="card-body">
            <div class="text-brand fs-5"><i class="bi <?= $icon ?>"></i></div>
            <div class="report-kpi-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="report-kpi-value"><?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="report-kpi-detail"><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></div>
        </div></div>
    </div>
<?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-6">
        <div class="report-card"><div class="card-body">
            <div class="fw-bold">Distribución por ANS</div>
            <div class="text-muted small mb-2">Casos del periodo filtrado.</div>
            <?php
            $maxSla = max(1, ...array_map(static fn(array $r): int => (int)$r['total'], $sla));
            foreach ($sla as $row):
            ?>
                <div class="report-bar">
                    <div class="report-bar-label"><?= htmlspecialchars($slaText($row['label']), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="report-bar-track"><div class="report-bar-fill" style="width:<?= min(100, ((int)$row['total'] / $maxSla) * 100) ?>%"></div></div>
                    <div class="report-bar-value"><?= $fmt($row['total']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if ($sla === []): ?><div class="report-empty">Sin datos.</div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="report-card"><div class="card-body">
            <div class="fw-bold">Distribución por estado</div>
            <div class="text-muted small mb-2">Estado registrado actualmente.</div>
            <?php
            $maxState = max(1, ...array_map(static fn(array $r): int => (int)$r['total'], $states));
            foreach ($states as $row):
            ?>
                <div class="report-bar">
                    <div class="report-bar-label"><?= htmlspecialchars($stateText($row['label']), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="report-bar-track"><div class="report-bar-fill" style="width:<?= min(100, ((int)$row['total'] / $maxState) * 100) ?>%"></div></div>
                    <div class="report-bar-value"><?= $fmt($row['total']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if ($states === []): ?><div class="report-empty">Sin datos.</div><?php endif; ?>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-6">
        <div class="report-card"><div class="card-body">
            <div class="fw-bold">Resumen por cola</div>
            <div class="text-muted small mb-2">Carga actual y actividad del periodo.</div>
            <div class="report-scroll">
                <table class="report-table">
                    <thead><tr><th>Cola</th><th>Recibidos</th><th>Abiertos</th><th>Pendientes</th><th>Cerrados</th></tr></thead>
                    <tbody>
                    <?php foreach ($queues as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$row['code'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted"><?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?></div></td>
                            <td><?= $fmt($row['received_period']) ?></td>
                            <td><?= $fmt($row['open_cases']) ?></td>
                            <td><?= $fmt($row['pending_assignment']) ?></td>
                            <td><?= $fmt($row['closed_cases']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($queues === []): ?><tr><td colspan="5" class="report-empty">Sin datos.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="report-card"><div class="card-body">
            <div class="fw-bold">Resumen por agente</div>
            <div class="text-muted small mb-2">Carga activa y actividad del periodo.</div>
            <div class="report-scroll">
                <table class="report-table">
                    <thead><tr><th>Agente</th><th>Activos</th><th>Recibidos</th><th>Gestionados</th><th>Cerrados</th></tr></thead>
                    <tbody>
                    <?php foreach ($agents as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$row['full_name'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted"><?= htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') ?></div></td>
                            <td><?= $fmt($row['open_cases']) ?></td>
                            <td><?= $fmt($row['received_period']) ?></td>
                            <td><?= $fmt($row['managed_period']) ?></td>
                            <td><?= $fmt($row['closed_period']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($agents === []): ?><tr><td colspan="5" class="report-empty">Sin agentes activos.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>

<div class="report-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div><div class="fw-bold">Detalle de casos</div><div class="text-muted small">Hasta 200 registros por consulta. La exportación permite hasta 5.000.</div></div>
            <span class="badge text-bg-light border"><?= $fmt(count($cases)) ?> mostrados</span>
        </div>
        <div class="report-scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Caso</th><th>Cola</th><th>Agente</th><th>Estado</th>
                        <th>ANS</th><th>Tipo petición</th><th>Regional</th><th>Canal</th>
                        <th>Creado</th><th>Primera gestión</th><th>Cerrado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cases as $row): ?>
                    <tr>
                        <td>
                            <a href="/cases/<?= (int)$row['id'] ?>" class="fw-bold text-decoration-none">
                                <?= htmlspecialchars((string)$row['case_number'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <div class="small text-muted"><?= htmlspecialchars((string)$row['external_key'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><?= htmlspecialchars((string)($row['queue_code'] ?: 'Sin cola'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($row['agent_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status-badge status-blue"><?= htmlspecialchars($stateText($row['current_state']), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <span class="status-badge <?= $badge($row['sla_status']) ?>">
                                <?= htmlspecialchars($slaText($row['sla_status'] ?: 'PENDING'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <div class="small text-muted"><?= $hours($row['sla_elapsed_minutes']) ?></div>
                        </td>
                        <td><?= htmlspecialchars((string)($row['petition_type'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($row['regional'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($row['origin_channel'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="small"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="small"><?= htmlspecialchars((string)($row['first_management_at'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="small"><?= htmlspecialchars((string)($row['closed_at'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a href="/cases/<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($cases === []): ?><tr><td colspan="12" class="report-empty">No hay casos con los filtros seleccionados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="small text-muted mt-3">
    Periodo: <strong><?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?></strong>.
    Los casos se filtran por fecha de creación; la vista conserva los tiempos y estado ANS registrados.
</div>
