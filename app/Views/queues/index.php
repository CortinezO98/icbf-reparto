<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $queues */
/** @var list<array<string,mixed>> $activeVersions */
/** @var string|null $error */
/** @var string|null $success */
/** @var list<array<string,mixed>> $agentCapacities */

$e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$csrf = $e(\App\Auth\Csrf::token());
?>

<div class="queues-page">
    <div class="page-heading queues-heading">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="queues-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                <span class="text-uppercase small fw-semibold text-secondary">Administración · Operación</span>
            </div>
            <h1>Colas de trabajo</h1>
            <p class="text-secondary mb-0">
                Configura las colas, sus estructuras de importación y la capacidad operativa de cada agente.
            </p>
        </div>
        <div class="queues-summary">
            <div class="summary-item">
                <span>Colas activas</span>
                <strong><?= count($queues) ?></strong>
            </div>
            <div class="summary-item">
                <span>Estructuras disponibles</span>
                <strong><?= count($activeVersions) ?></strong>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 shadow-sm queues-alert" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
            <div><?= $e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-start gap-2 shadow-sm queues-alert" role="alert">
            <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
            <div><?= $e($success) ?></div>
        </div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm queues-card queues-overview">
        <div class="card-body p-0">
            <div class="queues-section-head">
                <div>
                    <div class="section-kicker"><i class="bi bi-list-ul"></i> Configuración actual</div>
                    <h2>Colas de trabajo</h2>
                    <p>Consulta rápidamente qué estructuras están asociadas a cada cola.</p>
                </div>
                <span class="section-count"><?= count($queues) ?> cola<?= count($queues) === 1 ? '' : 's' ?></span>
            </div>

            <?php if ($queues !== []): ?>
                <div class="table-responsive">
                    <table class="table align-middle queues-table mb-0">
                        <thead>
                            <tr>
                                <th>Cola</th>
                                <th>Capacidad</th>
                                <th>Prioridad</th>
                                <th>Agentes</th>
                                <th>Estructuras activas</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($queues as $q): ?>
                            <?php
                            $structureLabels = array_values(array_filter(
                                explode('||', (string)($q['structure_labels'] ?? '')),
                                static fn(string $value): bool => trim($value) !== ''
                            ));
                            ?>
                            <tr>
                                <td>
                                    <div class="queue-name">
                                        <span class="queue-avatar"><i class="bi bi-diagram-2"></i></span>
                                        <div>
                                            <strong><?= $e($q['name']) ?></strong>
                                            <span><?= $e($q['code']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="data-pill"><?= (int)$q['default_capacity'] ?> casos</span></td>
                                <td><span class="priority-pill"><?= (int)$q['priority'] ?></span></td>
                                <td><span class="count-pill"><?= (int)$q['agent_count'] ?></span></td>
                                <td>
                                    <?php if ($structureLabels !== []): ?>
                                        <div class="structure-tags">
                                            <?php foreach ($structureLabels as $label): ?>
                                                <span class="structure-tag">
                                                    <i class="bi bi-file-earmark-spreadsheet"></i><?= $e($label) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">
                                            <i class="bi bi-dash-circle me-1"></i>Sin estructura asociada
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="queues-empty">
                    <i class="bi bi-diagram-3"></i>
                    <h3>Aún no hay colas configuradas</h3>
                    <p>Crea la primera cola para comenzar a organizar el reparto.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="row g-4 mt-0">
        <div class="col-xl-5">
            <section class="card border-0 shadow-sm queues-card h-100">
                <div class="card-body p-4">
                    <div class="section-kicker"><i class="bi bi-plus-circle"></i> Nueva configuración</div>
                    <h2 class="form-section-title">Crear cola</h2>
                    <p class="text-secondary small mb-4">
                        Define una cola que después podrás relacionar con una estructura activa.
                    </p>

                    <form method="post" action="/admin/queues/create" id="createQueueForm" novalidate>
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">

                        <div class="mb-3">
                            <label class="form-label" for="queueCode">Código técnico</label>
                            <input id="queueCode" class="form-control" name="code"
                                   maxlength="100" pattern="[A-Za-z0-9_]{2,100}"
                                   placeholder="Ej. PETICIONES" required autocomplete="off" spellcheck="false">
                            <div class="form-text">Solo letras, números y guion bajo.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="queueName">Nombre de la cola</label>
                            <input id="queueName" class="form-control" name="name"
                                   maxlength="180" placeholder="Ej. Direccionamiento de peticiones"
                                   required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="queueDescription">Descripción <span class="fw-normal text-secondary">(opcional)</span></label>
                            <textarea id="queueDescription" class="form-control" name="description"
                                      maxlength="500" rows="3"
                                      placeholder="Describe el propósito de esta cola."></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="queueCapacity">Capacidad predeterminada</label>
                                <div class="input-group">
                                    <input id="queueCapacity" class="form-control" type="number"
                                           name="default_capacity" min="1" max="1000" value="1" required>
                                    <span class="input-group-text">casos</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="queuePriority">Prioridad</label>
                                <input id="queuePriority" class="form-control" type="number"
                                       name="priority" min="0" max="100000" value="100" required>
                            </div>
                        </div>

                        <div class="form-help-card mt-4">
                            <i class="bi bi-info-circle"></i>
                            <div>
                                <strong>¿Qué significa capacidad?</strong>
                                <span>Es el máximo de casos abiertos que el motor puede mantener simultáneamente por agente.</span>
                            </div>
                        </div>

                        <button class="btn btn-brand w-100 mt-4" type="submit">
                            <i class="bi bi-plus-circle me-1"></i>Crear cola
                        </button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-7">
            <section class="card border-0 shadow-sm queues-card h-100 association-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                        <div>
                            <div class="section-kicker"><i class="bi bi-link-45deg"></i> Relación de configuración</div>
                            <h2 class="form-section-title">Asociar estructura activa</h2>
                        </div>
                        <span class="association-step-badge">2 pasos</span>
                    </div>

                    <p class="text-secondary small mb-4">
                        Selecciona una cola y una estructura activa. La asociación quedará disponible para las cargas de esa cola.
                    </p>

                    <?php if ($activeVersions !== [] && $queues !== []): ?>
                        <form method="post" action="/admin/queues/attach-structure" id="attachStructureForm">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">

                            <div class="association-step">
                                <div class="step-number">1</div>
                                <div class="flex-grow-1">
                                    <label class="form-label" for="queueSelect">¿A qué cola quieres asociarla?</label>
                                    <select id="queueSelect" class="form-select form-select-lg" name="queue_id" required>
                                        <option value="">Selecciona una cola...</option>
                                        <?php foreach ($queues as $q): ?>
                                            <option value="<?= (int)$q['id'] ?>">
                                                <?= $e($q['name']) ?> · <?= $e($q['code']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="association-connector" aria-hidden="true">
                                <span></span>
                            </div>

                            <div class="association-step">
                                <div class="step-number">2</div>
                                <div class="flex-grow-1">
                                    <label class="form-label" for="structureSelect">¿Qué estructura activa utilizará?</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-white"><i class="bi bi-file-earmark-spreadsheet text-brand"></i></span>
                                        <select id="structureSelect" class="form-select" name="structure_version_id" required>
                                            <option value="">Selecciona una estructura...</option>
                                            <?php foreach ($activeVersions as $v): ?>
                                                <option value="<?= (int)$v['id'] ?>"><?= $e($v['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-text">
                                        Solo aparecen versiones <strong>activas</strong>. Las versiones en borrador no pueden asociarse.
                                    </div>
                                </div>
                            </div>

                            <div class="association-preview" id="associationPreview" aria-live="polite">
                                <div class="preview-icon"><i class="bi bi-link-45deg"></i></div>
                                <div>
                                    <strong>Asociación pendiente</strong>
                                    <span>Selecciona ambos campos para revisar la relación antes de guardar.</span>
                                </div>
                            </div>

                            <button class="btn btn-brand btn-lg w-100 mt-3" type="submit" id="attachStructureButton" disabled>
                                <i class="bi bi-link-45deg me-1"></i>Asociar estructura a la cola
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="association-empty">
                            <div class="preview-icon"><i class="bi bi-info-circle"></i></div>
                            <h3>No hay opciones disponibles</h3>
                            <p class="mb-0">
                                <?php if ($queues === []): ?>
                                    Primero debes crear al menos una cola.
                                <?php else: ?>
                                    Primero crea y activa una versión de estructura desde <strong>Administración → Estructuras</strong>.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <section class="card border-0 shadow-sm queues-card capacity-card mt-4">
        <div class="card-body p-0">
            <div class="queues-section-head">
                <div>
                    <div class="section-kicker"><i class="bi bi-person-workspace"></i> Operación</div>
                    <h2>Capacidad por agente y cola</h2>
                    <p>Personaliza la capacidad de cada agente sin modificar la capacidad predeterminada de la cola.</p>
                </div>
            </div>

            <?php if ($agentCapacities !== []): ?>
                <div class="table-responsive">
                    <table class="table align-middle queues-table capacity-table mb-0">
                        <thead>
                            <tr>
                                <th>Cola</th>
                                <th>Agente</th>
                                <th>Casos abiertos</th>
                                <th>Capacidad efectiva</th>
                                <th>Configuración</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($agentCapacities as $item): ?>
                            <?php
                            $effectiveCapacity = $item['capacity_override'] !== null
                                ? (int)$item['capacity_override']
                                : (int)$item['default_capacity'];
                            $openCases = (int)$item['open_cases'];
                            $ratio = $effectiveCapacity > 0
                                ? min(100, (int)round(($openCases / $effectiveCapacity) * 100))
                                : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $e($item['queue_code']) ?></strong>
                                    <span class="table-secondary"><?= $e($item['queue_name']) ?></span>
                                </td>
                                <td>
                                    <div class="agent-cell">
                                        <span class="agent-avatar"><i class="bi bi-person"></i></span>
                                        <div>
                                            <strong><?= $e($item['agent_name']) ?></strong>
                                            <span><?= $e($item['agent_username']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="capacity-current">
                                        <strong><?= $openCases ?></strong>
                                        <span>abiertos</span>
                                    </div>
                                    <div class="capacity-progress" aria-label="<?= $ratio ?>% de capacidad utilizada">
                                        <span style="width:<?= $ratio ?>%"></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="capacity-value"><?= $effectiveCapacity ?></span>
                                    <?php if ($item['capacity_override'] !== null): ?>
                                        <span class="override-label">Personalizada</span>
                                    <?php else: ?>
                                        <span class="override-label">De la cola</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post"
                                          action="/admin/queues/<?= (int)$item['queue_id'] ?>/agents/<?= (int)$item['user_id'] ?>/capacity"
                                          class="capacity-form">
                                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                        <input class="form-control form-control-sm" type="number"
                                               name="capacity" min="1" max="1000"
                                               value="<?= $item['capacity_override'] !== null ? (int)$item['capacity_override'] : '' ?>"
                                               placeholder="<?= (int)$item['default_capacity'] ?>">
                                        <button class="btn btn-brand btn-sm" type="submit" title="Guardar capacidad">
                                            <i class="bi bi-check2"></i><span class="d-none d-lg-inline ms-1">Guardar</span>
                                        </button>
                                        <?php if ($item['capacity_override'] !== null): ?>
                                            <button class="btn btn-light border btn-sm" type="submit"
                                                    name="capacity" value=""
                                                    title="Restablecer capacidad de la cola">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="queues-empty">
                    <i class="bi bi-people"></i>
                    <h3>No hay agentes asociados a las colas activas</h3>
                    <p>Cuando existan agentes asociados, aquí podrás personalizar su capacidad.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
(() => {
    const name = document.getElementById('queueName');
    const code = document.getElementById('queueCode');
    if (name && code) {
        let codeEdited = false;
        code.addEventListener('input', () => {
            codeEdited = code.value.trim() !== '';
        });
        name.addEventListener('input', () => {
            if (codeEdited) return;
            code.value = name.value
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .replace(/_+/g, '_')
                .slice(0, 100);
        });
    }

    const queueSelect = document.getElementById('queueSelect');
    const structureSelect = document.getElementById('structureSelect');
    const button = document.getElementById('attachStructureButton');
    const preview = document.getElementById('associationPreview');

    if (!queueSelect || !structureSelect || !button || !preview) return;

    const updatePreview = () => {
        const queue = queueSelect.options[queueSelect.selectedIndex];
        const structure = structureSelect.options[structureSelect.selectedIndex];
        const ready = queueSelect.value !== '' && structureSelect.value !== '';

        button.disabled = !ready;

        if (!ready) {
            preview.className = 'association-preview';
            preview.innerHTML = `
                <div class="preview-icon"><i class="bi bi-link-45deg"></i></div>
                <div>
                    <strong>Asociación pendiente</strong>
                    <span>Selecciona ambos campos para revisar la relación antes de guardar.</span>
                </div>
            `;
            return;
        }

        preview.className = 'association-preview association-preview-ready';
        preview.innerHTML = `
            <div class="preview-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <strong>Listo para asociar</strong>
                <span><strong>${queue.textContent.trim()}</strong> utilizará <strong>${structure.textContent.trim()}</strong>.</span>
            </div>
        `;
    };

    queueSelect.addEventListener('change', updatePreview);
    structureSelect.addEventListener('change', updatePreview);
})();
</script>

<style>
.queues-page{max-width:1180px;margin:0 auto;}
.queues-heading{align-items:center;margin-bottom:1.5rem;}
.queues-heading h1{font-size:1.9rem;margin-bottom:.35rem;}
.queues-icon{
    width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);font-size:1.25rem;
}
.queues-summary{display:flex;gap:.65rem;}
.summary-item{
    min-width:118px;padding:.7rem .9rem;border:1px solid var(--border);
    border-radius:12px;background:#fff;box-shadow:0 .125rem .25rem rgba(0,0,0,.025);
}
.summary-item span{display:block;color:var(--text-soft);font-size:.73rem;text-transform:uppercase;letter-spacing:.4px;}
.summary-item strong{display:block;font-size:1.25rem;margin-top:.1rem;}
.queues-alert{border-radius:12px;}
.queues-card{border-radius:16px!important;overflow:hidden;}
.queues-section-head{
    display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;
    padding:1.2rem 1.25rem;border-bottom:1px solid var(--border);background:#fff;
}
.queues-section-head h2{font-size:1.2rem;font-weight:750;margin:.2rem 0 .15rem;}
.queues-section-head p{margin:0;color:var(--text-soft);font-size:.86rem;}
.section-kicker{
    color:var(--brand);font-size:.72rem;font-weight:800;text-transform:uppercase;
    letter-spacing:.65px;
}
.section-count,.association-step-badge{
    border:1px solid rgba(76,175,80,.2);background:var(--brand-soft);color:var(--brand);
    border-radius:999px;padding:.35rem .65rem;font-size:.76rem;font-weight:700;white-space:nowrap;
}
.queues-table thead th{padding:1rem 1.1rem!important;background:#f8faf9!important;}
.queues-table tbody td{padding:1rem 1.1rem!important;}
.queue-name,.agent-cell{display:flex;align-items:center;gap:.7rem;}
.queue-name strong,.agent-cell strong{display:block;font-size:.9rem;}
.queue-name span:not(.queue-avatar),.agent-cell span:not(.agent-avatar){
    display:block;color:var(--text-soft);font-size:.78rem;margin-top:.1rem;
}
.queue-avatar,.agent-avatar{
    width:36px;height:36px;display:grid;place-items:center;border-radius:10px;
    background:var(--brand-soft);color:var(--brand);flex:0 0 auto;
}
.data-pill,.priority-pill,.count-pill{
    display:inline-flex;align-items:center;border-radius:999px;padding:.32rem .6rem;
    background:#f5f7f8;border:1px solid #e4e8ea;font-size:.78rem;font-weight:700;
}
.priority-pill{background:#fff8e6;border-color:#f4e0a6;color:#8a6700;}
.structure-tags{display:flex;flex-wrap:wrap;gap:.4rem;}
.structure-tag{
    display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .5rem;border-radius:8px;
    background:#f1f8f2;color:#347a38;border:1px solid #d8ebda;font-size:.75rem;font-weight:650;
}
.form-section-title{font-size:1.25rem;font-weight:750;margin:.2rem 0 .35rem;}
.form-help-card{
    display:flex;gap:.65rem;padding:.75rem .85rem;background:#f7faf8;
    border:1px solid #e2eee4;border-radius:10px;color:#45634a;font-size:.78rem;
}
.form-help-card i{color:var(--brand);font-size:1rem;}
.form-help-card strong,.form-help-card span{display:block;}
.form-help-card span{margin-top:.1rem;color:#617066;}
.association-card{background:linear-gradient(180deg,#fff 0%,#fbfdfb 100%);}
.association-step{display:flex;gap:.8rem;align-items:flex-start;}
.step-number{
    width:30px;height:30px;border-radius:50%;display:grid;place-items:center;flex:0 0 30px;
    background:var(--brand);color:#fff;font-weight:800;font-size:.82rem;
}
.association-connector{height:16px;border-left:2px dashed #cfe4d1;margin-left:14px;}
.association-preview{
    display:flex;align-items:center;gap:.75rem;margin-top:1.15rem;padding:.8rem .9rem;
    border:1px dashed #ced8d0;border-radius:12px;background:#fafcfb;color:#657169;
}
.association-preview .preview-icon,.association-empty .preview-icon{
    width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:0 0 auto;
    background:#eef2ef;color:#6d7a71;
}
.association-preview strong,.association-preview span{display:block;}
.association-preview span{font-size:.78rem;margin-top:.12rem;}
.association-preview-ready{border-style:solid;border-color:#cde6cf;background:#f2faf3;color:#37643b;}
.association-preview-ready .preview-icon{background:var(--brand-soft);color:var(--brand);}
.association-empty{
    min-height:230px;display:flex;flex-direction:column;align-items:center;justify-content:center;
    text-align:center;padding:2rem;background:#fafcfb;border:1px dashed #d7ded9;border-radius:12px;
}
.association-empty h3{font-size:1rem;margin:.8rem 0 .25rem;}
.association-empty p{font-size:.82rem;color:var(--text-soft);max-width:430px;}
.capacity-table .table-secondary{display:block;color:var(--text-soft);font-size:.78rem;margin-top:.1rem;}
.capacity-current{display:flex;align-items:baseline;gap:.3rem;}
.capacity-current strong{font-size:1rem;}
.capacity-current span{font-size:.72rem;color:var(--text-soft);}
.capacity-progress{width:110px;height:5px;border-radius:99px;background:#edf0ee;margin-top:.35rem;overflow:hidden;}
.capacity-progress span{display:block;height:100%;background:var(--brand);border-radius:inherit;}
.capacity-value{
    display:inline-flex;min-width:38px;justify-content:center;padding:.3rem .5rem;
    border-radius:8px;background:var(--brand-soft);color:#357a39;font-weight:800;
}
.override-label{display:block;font-size:.7rem;color:var(--text-soft);margin-top:.2rem;}
.capacity-form{display:flex;gap:.4rem;align-items:center;min-width:210px;}
.capacity-form .form-control{max-width:95px;}
.queues-empty{padding:2.5rem 1rem;text-align:center;color:var(--text-soft);}
.queues-empty i{font-size:2rem;color:#a8b2aa;}
.queues-empty h3{font-size:1rem;color:#495057;margin:.7rem 0 .2rem;}
.queues-empty p{margin:0;font-size:.82rem;}
@media(max-width:768px){
    .queues-summary{width:100%;}
    .summary-item{flex:1;}
    .queues-section-head{flex-direction:column;}
    .capacity-form{min-width:180px;}
}
@media(max-width:576px){
    .queues-heading h1{font-size:1.55rem;}
    .queues-summary{display:grid;grid-template-columns:1fr 1fr;}
    .summary-item{min-width:0;}
}
</style>
