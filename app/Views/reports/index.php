<?php
use App\Auth\Auth;
use App\Auth\Authorization;
use App\Config\Database;

/** @var array<string,mixed> $data */
$summary = $data['summary'] ?? [];
$daily = $data['daily'] ?? [];
$agents = $data['agents'] ?? [];
$presenceHistory = $data['presence_history'] ?? [];
$queues = $data['queues'] ?? [];
$cases = $data['cases'] ?? [];
$filters = $data['filters'] ?? ['queues'=>[],'agents'=>[]];
$selected = $data['selected'] ?? [];

$fmt = static fn(mixed $value): string => number_format((float)$value, 0, ',', '.');

$hours = static function (mixed $minutes): string {
    if ($minutes === null || $minutes === '') {
        return '—';
    }

    $minutes = max(0, (float)$minutes);

    return number_format($minutes / 60, 1, ',', '.') . ' h';
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

$startDate = (string)($selected['start_date'] ?? '');
$endDate = (string)($selected['end_date'] ?? '');
$selectedRegional = (string)($selected['regional'] ?? '');
$selectedPetitionType = (string)($selected['petition_type'] ?? '');
$selectedManagementType = (string)($selected['management_type'] ?? '');

$green = (int)($summary['sla_green'] ?? 0);
$yellow = (int)($summary['sla_yellow'] ?? 0);
$red = (int)($summary['sla_red'] ?? 0);
$breached = (int)($summary['sla_breached'] ?? 0);

$slaVisibleTotal = $green + $yellow + $red;
$greenPct = $slaVisibleTotal > 0 ? round(($green / $slaVisibleTotal) * 100, 1) : 0;
$yellowPct = $slaVisibleTotal > 0 ? round(($yellow / $slaVisibleTotal) * 100, 1) : 0;
$redPct = $slaVisibleTotal > 0 ? round(($red / $slaVisibleTotal) * 100, 1) : 0;

$totalCases = (int)($summary['total_cases'] ?? 0);
$managedCases = (int)($summary['managed_cases'] ?? 0);
$responseRate = $summary['response_rate_percent'] ?? null;
$breachRate = $totalCases > 0 ? round(($breached / $totalCases) * 100, 1) : 0;

$maxDaily = 1;
foreach ($daily as $row) {
    $maxDaily = max($maxDaily, (int)($row['total'] ?? 0));
}

$exportUrl = static function (string $report, string $format) use ($startDate, $endDate, $selected): string {
    $query = [
        'report' => $report,
        'format' => $format,
        'start' => $startDate,
        'end' => $endDate,
    ];

    foreach (['queue_id', 'agent_id', 'state', 'sla', 'regional', 'petition_type', 'management_type'] as $key) {
        if (($selected[$key] ?? null) !== null && ($selected[$key] ?? '') !== '') {
            $query[$key] = $selected[$key];
        }
    }

    return '/reports/export?' . http_build_query($query);
};
?>
<style>
.reports-page{padding-bottom:28px}
.reports-header{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:20px}
.reports-header h1{font-size:1.65rem;font-weight:800;letter-spacing:-.02em;margin:0;color:#172033}
.reports-header p{margin:4px 0 0;color:#667085;font-size:.94rem}
.reports-header-actions{display:flex;align-items:center;gap:8px}
.reports-period{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid #dfe3e8;border-radius:8px;padding:9px 12px;font-size:.82rem;color:#344054;white-space:nowrap}
.reports-filter{background:#fff;border:1px solid #e8eaed;border-radius:16px;box-shadow:0 2px 8px rgba(16,24,40,.05);padding:18px;margin-bottom:20px}
.reports-filter label{font-size:.75rem;font-weight:700;color:#475467;margin-bottom:6px}
.reports-filter .form-control,.reports-filter .form-select{height:42px;border-color:#d9dde3;font-size:.86rem}
.reports-filter .input-group-text{background:#fff;border-color:#d9dde3;color:#667085}
.reports-filter .btn{height:42px}
.report-card{height:100%;background:#fff;border:1px solid #e8eaed;border-radius:16px;box-shadow:0 2px 8px rgba(16,24,40,.045);overflow:hidden}
.report-card-body{padding:18px}
.report-kpi{min-height:156px}
.report-kpi .kpi-label{font-size:.78rem;color:#667085;margin-bottom:3px}
.report-kpi .kpi-value{font-size:1.8rem;line-height:1.05;font-weight:800;color:#172033}
.report-kpi .kpi-detail{font-size:.76rem;color:#667085;margin-top:7px}
.report-kpi .kpi-icon{width:42px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:1.25rem}
.kpi-blue{background:#eaf2ff;color:#1769e0}
.kpi-green{background:#e8f7ef;color:#138a55}
.kpi-yellow{background:#fff7df;color:#a66a00}
.kpi-red{background:#fff0f0;color:#e33b4a}
.sla-number{font-size:1.55rem;font-weight:800;letter-spacing:-.02em}
.sla-number span{font-weight:500;color:#98a2b3}
.sla-progress{height:7px;background:#eef1f4;border-radius:999px;overflow:hidden}
.sla-progress > div{height:100%;border-radius:999px}
.sla-line{display:grid;grid-template-columns:78px 1fr 45px;align-items:center;gap:8px;margin-top:9px;font-size:.75rem}
.sla-pill{display:inline-flex;width:max-content;padding:2px 7px;border-radius:999px;font-weight:700}
.sla-green{background:#e7f7ee;color:#117a49}
.sla-yellow{background:#fff4cf;color:#8b6100}
.sla-red{background:#ffe7e7;color:#c52e3d}
.sla-breached{background:#fff0f0;color:#d92d3d}
.report-section-title{font-size:.98rem;font-weight:800;color:#172033}
.report-section-subtitle{font-size:.77rem;color:#667085;margin-top:2px}
.daily-table,.productivity-table{width:100%;border-collapse:collapse}
.daily-table th,.productivity-table th{font-size:.68rem;text-transform:uppercase;letter-spacing:.35px;color:#667085;font-weight:700;border-bottom:1px solid #e6e9ed;padding:9px 8px;white-space:nowrap}
.daily-table td,.productivity-table td{font-size:.82rem;color:#344054;border-bottom:1px solid #eef0f2;padding:9px 8px}
.daily-table tbody tr:last-child td,.productivity-table tbody tr:last-child td{border-bottom:0}
.daily-bar{height:8px;background:#e9edf1;border-radius:999px;overflow:hidden}
.daily-bar-fill{height:100%;background:#1473ea;border-radius:999px}
.daily-value{text-align:right;font-weight:800;color:#172033}
.daily-trend{text-align:right;font-size:.74rem;color:#667085}
.report-scroll{overflow:auto}
.report-empty{text-align:center;color:#98a2b3;padding:24px 12px;font-size:.84rem}
.productivity-table .agent-name{font-weight:700;color:#172033}
.productivity-table .muted{font-size:.72rem;color:#98a2b3}
.sla-status-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:.7rem;font-weight:700}
.status-blue{background:#eaf2ff;color:#175cd3}
.status-green{background:#e8f7ef;color:#117a49}
.status-yellow{background:#fff4cf;color:#8b6100}
.status-red{background:#ffe7e7;color:#c52e3d}
.status-gray{background:#f2f4f7;color:#667085}
.reports-export-modal .modal-dialog{max-width:920px}
.reports-export-modal .modal-content{border:0;border-radius:16px;box-shadow:0 20px 60px rgba(16,24,40,.22);overflow:hidden}
.reports-export-modal .modal-header{padding:18px 20px;border-bottom:1px solid #e4e7ec}
.reports-export-modal .modal-title{font-size:1.05rem;font-weight:800;color:#172033}
.reports-export-modal .modal-body{display:flex;flex-direction:column;align-items:center;gap:12px;padding:18px}
.reports-export-modal .reports-export-intro{width:100%}
.report-export-grid{width:100%;max-width:760px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.report-export-grid .report-export-item{width:100%}
.reports-export-intro{font-size:.77rem;line-height:1.55;color:#667085;background:#f8fafb;border:1px solid #e5e7eb;border-radius:10px;padding:11px 13px;margin:0 0 2px}
.report-export-item{display:grid;grid-template-rows:auto 1fr auto;gap:12px;border:1px solid #dfe3e8;border-radius:12px;padding:15px;background:#fff;margin:0;min-width:0;min-height:150px;transition:border-color .15s ease,box-shadow .15s ease}
.report-export-item:hover{border-color:#cbd5df;box-shadow:0 5px 16px rgba(16,24,40,.06)}
.report-export-info{min-width:0}
.report-export-title{display:flex;align-items:center;gap:9px;font-size:.9rem;font-weight:800;color:#344054;line-height:1.3}
.report-export-title i{display:inline-grid;place-items:center;flex:0 0 36px;width:36px;height:36px;border-radius:10px;background:#eef8ef;color:var(--color-primary);font-size:1rem}
.report-export-description{font-size:.73rem;line-height:1.5;color:#667085;margin-top:6px;padding-left:45px}
.report-export-actions{display:flex;justify-content:flex-end;gap:8px;padding-top:11px;border-top:1px solid #eef0f2}
.report-export-actions .btn{font-size:.74rem;padding:6px 11px;min-width:70px}
.report-export-actions .btn-excel{border-color:#198754;color:#198754}
.report-export-actions .btn-excel:hover{background:#198754;color:#fff}
@media(max-width:575.98px){
  .reports-export-modal .modal-body{padding:12px}
  .report-export-item{padding:12px;min-height:0}
  .report-export-title i{flex-basis:34px;width:34px;height:34px}
  .report-export-description{padding-left:0}
  .report-export-actions{justify-content:stretch}
  .report-export-actions .btn{flex:1}
}
@media(max-width:767.98px){
  .report-export-grid{grid-template-columns:1fr;max-width:100%}
  .reports-header h1{font-size:1.4rem}
  .reports-header-actions{width:100%;justify-content:space-between}
  .report-kpi{min-height:130px}
  .sla-line{grid-template-columns:72px 1fr 38px}
}
</style>

<div class="reports-page">
    <div class="reports-header">
        <div>
            <h1><i class="bi bi-file-earmark-bar-graph me-2" style="color:var(--color-primary)"></i>Dashboard de Reportes</h1>
            <p>Análisis completo de métricas y desempeño del sistema</p>
        </div>

        <div class="reports-header-actions">
            <div class="reports-period">
                <i class="bi bi-calendar3"></i>
                <span><?= htmlspecialchars((string)($data['period'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php if (Authorization::hasPermission(Database::connection(), (int)Auth::id(), 'REPORT_EXPORT')): ?>
                <button
                    class="btn btn-outline-primary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#reportsExportModal"
                >
                    <i class="bi bi-download me-1"></i>Exportar
                </button>
            <?php endif; ?>
        </div>
    </div>

    <form class="reports-filter" method="get" action="/reports">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label for="start">Fecha inicio</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                    <input class="form-control" type="date" id="start" name="start"
                           value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="col-12 col-md-3">
                <label for="end">Fecha fin</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                    <input class="form-control" type="date" id="end" name="end"
                           value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="col-12 col-md-3">
                <label for="queue_id">Cola</label>
                <select class="form-select" id="queue_id" name="queue_id">
                    <option value="">Todas las colas</option>
                    <?php foreach ($filters['queues'] as $queue): ?>
                        <option value="<?= (int)$queue['id'] ?>"
                            <?= (int)($selected['queue_id'] ?? 0) === (int)$queue['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="agent_id">Agente</label>
                <select class="form-select" id="agent_id" name="agent_id">
                    <option value="">Todos los agentes</option>
                    <?php foreach ($filters['agents'] as $agent): ?>
                        <option value="<?= (int)$agent['id'] ?>"
                            <?= (int)($selected['agent_id'] ?? 0) === (int)$agent['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="regional">Regional</label>
                <select class="form-select" id="regional" name="regional">
                    <option value="">Todas las regionales</option>
                    <?php foreach (($filters['regionals'] ?? []) as $regional): ?>
                        <option value="<?= htmlspecialchars((string)$regional['regional'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $selectedRegional === (string)$regional['regional'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$regional['regional'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="petition_type">Tipo de petición</label>
                <select class="form-select" id="petition_type" name="petition_type">
                    <option value="">Todos los tipos</option>
                    <?php foreach (($filters['petition_types'] ?? []) as $petition): ?>
                        <option value="<?= htmlspecialchars((string)$petition['petition_type'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $selectedPetitionType === (string)$petition['petition_type'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$petition['petition_type'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="management_type">Tipo de gestión</label>
                <select class="form-select" id="management_type" name="management_type">
                    <option value="">Todos los tipos de gestión</option>
                    <?php foreach (($filters['management_types'] ?? []) as $management): ?>
                        <option value="<?= htmlspecialchars((string)$management['code'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $selectedManagementType === (string)$management['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$management['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="state">Estado</label>
                <select class="form-select" id="state" name="state">
                    <option value="">Todos los estados</option>
                    <?php foreach ([
                        'PENDING_ASSIGNMENT'=>'Pendiente de asignación',
                        'ASSIGNED'=>'Asignado',
                        'CLOSED'=>'Cerrado'
                    ] as $key=>$label): ?>
                        <option value="<?= $key ?>"
                            <?= ($selected['state'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="sla">ANS</label>
                <div class="d-flex gap-2">
                    <select class="form-select" id="sla" name="sla">
                        <option value="">Todos los estados ANS</option>
                        <?php foreach ([
                            'GREEN'=>'Verde',
                            'YELLOW'=>'Amarillo',
                            'RED'=>'Rojo',
                            'BREACHED'=>'Vencido'
                        ] as $key=>$label): ?>
                            <option value="<?= $key ?>"
                                <?= ($selected['sla'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary px-3" type="submit" title="Aplicar filtros">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-3">
            <div class="report-card report-kpi">
                <div class="report-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Casos Totales</div>
                            <div class="kpi-value"><?= $fmt($totalCases) ?></div>
                            <div class="kpi-detail">
                                <?= $fmt($summary['open_cases'] ?? 0) ?> abiertos
                                <span class="ms-1">(<?= $totalCases > 0 ? round(((int)($summary['open_cases'] ?? 0) / $totalCases) * 100) : 0 ?>%)</span>
                            </div>
                        </div>
                        <div class="kpi-icon kpi-blue"><i class="bi bi-folder2-open"></i></div>
                    </div>
                    <div class="small text-muted mt-3">
                        Cerrados: <strong><?= $fmt($summary['closed_cases'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-3">
            <div class="report-card report-kpi">
                <div class="report-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Respondidos</div>
                            <div class="kpi-value"><?= $fmt($managedCases) ?></div>
                            <div class="kpi-detail">
                                Tasa de respuesta:
                                <strong><?= $responseRate === null ? '—' : $responseRate . '%' ?></strong>
                            </div>
                        </div>
                        <div class="kpi-icon kpi-green"><i class="bi bi-check-circle"></i></div>
                    </div>
                    <div class="sla-progress mt-3">
                        <div style="width:<?= min(100, max(0, (float)($responseRate ?? 0))) ?>%;background:#198754"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-3">
            <div class="report-card report-kpi">
                <div class="report-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="w-100">
                            <div class="kpi-label">Estado SLA</div>
                            <div class="sla-number">
                                <?= $fmt($green) ?><span>/</span><?= $fmt($yellow) ?><span>/</span><?= $fmt($red) ?>
                            </div>
                            <div class="d-flex gap-2 mt-1">
                                <span class="sla-pill sla-green">Verde</span>
                                <span class="sla-pill sla-yellow">Amarillo</span>
                                <span class="sla-pill sla-red">Rojo</span>
                            </div>
                        </div>
                        <div class="kpi-icon kpi-yellow ms-2"><i class="bi bi-speedometer2"></i></div>
                    </div>

                    <div class="sla-line">
                        <span>Verde</span>
                        <div class="sla-progress"><div style="width:<?= $greenPct ?>%;background:#198754"></div></div>
                        <strong><?= $greenPct ?>%</strong>
                    </div>
                    <div class="sla-line">
                        <span>Amarillo</span>
                        <div class="sla-progress"><div style="width:<?= $yellowPct ?>%;background:#f0ad00"></div></div>
                        <strong><?= $yellowPct ?>%</strong>
                    </div>
                    <div class="sla-line">
                        <span>Rojo</span>
                        <div class="sla-progress"><div style="width:<?= $redPct ?>%;background:#dc3545"></div></div>
                        <strong><?= $redPct ?>%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-3">
            <div class="report-card report-kpi">
                <div class="report-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Vencidos</div>
                            <div class="kpi-value" style="color:#dc3545"><?= $fmt($breached) ?></div>
                            <div class="kpi-detail">Tasa de vencimiento: <strong><?= $breachRate ?>%</strong></div>
                        </div>
                        <div class="kpi-icon kpi-red"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="mt-3 px-2 py-2 rounded-2" style="background:#fff4d6;color:#805f00;font-size:.76rem">
                        <i class="bi bi-person-dash me-1"></i>
                        Sin gestión: <strong><?= $fmt($summary['unmanaged_open_cases'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="report-card mb-3">
        <div class="report-card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-graph-up-arrow" style="color:var(--color-primary)"></i>
                <div>
                    <div class="report-section-title">Serie Diaria - Casos Recibidos</div>
                    <div class="report-section-subtitle">Cantidad de casos creados por día dentro del periodo seleccionado.</div>
                </div>
            </div>

            <?php if ($daily !== []): ?>
                <div class="report-scroll">
                    <table class="daily-table">
                        <thead>
                            <tr>
                                <th style="width:18%">Día</th>
                                <th style="width:15%;text-align:right">Casos recibidos</th>
                                <th>Tendencia</th>
                                <th style="width:8%;text-align:right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($daily as $row):
                            $count = (int)($row['total'] ?? 0);
                            $trend = round(($count / $maxDaily) * 100);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(date('d/m/Y', strtotime((string)$row['day'])), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td class="daily-value"><?= $fmt($count) ?></td>
                                <td>
                                    <div class="daily-bar">
                                        <div class="daily-bar-fill" style="width:<?= min(100, $trend) ?>%"></div>
                                    </div>
                                </td>
                                <td class="daily-trend"><?= $trend ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="report-empty">No hay casos recibidos en el periodo seleccionado.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="report-card mb-3">
        <div class="report-card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people" style="color:var(--color-primary)"></i>
                        <div class="report-section-title">Productividad por Agente</div>
                    </div>
                    <div class="report-section-subtitle">Casos asignados, resueltos, vencidos, tiempo de respuesta y cumplimiento ANS.</div>
                </div>
                <span class="small text-muted d-none d-md-block">Periodo seleccionado</span>
            </div>

            <div class="report-scroll">
                <table class="productivity-table">
                    <thead>
                        <tr>
                            <th>Agente</th>
                            <th style="text-align:right">Asignados</th>
                            <th style="text-align:right">Resueltos</th>
                            <th style="text-align:right">Vencidos</th>
                            <th style="text-align:right">Tiempo resp. (h)</th>
                            <th style="text-align:right">% Cumplimiento SLA</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($agents as $agent):
                        $closed = (int)($agent['closed_cases'] ?? 0);
                        $compliant = (int)($agent['compliant_cases'] ?? 0);
                        $compliance = $closed > 0 ? round(($compliant / $closed) * 100, 1) : null;
                    ?>
                        <tr>
                            <td>
                                <div class="agent-name"><?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="muted"><?= htmlspecialchars((string)$agent['username'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td style="text-align:right"><?= $fmt($agent['assigned_cases'] ?? 0) ?></td>
                            <td style="text-align:right"><?= $fmt($agent['resolved_cases'] ?? 0) ?></td>
                            <td style="text-align:right">
                                <?php if ((int)($agent['breached_cases'] ?? 0) > 0): ?>
                                    <span class="sla-status-badge status-red"><?= $fmt($agent['breached_cases']) ?></span>
                                <?php else: ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right"><?= $hours($agent['response_minutes'] ?? null) ?></td>
                            <td style="text-align:right">
                                <?php if ($compliance === null): ?>
                                    —
                                <?php else: ?>
                                    <strong><?= $compliance ?>%</strong>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($agents === []): ?>
                        <tr><td colspan="6" class="report-empty">No hay datos de agentes para el periodo seleccionado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="report-card mb-3">
        <div class="report-card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history" style="color:var(--color-primary)"></i>
                        <div>
                            <div class="report-section-title">Histórico de estados de agentes</div>
                            <div class="report-section-subtitle">Muestra cada cambio de estado dentro del periodo, incluyendo la desconexión registrada por cierre de sesión o detectada por heartbeat.</div>
                        </div>
                    </div>
                </div>
                <span class="small text-muted d-none d-md-block"><?= count($presenceHistory) ?> registros</span>
            </div>

            <div class="report-scroll">
                <table class="productivity-table">
                    <thead>
                        <tr>
                            <th>Agente</th>
                            <th>Estado</th>
                            <th>Inicio</th>
                            <th>Fin / desconexión</th>
                            <th>Duración</th>
                            <th>Origen</th>
                            <th>Establecido por</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($presenceHistory, 0, 200) as $presence): ?>
                        <?php
                            $presenceCode = (string)($presence['status_code'] ?? '');
                            $presenceLabel = (string)($presence['status_label'] ?? $presenceCode);
                            $presenceClass = $presenceCode === 'OFFLINE'
                                ? 'status-gray'
                                : ($presenceCode === 'AVAILABLE' ? 'status-green' : 'status-blue');
                            $started = (string)($presence['started_at'] ?? '');
                            $ended = (string)($presence['ended_at'] ?? '');
                            $duration = (int)($presence['duration_minutes'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <div class="agent-name"><?= htmlspecialchars((string)$presence['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="muted"><?= htmlspecialchars((string)$presence['username'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <span class="sla-status-badge <?= $presenceClass ?>">
                                    <?= htmlspecialchars($presenceLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($started, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= $ended !== ''
                                    ? htmlspecialchars($ended, ENT_QUOTES, 'UTF-8')
                                    : '<span class="text-muted">En curso</span>' ?>
                            </td>
                            <td><?= $hours($duration) ?></td>
                            <td><?= htmlspecialchars((string)($presence['source'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($presence['set_by_name'] ?? 'Sistema'), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($presenceHistory === []): ?>
                        <tr><td colspan="7" class="report-empty">No hay cambios de estado de agentes en el periodo seleccionado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="small text-muted mt-3">
                Si el agente cierra sesión, la desconexión se registra en ese momento. Si pierde conexión o cierra el navegador sin cerrar sesión, la desconexión se registra cuando el sistema detecta que el heartbeat superó el tiempo configurado.
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="report-card">
                <div class="report-card-body">
                    <div class="report-section-title">Resumen por Cola</div>
                    <div class="report-section-subtitle mb-3">Carga y comportamiento de las colas.</div>
                    <div class="report-scroll">
                        <table class="productivity-table">
                            <thead>
                                <tr>
                                    <th>Cola</th>
                                    <th style="text-align:right">Recibidos</th>
                                    <th style="text-align:right">Abiertos</th>
                                    <th style="text-align:right">Cerrados</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($queues as $queue): ?>
                                <tr>
                                    <td>
                                        <div class="agent-name"><?= htmlspecialchars((string)$queue['code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="muted"><?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td style="text-align:right"><?= $fmt($queue['received_period'] ?? 0) ?></td>
                                    <td style="text-align:right"><?= $fmt($queue['open_cases'] ?? 0) ?></td>
                                    <td style="text-align:right"><?= $fmt($queue['closed_cases'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($queues === []): ?>
                                <tr><td colspan="4" class="report-empty">Sin datos.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="report-card">
                <div class="report-card-body">
                    <div class="report-section-title">Detalle de casos</div>
                    <div class="report-section-subtitle mb-3">Hasta 200 registros visibles. La exportación permite hasta 5.000.</div>
                    <div class="report-scroll">
                        <table class="productivity-table">
                            <thead>
                                <tr>
                                    <th>Caso</th>
                                    <th>Agente</th>
                                    <th>Estado</th>
                                    <th>ANS</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($cases, 0, 12) as $row):
                                $slaStatus = (string)($row['sla_status'] ?? 'PENDING');
                                $slaClass = match ($slaStatus) {
                                    'GREEN' => 'status-green',
                                    'YELLOW' => 'status-yellow',
                                    'RED','BREACHED' => 'status-red',
                                    default => 'status-gray',
                                };
                            ?>
                                <tr>
                                    <td>
                                        <a href="/cases/<?= (int)$row['id'] ?>" class="agent-name text-decoration-none">
                                            <?= htmlspecialchars((string)$row['case_number'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <div class="muted"><?= htmlspecialchars((string)($row['queue_code'] ?: 'Sin cola'), ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars((string)($row['agent_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="sla-status-badge status-blue">
                                            <?= htmlspecialchars($stateText($row['current_state']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="sla-status-badge <?= $slaClass ?>">
                                            <?= htmlspecialchars($slaText($slaStatus), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/cases/<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver caso">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($cases === []): ?>
                                <tr><td colspan="5" class="report-empty">No hay casos con los filtros seleccionados.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="small text-muted mt-2">
        Los indicadores se calculan sobre la fecha de creación de los casos. El tiempo de respuesta usa la primera gestión registrada y el cumplimiento ANS usa el estado ANS persistido en cada caso.
    </div>
</div>

<?php if (Authorization::hasPermission(Database::connection(), (int)Auth::id(), 'REPORT_EXPORT')): ?>
<div class="modal fade reports-export-modal" id="reportsExportModal" tabindex="-1" aria-labelledby="reportsExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="reportsExportModalLabel">
                    <i class="bi bi-download me-2"></i>Exportar Reportes
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="reports-export-intro">
                    Los reportes de Casos/SLA, Resumen de Agentes e Histórico de Agentes usan el rango de fechas seleccionado arriba
                    (<?= htmlspecialchars($data['period'] ?? '', ENT_QUOTES, 'UTF-8') ?>).
                    El Estado en Tiempo Real siempre exporta la fotografía actual, sin rango histórico.
                </div>

                <div class="report-export-grid">
                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-briefcase"></i>Casos / SLA</div>
                            <div class="report-export-description">Detalle de casos, ANS, tiempos, estados, agente, regional, tipo de petición y trazabilidad básica.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('cases', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('cases', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>

                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-person-lines-fill"></i>Productividad de Agentes</div>
                            <div class="report-export-description">Asignados, pendientes, resueltos, vencidos, tiempo de primera gestión y cumplimiento ANS.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('agents_summary', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('agents_summary', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>

                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-list-check"></i>Gestiones de Casos</div>
                            <div class="report-export-description">Cerrados, direccionamientos, escalamientos, cambios de tipo, reportes a Policía, observaciones y soportes.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('managements', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('managements', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>

                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-arrow-left-right"></i>Asignaciones y Reasignaciones</div>
                            <div class="report-export-description">Agente origen/destino, tipo de asignación, responsable, fechas y motivo de reasignación.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('assignments', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('assignments', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>

                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-bar-chart-line"></i>Volumen por Día y Hora</div>
                            <div class="report-export-description">Distribución de casos por fecha, hora, cola, regional y tipo de petición.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('volume_time', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('volume_time', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>

                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-calendar3"></i>Consolidado Mensual</div>
                            <div class="report-export-description">Comparativo mensual de volumen, abiertos, cerrados, gestionados, vencidos y tiempos operativos.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('monthly', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('monthly', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>

                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-clock-history"></i>Histórico de Agentes</div>
                            <div class="report-export-description">Cada transición de estado con inicio, fin, duración, heartbeat, origen y usuario que estableció el estado.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('agents_history', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('agents_history', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>

                    <div class="report-export-item">
                        <div class="report-export-info">
                            <div class="report-export-title"><i class="bi bi-broadcast-pin"></i>Agentes en Tiempo Real</div>
                            <div class="report-export-description">Fotografía actual: estado efectivo, disponibilidad, heartbeat, colas, capacidad y casos abiertos.</div>
                        </div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('agents_realtime', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('agents_realtime', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>