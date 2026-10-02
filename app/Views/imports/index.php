<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $batches */
/** @var list<array<string,mixed>> $versions */
/** @var string|null $error */
/** @var string|null $success */

$e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$csrf = $e(\App\Auth\Csrf::token());

$statusMeta = static function (string $status): array {
    return match ($status) {
        'COMPLETED', 'VALIDATED' => ['success', 'Validado'],
        'VALIDATED_WITH_ERRORS' => ['warning', 'Requiere revisión'],
        'FAILED' => ['danger', 'Error'],
        'PROCESSING', 'VALIDATING' => ['info', 'Procesando'],
        default => ['secondary', ucwords(strtolower(str_replace('_', ' ', $status)))],
    };
};
?>

<div class="imports-page">
    <div class="page-heading imports-heading">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="imports-icon"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span>
                <span class="text-uppercase small fw-semibold text-secondary">Operación · Importaciones</span>
            </div>
            <h1>Cargas de información</h1>
            <p class="text-secondary mb-0">
                Sube un archivo, selecciona su estructura y revisa la validación antes de crear los casos.
            </p>
        </div>
        <div class="import-security-badge">
            <i class="bi bi-shield-check"></i>
            <span>Validación segura</span>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 shadow-sm imports-alert" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div><?= $e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-start gap-2 shadow-sm imports-alert" role="alert">
            <i class="bi bi-check-circle-fill mt-1"></i>
            <div><?= $e($success) ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card border-0 shadow-sm import-card upload-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="section-kicker"><i class="bi bi-upload"></i> Nueva carga</div>
                    <h2 class="import-title">Validar un archivo</h2>
                    <p class="text-secondary import-intro">
                        Elige una estructura activa y carga el archivo correspondiente. El sistema revisará
                        encabezados, tipos de datos, duplicados y cada fila antes de permitir la confirmación.
                    </p>

                    <div class="import-steps" aria-label="Pasos de la importación">
                        <div class="import-step active">
                            <span>1</span>
                            <div><strong>Estructura</strong><small>Define cómo leer el archivo</small></div>
                        </div>
                        <div class="step-line"></div>
                        <div class="import-step">
                            <span>2</span>
                            <div><strong>Archivo</strong><small>Selecciona XLSX o CSV</small></div>
                        </div>
                        <div class="step-line"></div>
                        <div class="import-step">
                            <span>3</span>
                            <div><strong>Validación</strong><small>Revisa el resultado</small></div>
                        </div>
                    </div>

                    <form method="post" action="/imports/upload" enctype="multipart/form-data" id="importForm" novalidate>
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">

                        <div class="mb-4">
                            <label class="form-label" for="structureSelect">1. Estructura activa</label>
                            <select
                                id="structureSelect"
                                name="structure_version_id"
                                class="form-select form-select-lg"
                                required
                            >
                                <option value="">Selecciona una estructura...</option>
                                <?php foreach ($versions as $v): ?>
                                    <?php
                                    $queueOptions = [];
                                    foreach (explode('||', (string)($v['queue_options'] ?? '')) as $option) {
                                        if ($option === '' || !str_contains($option, ':')) {
                                            continue;
                                        }
                                        [$queueId, $queueName] = explode(':', $option, 2);
                                        $queueOptions[] = ['id' => (int)$queueId, 'name' => $queueName];
                                    }
                                    ?>
                                    <option
                                        value="<?= (int)$v['id'] ?>"
                                        data-csv="<?= (int)$v['allow_csv'] ?>"
                                        data-xlsx="<?= (int)$v['allow_xlsx'] ?>"
                                        data-queue-count="<?= (int)$v['queue_count'] ?>"
                                        data-queues="<?= $e(json_encode($queueOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                    >
                                        <?= $e($v['structure_name']) ?> · v<?= (int)$v['version_number'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo aparecen versiones activas y listas para recibir cargas.</div>
                        </div>

                        <div id="queueBox" class="queue-selection d-none mb-4">
                            <label class="form-label" for="queueSelect">Cola de destino</label>
                            <select id="queueSelect" name="queue_id" class="form-select">
                                <option value="">Selecciona una cola...</option>
                            </select>
                            <div id="queueHelp" class="form-text"></div>
                        </div>

                        <div class="selected-structure d-none mb-4" id="structureSummary" aria-live="polite">
                            <div class="summary-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                            <div class="flex-grow-1">
                                <span class="summary-label">Estructura seleccionada</span>
                                <strong id="selectedStructureName">—</strong>
                                <span id="selectedStructureRules">—</span>
                            </div>
                            <span id="selectedQueueState" class="summary-state"></span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="importFile">2. Archivo</label>
                            <label class="dropzone" for="importFile" id="dropzone">
                                <input
                                    id="importFile"
                                    type="file"
                                    name="import_file"
                                    accept=".xlsx,.csv"
                                    required
                                >
                                <span class="dropzone-icon" aria-hidden="true"><i class="bi bi-cloud-arrow-up"></i></span>
                                <span class="dropzone-copy">
                                    <strong>Arrastra tu archivo aquí</strong>
                                    <span>o haz clic para seleccionarlo</span>
                                </span>
                                <small id="fileHelp">XLSX o CSV · máximo 20 MB</small>
                            </label>
                            <div id="fileSelected" class="file-selected d-none" aria-live="polite">
                                <span class="file-selected-icon"><i class="bi bi-file-earmark-check"></i></span>
                                <div class="flex-grow-1">
                                    <strong id="fileName">—</strong>
                                    <span id="fileSize">—</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-light border" id="clearFile">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="security-note">
                            <i class="bi bi-shield-lock"></i>
                            <div>
                                <strong>La carga se valida antes de procesarse</strong>
                                <span>Se verifica tamaño, tipo de archivo, estructura de encabezados, duplicados y contenido fila a fila.</span>
                            </div>
                        </div>

                        <button class="btn btn-brand btn-lg w-100 mt-4" type="submit" id="validateButton" disabled>
                            <i class="bi bi-search me-1"></i>Validar archivo
                        </button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card border-0 shadow-sm import-card h-100">
                <div class="card-body p-4">
                    <div class="section-kicker"><i class="bi bi-shield-check"></i> Controles de seguridad</div>
                    <h2 class="import-side-title">Antes de procesar</h2>
                    <p class="text-secondary small">
                        Estas validaciones se aplican en el servidor, independientemente de lo que indique el navegador.
                    </p>

                    <div class="security-list">
                        <div><i class="bi bi-check-circle-fill"></i><span><strong>20 MB</strong><small>Tamaño máximo</small></span></div>
                        <div><i class="bi bi-check-circle-fill"></i><span><strong>MIME + extensión</strong><small>Tipo de archivo validado</small></span></div>
                        <div><i class="bi bi-check-circle-fill"></i><span><strong>SHA-256</strong><small>Detección de archivos repetidos</small></span></div>
                        <div><i class="bi bi-check-circle-fill"></i><span><strong>Encabezados</strong><small>Comparación con la estructura</small></span></div>
                        <div><i class="bi bi-check-circle-fill"></i><span><strong>Fila a fila</strong><small>Validación de datos</small></span></div>
                    </div>

                    <div class="tip-card">
                        <i class="bi bi-lightbulb"></i>
                        <div>
                            <strong>Recomendación</strong>
                            <span>Utiliza exactamente los encabezados definidos en la estructura activa para evitar rechazos.</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="card border-0 shadow-sm import-card mt-4">
        <div class="card-body p-0">
            <div class="imports-section-head">
                <div>
                    <div class="section-kicker"><i class="bi bi-clock-history"></i> Seguimiento</div>
                    <h2>Lotes recientes</h2>
                    <p>Consulta el resultado de las cargas anteriores y continúa con su validación.</p>
                </div>
                <span class="section-count"><?= count($batches) ?> registro<?= count($batches) === 1 ? '' : 's' ?></span>
            </div>

            <?php if ($batches !== []): ?>
                <div class="table-responsive">
                    <table class="table align-middle imports-table mb-0">
                        <thead>
                            <tr>
                                <th>Lote</th>
                                <th>Estructura</th>
                                <th>Cola</th>
                                <th>Archivo</th>
                                <th>Estado</th>
                                <th class="text-end">Resultado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($batches as $b): ?>
                            <?php [$statusClass, $statusLabel] = $statusMeta((string)$b['status']); ?>
                            <tr>
                                <td>
                                    <strong class="batch-number"><?= $e($b['batch_number']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= $e($b['structure_name']) ?></strong>
                                    <span class="table-secondary">Estructura activa</span>
                                </td>
                                <td>
                                    <?php if (!empty($b['queue_name'])): ?>
                                        <span class="queue-tag"><i class="bi bi-diagram-2"></i><?= $e($b['queue_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="file-table-cell">
                                        <i class="bi bi-file-earmark-spreadsheet"></i>
                                        <span><?= $e($b['original_filename']) ?></span>
                                    </div>
                                </td>
                                <td><span class="status-badge status-<?= $statusClass ?>"><?= $e($statusLabel) ?></span></td>
                                <td class="text-end">
                                    <div class="result-counts">
                                        <span title="Filas válidas"><i class="bi bi-check2-circle"></i><?= (int)$b['valid_rows'] ?></span>
                                        <span title="Errores"><i class="bi bi-exclamation-circle"></i><?= (int)$b['invalid_rows'] ?></span>
                                        <span title="Duplicadas"><i class="bi bi-copy"></i><?= (int)$b['duplicate_rows'] ?></span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-light border btn-sm" href="/imports/<?= (int)$b['id'] ?>">
                                        <i class="bi bi-eye me-1"></i>Ver detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="imports-empty">
                    <i class="bi bi-inbox"></i>
                    <h3>No hay cargas recientes</h3>
                    <p>Cuando realices una importación, su resultado aparecerá aquí.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
(() => {
    const structure = document.getElementById('structureSelect');
    const queueBox = document.getElementById('queueBox');
    const queueSelect = document.getElementById('queueSelect');
    const queueHelp = document.getElementById('queueHelp');
    const summary = document.getElementById('structureSummary');
    const summaryName = document.getElementById('selectedStructureName');
    const summaryRules = document.getElementById('selectedStructureRules');
    const queueState = document.getElementById('selectedQueueState');
    const file = document.getElementById('importFile');
    const dropzone = document.getElementById('dropzone');
    const fileSelected = document.getElementById('fileSelected');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const clearFile = document.getElementById('clearFile');
    const button = document.getElementById('validateButton');
    const form = document.getElementById('importForm');

    const maxBytes = 20 * 1024 * 1024;

    const formatSize = bytes => {
        if (bytes < 1024 * 1024) return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        return (bytes / 1024 / 1024).toFixed(2) + ' MB';
    };

    const selectedOption = () => structure?.options[structure.selectedIndex];

    const update = () => {
        const option = selectedOption();
        const hasStructure = !!structure?.value;
        const queueCount = Number(option?.dataset.queueCount || 0);
        let queues = [];

        try {
            queues = JSON.parse(option?.dataset.queues || '[]');
        } catch (_) {
            queues = [];
        }

        queueSelect.innerHTML = '<option value="">Selecciona una cola...</option>';

        if (!hasStructure) {
            queueBox.classList.add('d-none');
            summary.classList.add('d-none');
            button.disabled = true;
            return;
        }

        summary.classList.remove('d-none');
        summaryName.textContent = option.textContent.trim();

        const formats = [];
        if (option.dataset.xlsx === '1') formats.push('XLSX');
        if (option.dataset.csv === '1') formats.push('CSV');
        summaryRules.textContent = 'Formatos permitidos: ' + (formats.join(' / ') || 'ninguno');

        if (queueCount === 1 && queues.length === 1) {
            const q = queues[0];
            queueSelect.innerHTML = '<option value="' + String(q.id) + '">' +
                String(q.name).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;') +
                '</option>';
            queueSelect.value = String(q.id);
            queueBox.classList.remove('d-none');
            queueSelect.disabled = true;
            queueHelp.textContent = 'Esta estructura tiene una única cola asociada. Se seleccionará automáticamente.';
            queueState.textContent = 'Cola automática';
        } else if (queueCount > 1) {
            queues.forEach(q => {
                const opt = document.createElement('option');
                opt.value = String(q.id);
                opt.textContent = String(q.name);
                queueSelect.appendChild(opt);
            });
            queueSelect.disabled = false;
            queueBox.classList.remove('d-none');
            queueHelp.textContent = 'Esta estructura tiene varias colas asociadas. Selecciona dónde deben quedar los casos.';
            queueState.textContent = 'Requiere selección';
        } else {
            queueBox.classList.remove('d-none');
            queueSelect.disabled = true;
            queueHelp.textContent = 'No hay una cola activa asociada a esta estructura. Debes asociarla desde Administración → Colas.';
            queueState.textContent = 'Sin cola';
        }

        validateState();
    };

    const validateState = () => {
        const option = selectedOption();
        const hasStructure = !!structure?.value;
        const queueCount = Number(option?.dataset.queueCount || 0);
        const queueReady = queueCount === 1 || (queueCount > 1 && !!queueSelect.value);
        const hasFile = file.files && file.files.length === 1;
        button.disabled = !(hasStructure && queueReady && hasFile && file.files[0].size <= maxBytes);
    };

    structure.addEventListener('change', update);
    queueSelect.addEventListener('change', validateState);

    const setFile = selected => {
        if (!selected) {
            fileSelected.classList.add('d-none');
            dropzone.classList.remove('d-none');
            validateState();
            return;
        }

        if (selected.size > maxBytes) {
            file.value = '';
            Swal.fire({
                icon: 'error',
                title: 'Archivo demasiado grande',
                text: 'El tamaño máximo permitido es de 20 MB.',
                confirmButtonColor: '#4CAF50'
            });
            return;
        }

        const ext = selected.name.toLowerCase().split('.').pop();
        if (!['xlsx', 'csv'].includes(ext)) {
            file.value = '';
            Swal.fire({
                icon: 'error',
                title: 'Formato no permitido',
                text: 'Selecciona un archivo XLSX o CSV.',
                confirmButtonColor: '#4CAF50'
            });
            return;
        }

        fileName.textContent = selected.name;
        fileSize.textContent = formatSize(selected.size);
        fileSelected.classList.remove('d-none');
        dropzone.classList.add('d-none');
        validateState();
    };

    file.addEventListener('change', () => setFile(file.files?.[0] || null));

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, event => {
            event.preventDefault();
            dropzone.classList.add('dropzone-active');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, event => {
            event.preventDefault();
            dropzone.classList.remove('dropzone-active');
        });
    });

    dropzone.addEventListener('drop', event => {
        const dropped = event.dataTransfer?.files?.[0];
        if (!dropped) return;
        try {
            const transfer = new DataTransfer();
            transfer.items.add(dropped);
            file.files = transfer.files;
        } catch (_) {}
        setFile(dropped);
    });

    clearFile.addEventListener('click', () => {
        file.value = '';
        setFile(null);
    });

    form.addEventListener('submit', event => {
        validateState();
        if (button.disabled) {
            event.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Completa la carga',
                text: 'Selecciona una estructura, una cola cuando sea necesaria y un archivo válido.',
                confirmButtonColor: '#4CAF50'
            });
        }
    });

    update();
})();
</script>

<style>
.imports-page{max-width:1180px;margin:0 auto;}
.imports-heading{align-items:center;margin-bottom:1.5rem;}
.imports-heading h1{font-size:1.9rem;margin-bottom:.35rem;}
.imports-icon{
    width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);font-size:1.25rem;
}
.import-security-badge{
    display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .8rem;
    background:#f2faf3;border:1px solid #d7ebd9;border-radius:999px;color:#39763d;
    font-size:.78rem;font-weight:700;white-space:nowrap;
}
.imports-alert{border-radius:12px;}
.import-card{border-radius:16px!important;overflow:hidden;}
.section-kicker{
    color:var(--brand);font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.65px;
}
.import-title{font-size:1.35rem;font-weight:800;margin:.2rem 0 .35rem;}
.import-intro{font-size:.86rem;line-height:1.55;max-width:720px;}
.import-steps{
    display:flex;align-items:center;gap:.55rem;padding:1rem;margin:1.25rem 0 1.5rem;
    background:#f8faf9;border:1px solid #e5ebe6;border-radius:12px;
}
.import-step{display:flex;align-items:center;gap:.5rem;min-width:0;}
.import-step>span{
    width:30px;height:30px;border-radius:50%;display:grid;place-items:center;
    background:#e9eeea;color:#66736a;font-weight:800;font-size:.8rem;flex:0 0 auto;
}
.import-step.active>span{background:var(--brand);color:#fff;}
.import-step strong,.import-step small{display:block;}
.import-step strong{font-size:.8rem;}
.import-step small{font-size:.68rem;color:var(--text-soft);}
.step-line{height:1px;background:#dce4de;flex:1;min-width:15px;}
.form-label{font-weight:700;}
.selected-structure{
    display:flex;align-items:center;gap:.75rem;padding:.8rem .9rem;
    background:#f3faf4;border:1px solid #d7ead9;border-radius:12px;
}
.summary-icon{
    width:38px;height:38px;border-radius:10px;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);flex:0 0 auto;
}
.summary-label,.selected-structure strong,.selected-structure span:not(.summary-state){
    display:block;
}
.summary-label{font-size:.68rem;color:#6c776f;text-transform:uppercase;font-weight:800;letter-spacing:.4px;}
.selected-structure strong{font-size:.88rem;margin-top:.1rem;}
.selected-structure #selectedStructureRules{font-size:.74rem;color:#66736a;margin-top:.1rem;}
.summary-state{
    margin-left:auto;padding:.3rem .55rem;border-radius:999px;background:#fff;border:1px solid #dce8de;
    color:#4a6250;font-size:.7rem;font-weight:700;white-space:nowrap;
}
.queue-selection{
    padding:.9rem;background:#fafcfb;border:1px solid #e1e8e2;border-radius:12px;
}
.dropzone{
    min-height:170px;border:1px dashed #cbd6cd;border-radius:14px;background:#fcfdfc;
    display:flex!important;flex-direction:column!important;align-items:center!important;
    justify-content:center!important;text-align:center!important;gap:0;
    padding:1.5rem 1rem;cursor:pointer;transition:border-color .15s ease,background .15s ease;
}
.dropzone:hover,.dropzone-active{
    border-color:rgba(76,175,80,.7);background:#f4faf5;
}
.dropzone input{display:none!important;}
.dropzone-icon{
    width:48px;height:48px;border-radius:13px;display:grid!important;place-items:center;
    align-self:center!important;margin:0 auto .75rem!important;
    background:var(--brand-soft);color:var(--brand);font-size:1.35rem;
}
.dropzone-copy{
    display:flex!important;flex-direction:column;align-items:center;gap:.15rem;
}
.dropzone-copy strong{font-size:.92rem;line-height:1.35;}
.dropzone-copy span{font-size:.76rem;color:var(--text-soft);line-height:1.35;}
.dropzone small{font-size:.68rem;color:#7b867e;margin-top:.55rem;}
.file-selected{
    display:flex;align-items:center;gap:.7rem;padding:.8rem .9rem;border:1px solid #d8e5da;
    background:#f4faf5;border-radius:12px;
}
.file-selected-icon{
    width:38px;height:38px;border-radius:10px;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);
}
.file-selected strong,.file-selected span:not(.file-selected-icon){display:block;}
.file-selected strong{font-size:.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.file-selected #fileSize{font-size:.7rem;color:var(--text-soft);margin-top:.1rem;}
.security-note{
    display:flex;gap:.65rem;padding:.7rem .85rem;background:#fafcfb;border:1px solid #e5eae6;
    border-radius:10px;margin-top:.9rem;
}
.security-note>i{color:var(--brand);font-size:1.05rem;}
.security-note strong,.security-note span{display:block;}
.security-note strong{font-size:.74rem;}
.security-note span{font-size:.68rem;color:var(--text-soft);margin-top:.1rem;}
.import-side-title{font-size:1.2rem;font-weight:800;margin:.25rem 0 .35rem;}
.security-list{display:grid;gap:.7rem;margin:1.25rem 0;}
.security-list>div{display:flex;gap:.65rem;align-items:flex-start;padding:.65rem .7rem;border:1px solid #edf0ee;border-radius:10px;}
.security-list i{color:var(--brand);margin-top:.1rem;}
.security-list strong,.security-list small{display:block;}
.security-list strong{font-size:.78rem;}
.security-list small{font-size:.68rem;color:var(--text-soft);margin-top:.05rem;}
.tip-card{
    display:flex;gap:.65rem;padding:.8rem;background:#fff9e9;border:1px solid #f1e3b8;
    border-radius:10px;color:#6f5b24;
}
.tip-card>i{color:#b38a16;}
.tip-card strong,.tip-card span{display:block;}
.tip-card strong{font-size:.75rem;}
.tip-card span{font-size:.68rem;margin-top:.1rem;}
.imports-section-head{
    display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;
    padding:1.2rem 1.25rem;border-bottom:1px solid var(--border);background:#fff;
}
.imports-section-head h2{font-size:1.2rem;font-weight:800;margin:.2rem 0 .15rem;}
.imports-section-head p{margin:0;color:var(--text-soft);font-size:.84rem;}
.section-count{
    border:1px solid rgba(76,175,80,.2);background:var(--brand-soft);color:var(--brand);
    border-radius:999px;padding:.35rem .65rem;font-size:.76rem;font-weight:700;white-space:nowrap;
}
.imports-table thead th{padding:1rem 1.05rem!important;background:#f8faf9!important;}
.imports-table tbody td{padding:.95rem 1.05rem!important;}
.batch-number{font-size:.78rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
.table-secondary{display:block;color:var(--text-soft);font-size:.7rem;margin-top:.1rem;}
.queue-tag{
    display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .5rem;border-radius:8px;
    background:#f3f8f4;border:1px solid #dce9de;color:#416a47;font-size:.72rem;font-weight:650;
}
.file-table-cell{display:flex;align-items:center;gap:.45rem;max-width:210px;}
.file-table-cell i{color:var(--brand);}
.file-table-cell span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem;}
.status-badge{
    display:inline-flex;align-items:center;padding:.3rem .55rem;border-radius:999px;
    font-size:.7rem;font-weight:800;border:1px solid transparent;white-space:nowrap;
}
.status-success{background:#eaf7ec;color:#287333;border-color:#cfe8d2;}
.status-warning{background:#fff8e5;color:#866500;border-color:#f0dfaa;}
.status-danger{background:#fdecec;color:#a12d2d;border-color:#f1cccc;}
.status-info{background:#eaf4fb;color:#21658b;border-color:#cfe5f2;}
.status-secondary{background:#f1f3f2;color:#66706a;border-color:#dfe4e1;}
.result-counts{display:flex;justify-content:flex-end;gap:.65rem;font-size:.72rem;color:#637067;}
.result-counts span{display:inline-flex;align-items:center;gap:.2rem;}
.result-counts span:first-child{color:#34743a;}
.result-counts span:nth-child(2){color:#9a6c00;}
.result-counts span:nth-child(3){color:#6b6575;}
.imports-empty{padding:2.5rem 1rem;text-align:center;color:var(--text-soft);}
.imports-empty i{font-size:2rem;color:#a8b2aa;}
.imports-empty h3{font-size:1rem;color:#495057;margin:.7rem 0 .2rem;}
.imports-empty p{margin:0;font-size:.82rem;}
@media(max-width:768px){
    .imports-heading{align-items:flex-start;}
    .import-steps{overflow:auto;}
    .import-step small{display:none;}
    .imports-section-head{flex-direction:column;}
    .result-counts{justify-content:flex-start;}
}
@media(max-width:576px){
    .imports-heading h1{font-size:1.55rem;}
    .import-steps{gap:.35rem;}
    .step-line{min-width:8px;}
}
</style>
