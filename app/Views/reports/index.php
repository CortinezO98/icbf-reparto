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

    foreach (['queue_id', 'agent_id', 'state', 'sla'] as $key) {
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
.reports-export-modal .modal-header{padding:17px 20px;border-bottom:1px solid #e4e7ec}
.reports-export-modal .modal-title{font-size:1.05rem;font-weight:800;color:#172033}
.reports-export-modal .modal-body{padding:18px}
.reports-export-intro{font-size:.78rem;line-height:1.55;color:#667085;background:#f8fafb;border:1px solid #e5e7eb;border-radius:10px;padding:11px 13px;margin-bottom:16px}
.reports-export-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.report-export-item{display:grid;grid-template-columns:40px minmax(0,1fr);grid-template-rows:auto auto;align-items:center;column-gap:12px;row-gap:12px;border:1px solid #dfe3e8;border-radius:12px;padding:14px;background:#fff;transition:border-color .15s ease,box-shadow .15s ease}
.report-export-item:hover{border-color:#cbd5df;box-shadow:0 5px 16px rgba(16,24,40,.06)}
.report-export-icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;background:rgba(76,175,80,.10);color:var(--color-primary);font-size:1.05rem}
.report-export-info{min-width:0}
.report-export-title{font-size:.88rem;font-weight:800;color:#344054;line-height:1.25}
.report-export-description{font-size:.73rem;line-height:1.45;color:#667085;margin-top:4px}
.report-export-actions{grid-column:1 / -1;display:flex;justify-content:flex-end;gap:8px;padding-top:2px;border-top:1px solid #eef0f2}
.report-export-actions .btn{font-size:.74rem;padding:6px 11px}
.report-export-actions .btn-excel{border-color:#198754;color:#198754}
.report-export-actions .btn-excel:hover{background:#198754;color:#fff}
@media(max-width:767.98px){.reports-export-grid{grid-template-columns:1fr}}
@media(max-width:575.98px){.report-export-item{grid-template-columns:36px minmax(0,1fr);padding:12px}.report-export-icon{width:36px;height:36px}.report-export-actions{justify-content:stretch}.report-export-actions .btn{flex:1}}
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
<div class="modal fade reports-export-modal" id="reportsExportModal" tabindex="-1" aria-labelledby="reportsExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="reportsExportModalLabel"><i class="bi bi-download me-2"></i>Exportar reportes</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="reports-export-intro">
                    <i class="bi bi-info-circle me-1"></i>
                    Los reportes de <strong>Casos / SLA</strong>, <strong>Resumen de Agentes</strong> e
                    <strong>Histórico de Agentes</strong> utilizan el periodo seleccionado:
                    <strong><?= htmlspecialchars($data['period'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
                    El reporte de <strong>Estado en Tiempo Real</strong> siempre exporta la fotografía actual.
                </div>
                <div class="reports-export-grid">
                    <div class="report-export-item">
                        <div class="report-export-icon"><i class="bi bi-briefcase"></i></div>
                        <div class="report-export-info"><div class="report-export-title">Casos / SLA</div><div class="report-export-description">Detalle de casos, tiempos de gestión y cumplimiento ANS.</div></div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('cases', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('cases', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>
                    <div class="report-export-item">
                        <div class="report-export-icon"><i class="bi bi-person-lines-fill"></i></div>
                        <div class="report-export-info"><div class="report-export-title">Agentes — Resumen</div><div class="report-export-description">Casos asignados, resueltos, vencidos, primera gestión y cumplimiento ANS.</div></div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('agents_summary', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('agents_summary', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>
                    <div class="report-export-item">
                        <div class="report-export-icon"><i class="bi bi-clock-history"></i></div>
                        <div class="report-export-info"><div class="report-export-title">Agentes — Histórico</div><div class="report-export-description">Transiciones de estado con inicio, fin, duración y origen.</div></div>
                        <div class="report-export-actions">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportUrl('agents_history', 'csv'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                            <a class="btn btn-outline-success btn-excel" href="<?= htmlspecialchars($exportUrl('agents_history', 'xlsx'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                        </div>
                    </div>
                    <div class="report-export-item">
                        <div class="report-export-icon"><i class="bi bi-broadcast-pin"></i></div>
                        <div class="report-export-info"><div class="report-export-title">Agentes — Tiempo real</div><div class="report-export-description">Estado actual, disponibilidad, heartbeat, colas y capacidad.</div></div>
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
