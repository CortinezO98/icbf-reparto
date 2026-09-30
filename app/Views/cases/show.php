<?php
/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $managements */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $managementTypes */
/** @var list<array<string,mixed>> $escalations */
/** @var list<array<string,mixed>> $petitionTypes */
/** @var bool $canManage */
/** @var string|null $success */
/** @var string|null $error */

$source = json_decode((string)($case['source_normalized_json'] ?? ''), true);
$source = is_array($source) ? $source : [];
?>
<style>
.case-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.case-head h1{margin:0}.case-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;margin-top:18px}.case-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;box-shadow:0 4px 14px rgba(15,23,42,.04)}.data-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.datum{background:#f8fafc;border-radius:8px;padding:10px}.datum small{display:block;color:#64748b}.datum strong{display:block;margin-top:3px}.timeline{display:grid;gap:10px}.timeline-item{border-left:3px solid #4CAF50;padding:7px 0 7px 12px}.manage-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.flash-ok{padding:12px;background:#dcfce7;color:#166534;border-radius:8px;margin:14px 0}.flash-err{padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:14px 0}@media(max-width:850px){.case-grid,.data-grid,.manage-grid{grid-template-columns:1fr}}
</style>

<div class="case-head">
    <div><h1><?= htmlspecialchars((string)$case['case_number'], ENT_QUOTES, 'UTF-8') ?></h1><div class="muted">Radicado/SIM: <?= htmlspecialchars((string)$case['external_key'], ENT_QUOTES, 'UTF-8') ?></div></div>
    <a class="btn btn-light" href="/cases">← Volver a casos</a>
</div>

<?php if ($success): ?><div class="flash-ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="case-grid">
    <div>
        <div class="case-card">
            <h2 style="margin-top:0">Información del caso</h2>
            <div class="data-grid">
                <div class="datum"><small>Estado</small><strong><?= htmlspecialchars((string)$case['current_state'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Cola</small><strong><?= htmlspecialchars((string)($case['queue_name'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Agente</small><strong><?= htmlspecialchars((string)($case['assigned_user_name'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Tipo petición</small><strong><?= htmlspecialchars((string)($case['petition_type'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Regional</small><strong><?= htmlspecialchars((string)($case['regional'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Canal origen</small><strong><?= htmlspecialchars((string)($case['origin_channel'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Radicación</small><strong><?= htmlspecialchars((string)($case['radicated_at'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="datum"><small>Última gestión</small><strong><?= htmlspecialchars((string)($case['current_management_type_code'] ?: 'Sin gestión'), ENT_QUOTES, 'UTF-8') ?></strong></div>
            </div>

            <?php if ($source !== []): ?>
                <h3>Datos de origen</h3>
                <div class="data-grid">
                    <?php foreach ($source as $key=>$value): ?>
                        <div class="datum"><small><?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?></small><strong><?= htmlspecialchars(is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="case-card" style="margin-top:18px">
            <h2 style="margin-top:0">Historial de gestión</h2>
            <div class="timeline">
                <?php foreach ($managements as $m): ?>
                    <div class="timeline-item">
                        <strong><?= htmlspecialchars((string)$m['management_type_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if (!empty($m['escalation_category_code'])): ?> · <?= htmlspecialchars((string)$m['escalation_category_code'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        <div class="muted"><?= htmlspecialchars((string)$m['actor_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$m['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if (!empty($m['observation'])): ?><div><?= nl2br(htmlspecialchars((string)$m['observation'], ENT_QUOTES, 'UTF-8')) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($managements === []): ?><div class="muted">El caso todavía no tiene gestiones.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <?php if ($canManage): ?>
            <div class="case-card">
                <h2 style="margin-top:0">Registrar gestión</h2>
                <form method="post" action="/cases/<?= (int)$case['id'] ?>/manage" enctype="multipart/form-data" id="managementForm">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label>Tipo de gestión *</label>
                    <select name="management_type_code" id="managementType" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($managementTypes as $item): ?><option value="<?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>

                    <div id="escalationBlock" style="display:none">
                        <label>Categoría de escalamiento *</label>
                        <select name="escalation_category_code">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($escalations as $item): ?><option value="<?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <div id="petitionChangeBlock" style="display:none">
                        <label>Nuevo tipo de petición *</label>
                        <select name="new_petition_type">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($petitionTypes as $item): ?><option value="<?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <label>Observación</label>
                    <textarea name="observation" rows="5" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px"></textarea>

                    <label>Soporte</label>
                    <input type="file" name="support" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <small class="muted">Máximo 10 MB.</small>

                    <button class="btn btn-primary" type="submit" style="margin-top:14px">Guardar gestión</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="case-card" style="margin-top:18px">
            <h2 style="margin-top:0">Eventos</h2>
            <div class="timeline">
                <?php foreach ($events as $event): ?>
                    <div class="timeline-item">
                        <strong><?= htmlspecialchars((string)$event['event_type'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <div class="muted"><?= htmlspecialchars((string)($event['actor_name'] ?: 'Sistema'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$event['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const type=document.getElementById('managementType');
    const escalation=document.getElementById('escalationBlock');
    const petition=document.getElementById('petitionChangeBlock');
    const refresh=()=>{
        escalation.style.display=type.value==='ESCALATED'?'':'none';
        petition.style.display=type.value==='PETITION_TYPE_CHANGE'?'':'none';
    };
    type?.addEventListener('change',refresh);
    refresh();
})();
</script>
