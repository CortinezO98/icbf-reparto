<?php
/** @var list<array<string,mixed>> $cases */
/** @var array<string,mixed> $pagination */
/** @var list<array<string,mixed>> $queues */
/** @var string $scopeLabel */

$search = trim((string)($_GET['search'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
$queueId = (string)($_GET['queue_id'] ?? '');
?>
<style>
.cases-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}.cases-head h1{margin:0}.case-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-top:18px;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,.04)}.case-filter{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;padding:15px;align-items:end}.case-table{min-width:1000px}.case-pill{padding:4px 8px;border-radius:999px;font-size:.76rem;font-weight:750;background:#eef2ff;color:#4338ca}.case-state{padding:4px 8px;border-radius:999px;font-size:.76rem;font-weight:750;background:#e2f7e8;color:#17713c}.case-action{padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#334155;background:#fff}@media(max-width:800px){.case-filter{grid-template-columns:1fr 1fr}}@media(max-width:520px){.case-filter{grid-template-columns:1fr}}
</style>

<div class="cases-head">
    <div><h1><i class="bi bi-inbox text-brand me-2"></i><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></h1><div class="muted">Consulta y gestión de casos del módulo de reparto.</div></div>
</div>

<div class="case-panel">
    <form method="get" action="/cases" class="case-filter">
        <div><label>Buscar</label><input name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Caso, radicado o tipo de petición"></div>
        <div><label>Estado</label><select name="state"><option value="">Todos</option><?php foreach (['PENDING_ASSIGNMENT','ASSIGNED','CLOSED'] as $s): ?><option value="<?= $s ?>" <?= $state === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div><label>Cola</label><select name="queue_id"><option value="">Todas</option><?php foreach ($queues as $q): ?><option value="<?= (int)$q['id'] ?>" <?= $queueId === (string)$q['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$q['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div><button class="btn btn-primary" type="submit">Filtrar</button></div>
    </form>

    <div style="overflow:auto">
        <table class="case-table">
            <thead><tr><th>Caso</th><th>Radicado/SIM</th><th>Tipo petición</th><th>Cola</th><th>Estado</th><th>Gestión</th><th>Agente</th><th>Radicación</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cases as $case): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string)$case['case_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars((string)$case['external_key'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($case['petition_type'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="case-pill"><?= htmlspecialchars((string)($case['queue_code'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><span class="case-state"><?= htmlspecialchars((string)$case['current_state'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars((string)($case['current_management_type_code'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($case['assigned_user_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($case['radicated_at'] ?: $case['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a class="case-action" href="/cases/<?= (int)$case['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($cases === []): ?><tr><td colspan="9" class="muted">No hay casos para mostrar.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
