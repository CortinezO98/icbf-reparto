<?php
/** @var list<array<string,mixed>> $agents */
/** @var array<string,int> $summary */
?>
<div class="page-heading">
    <div>
        <h1>Estado de agentes</h1>
        <p class="muted">Seguimiento operativo de presencia, carga activa y capacidad de asignación.</p>
    </div>
    <div class="refresh-note">Actualización automática cada 15 segundos</div>
</div>

<div class="metric-grid" id="presenceMetrics">
    <div class="metric-card">
        <span>Agentes disponibles</span>
        <strong data-kpi="available_agents"><?= (int)($summary['available_agents'] ?? 0) ?></strong>
    </div>
    <div class="metric-card">
        <span>Capacidad libre</span>
        <strong data-kpi="available_capacity"><?= (int)($summary['available_capacity'] ?? 0) ?></strong>
    </div>
    <div class="metric-card">
        <span>Casos pendientes</span>
        <strong data-kpi="pending_queue"><?= (int)($summary['pending_queue'] ?? 0) ?></strong>
    </div>
    <div class="metric-card">
        <span>Total agentes</span>
        <strong data-kpi="total_agents"><?= (int)($summary['total_agents'] ?? 0) ?></strong>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <div style="overflow:auto">
        <table>
            <thead>
                <tr>
                    <th>Agente</th>
                    <th>Estado</th>
                    <th>Colas</th>
                    <th>Carga activa</th>
                    <th>Capacidad libre</th>
                    <th>Última señal</th>
                </tr>
            </thead>
            <tbody id="presenceAgentRows">
            <?php foreach ($agents as $agent): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string)$agent['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <div class="muted"><?= htmlspecialchars((string)$agent['username'], ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td>
                        <span class="presence-badge">
                            <span class="presence-dot" style="background:<?= htmlspecialchars((string)$agent['status_color'], ENT_QUOTES, 'UTF-8') ?>"></span>
                            <?= htmlspecialchars((string)$agent['status_label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars((string)($agent['queue_codes'] ?: 'Sin cola'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$agent['open_cases'] ?> / <?= (int)$agent['configured_capacity'] ?></td>
                    <td><?= (int)$agent['free_capacity'] ?></td>
                    <td><?= htmlspecialchars((string)($agent['last_heartbeat_at'] ?: 'Sin conexión'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(() => {
    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;')
        .replaceAll('>','&gt;').replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    const refresh = async () => {
        try {
            const response = await fetch('/supervisor/agents/data', {
                headers:{'Accept':'application/json'},
                credentials:'same-origin',
                cache:'no-store'
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data.ok) return;

            Object.entries(data.summary || {}).forEach(([key,value]) => {
                const el = document.querySelector(`[data-kpi="${key}"]`);
                if (el) el.textContent = String(value);
            });

            const tbody = document.getElementById('presenceAgentRows');
            if (!tbody) return;

            tbody.innerHTML = (data.agents || []).map(agent => `
                <tr>
                    <td><strong>${escapeHtml(agent.full_name)}</strong><div class="muted">${escapeHtml(agent.username)}</div></td>
                    <td><span class="presence-badge"><span class="presence-dot" style="background:${escapeHtml(agent.status_color)}"></span>${escapeHtml(agent.status_label)}</span></td>
                    <td>${escapeHtml(agent.queue_codes || 'Sin cola')}</td>
                    <td>${Number(agent.open_cases || 0)} / ${Number(agent.configured_capacity || 0)}</td>
                    <td>${Number(agent.free_capacity || 0)}</td>
                    <td>${escapeHtml(agent.last_heartbeat_at || 'Sin conexión')}</td>
                </tr>
            `).join('');
        } catch (_) {}
    };

    window.setInterval(refresh, 15000);
})();
</script>
