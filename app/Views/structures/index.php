<?php
/** @var list<array<string,mixed>> $structures */
$e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="structure-index-page">
    <div class="page-heading">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="structure-icon"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i></span>
                <span class="text-uppercase small fw-semibold text-secondary">Administración</span>
            </div>
            <h1 class="mb-1">Estructuras de importación</h1>
            <p class="text-secondary mb-0">Plantillas versionadas para validar archivos Excel y CSV.</p>
        </div>
        <a class="btn btn-brand" href="/admin/structures/create">
            <i class="bi bi-plus-circle me-1"></i>
            Nueva estructura
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="metric-card bg-white shadow-sm">
                <span>Estructuras</span>
                <strong><?= count($structures) ?></strong>
            </div>
        </div>
        <div class="col-md-4">
            <?php $withActive = count(array_filter($structures, static fn(array $s): bool => $s['active_version'] !== null)); ?>
            <div class="metric-card bg-white shadow-sm">
                <span>Con versión activa</span>
                <strong><?= $withActive ?></strong>
            </div>
        </div>
        <div class="col-md-4">
            <?php $totalVersions = array_sum(array_map(static fn(array $s): int => (int)$s['version_count'], $structures)); ?>
            <div class="metric-card bg-white shadow-sm">
                <span>Versiones configuradas</span>
                <strong><?= $totalVersions ?></strong>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white p-4">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Catálogo de estructuras</h2>
                    <p class="small text-secondary mb-0">Cada estructura puede tener varias versiones históricas.</p>
                </div>
                <span class="badge text-bg-light border"><?= count($structures) ?> registradas</span>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if ($structures === []): ?>
                <div class="empty py-5">
                    <i class="bi bi-file-earmark-plus d-block mb-2"></i>
                    <h3 class="h6 fw-bold">No hay estructuras configuradas</h3>
                    <p class="text-secondary">Crea la primera estructura para comenzar a definir cargas.</p>
                    <a class="btn btn-brand" href="/admin/structures/create">
                        <i class="bi bi-plus-circle me-1"></i>Nueva estructura
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Código</th>
                                <th>Nombre</th>
                                <th class="text-center">Versiones</th>
                                <th>Versión activa</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($structures as $s): ?>
                            <tr>
                                <td class="ps-4">
                                    <code><?= $e($s['code']) ?></code>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= $e($s['name']) ?></div>
                                    <?php if (!empty($s['description'])): ?>
                                        <div class="small text-secondary"><?= $e($s['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill text-bg-light border"><?= (int)$s['version_count'] ?></span>
                                </td>
                                <td>
                                    <?php if ($s['active_version'] !== null): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>v<?= (int)$s['active_version'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-secondary small">Sin activar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a class="btn btn-light border btn-sm" href="/admin/structures/<?= (int)$s['id'] ?>">
                                        <i class="bi bi-gear me-1"></i>Configurar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.structure-index-page .structure-icon{
    width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);font-size:1.2rem;
}
.structure-index-page .metric-card{
    border:1px solid var(--border);border-radius:14px;padding:1rem 1.1rem;
}
.structure-index-page .metric-card span{
    color:var(--text-soft);font-size:.82rem;
}
.structure-index-page .metric-card strong{
    display:block;margin-top:.2rem;font-size:1.55rem;
}
.structure-index-page table code{
    color:#495057;background:#f1f3f5;padding:.25rem .45rem;border-radius:6px;
}
</style>
