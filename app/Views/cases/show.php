<?php
declare(strict_types=1);

/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $managements */
/** @var list<array<string,mixed>> $assignments */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $managementTypes */
/** @var list<array<string,mixed>> $escalations */
/** @var list<array<string,mixed>> $petitionTypes */
/** @var bool $canManage */
/** @var bool $canReassign */
/** @var list<array{id:int,full_name:string,username:string,capacity:int,open_cases:int,free_capacity:int}> $reassignmentCandidates */
/** @var string|null $success */
/** @var string|null $error */

$e = static fn(mixed $value): string => htmlspecialchars(
    (string)$value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$source = json_decode((string)($case['source_normalized_json'] ?? ''), true);
$source = is_array($source) ? $source : [];

$slaStatus = strtoupper((string)($case['sla_status'] ?? ''));
$slaClass = match ($slaStatus) {
    'GREEN' => 'case-badge--success',
    'YELLOW' => 'case-badge--neutral',
    'RED', 'BREACHED' => 'case-badge--danger',
    default => 'case-badge--neutral',
};

$state = (string)($case['current_state'] ?? '');
$stateLabel = match ($state) {
    'PENDING_ASSIGNMENT' => 'Pendiente de asignación',
    'ASSIGNED' => 'Asignado',
    'CLOSED' => 'Cerrado',
    default => $state !== '' ? $state : 'Sin estado',
};

$elapsedMinutes = $case['sla_elapsed_minutes'] ?? null;
$elapsedLabel = $elapsedMinutes === null || $elapsedMinutes === ''
    ? '—'
    : ((float)$elapsedMinutes < 60
        ? number_format((float)$elapsedMinutes, 0, ',', '.') . ' min'
        : number_format((float)$elapsedMinutes / 60, 1, ',', '.') . ' h');

$currentPetitionTypeCode = '';
foreach ($petitionTypes as $petitionType) {
    if ((string)$petitionType['label'] === (string)($case['petition_type'] ?? '')) {
        $currentPetitionTypeCode = (string)$petitionType['code'];
        break;
    }
}

$managementLabels = [];
foreach ($managementTypes as $item) {
    $managementLabels[(string)$item['code']] = (string)$item['label'];
}

$escalationLabels = [];
foreach ($escalations as $item) {
    $escalationLabels[(string)$item['code']] = (string)$item['label'];
}

$eventLabels = [
    'CASE_CREATED' => ['Caso creado', 'created'],
    'CASE_ASSIGNED' => ['Caso asignado', 'assignment'],
    'CASE_REASSIGNED' => ['Caso reasignado', 'reassign'],
    'CASE_RECOVERED' => ['Caso recuperado', 'recovery'],
    'CASE_RELEASED_OFFLINE' => ['Caso liberado por desconexión', 'release'],
    'CASE_RELEASED' => ['Caso liberado', 'release'],
    'CASE_MANAGED' => ['Gestión registrada', 'management'],
    'CASE_CLOSED' => ['Caso cerrado', 'management'],
];

$tracking = [];

foreach ($managements as $management) {
    $managementCode = (string)($management['management_type_code'] ?? '');
    $tags = [];

    if (!empty($management['petition_type_selected'])) {
        $tags[] = (string)$management['petition_type_selected'];
    }

    if (!empty($management['escalation_category_code'])) {
        $code = (string)$management['escalation_category_code'];
        $tags[] = $escalationLabels[$code] ?? $code;
    }

    $tracking[] = [
        'at' => (string)($management['created_at'] ?? ''),
        'kind' => 'management',
        'icon' => 'bi-pencil-square',
        'title' => $managementLabels[$managementCode] ?? ($managementCode !== '' ? $managementCode : 'Gestión'),
        'actor' => (string)($management['actor_name'] ?? 'Sistema'),
        'description' => (string)($management['observation'] ?? ''),
        'tags' => $tags,
    ];
}

foreach ($assignments as $assignment) {
    $assignmentType = strtoupper((string)($assignment['assignment_type'] ?? ''));
    $tracking[] = [
        'at' => (string)($assignment['assigned_at'] ?? ''),
        'kind' => 'assignment',
        'icon' => 'bi-person-check',
        'title' => 'Caso asignado',
        'actor' => !empty($assignment['assigned_by_name'])
            ? 'Por ' . (string)$assignment['assigned_by_name']
            : 'Sistema',
        'description' => 'Asignado a ' . (string)($assignment['user_name'] ?? 'Agente'),
        'tags' => [$assignmentType !== '' ? $assignmentType : 'AUTO'],
    ];

    if (!empty($assignment['ended_at'])) {
        $reason = strtoupper((string)($assignment['end_reason'] ?? ''));
        $reasonLabel = match ($reason) {
            'LOGOUT' => 'Cierre de sesión',
            'STALE_HEARTBEAT' => 'Heartbeat vencido',
            'SHIFT_END' => 'Fin de turno',
            'OFFLINE' => 'Desconexión',
            'REASSIGNED' => 'Reasignación',
            default => $reason !== '' ? $reason : 'Finalización',
        };

        $tracking[] = [
            'at' => (string)$assignment['ended_at'],
            'kind' => in_array($reason, ['REASSIGNED'], true) ? 'reassign' : 'release',
            'icon' => in_array($reason, ['REASSIGNED'], true) ? 'bi-arrow-left-right' : 'bi-person-dash',
            'title' => in_array($reason, ['REASSIGNED'], true)
                ? 'Asignación finalizada por reasignación'
                : 'Asignación finalizada',
            'actor' => 'Sistema',
            'description' => $reasonLabel,
            'tags' => [],
        ];
    }
}

foreach ($events as $event) {
    $eventCode = strtoupper((string)($event['event_type'] ?? ''));
    [$title, $kind] = $eventLabels[$eventCode] ?? [
        ucwords(strtolower(str_replace('_', ' ', $eventCode !== '' ? $eventCode : 'Evento'))),
        'created',
    ];

    $tracking[] = [
        'at' => (string)($event['created_at'] ?? ''),
        'kind' => $kind,
        'icon' => match ($kind) {
            'assignment' => 'bi-person-check',
            'recovery' => 'bi-arrow-counterclockwise',
            'release' => 'bi-person-dash',
            'reassign' => 'bi-arrow-left-right',
            'management' => 'bi-check2-circle',
            default => 'bi-activity',
        },
        'title' => $title,
        'actor' => (string)($event['actor_name'] ?? 'Sistema'),
        'description' => '',
        'tags' => [],
    ];
}

usort(
    $tracking,
    static function (array $a, array $b): int {
        $at = strtotime((string)$a['at']) ?: 0;
        $bt = strtotime((string)$b['at']) ?: 0;

        return $bt <=> $at;
    }
);
?>
<div class="case-detail">
    <nav class="case-breadcrumb" aria-label="Ruta de navegación">
        <a href="/cases"><i class="bi bi-inbox" aria-hidden="true"></i> Casos</a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span><?= $e($case['case_number']) ?></span>
    </nav>

    <header class="case-hero">
        <div class="case-hero-main">
            <div class="case-title-row">
                <div class="case-title-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <h1><?= $e($case['case_number']) ?></h1>
                    <div class="case-hero-sub">
                        <span>Radicado/SIM: <strong><?= $e($case['external_key']) ?></strong></span>
                        <span class="case-badge <?= $slaClass ?>">
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            <?= $e($slaStatus !== '' ? $slaStatus : 'ANS pendiente') ?>
                        </span>
                        <span class="case-badge case-badge--neutral"><?= $e($stateLabel) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="case-actions">
            <?php if ($canViewSla): ?>
                <a class="btn btn-light border btn-sm" href="/sla">
                    <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Tablero ANS
                </a>
            <?php endif; ?>
            <a class="btn btn-light border btn-sm" href="/cases">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a casos
            </a>
        </div>
    </header>

    <?php if ($success): ?>
        <div class="case-flash case-flash--ok" role="status">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span><?= $e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="case-flash case-flash--err" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span><?= $e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="case-layout">
        <main class="case-main">
            <section class="case-card case-info-card" aria-labelledby="case-info-title">
                <div class="case-card__head">
                    <div class="case-card__title">
                        <div class="case-card__icon"><i class="bi bi-info-circle" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="case-info-title">Información del caso</h2>
                            <p>Datos generales del caso y su estado actual.</p>
                        </div>
                    </div>
                    <span class="case-badge case-badge--info">
                        <i class="bi bi-record-circle" aria-hidden="true"></i><?= $e($stateLabel) ?>
                    </span>
                </div>

                <div class="case-card__body">
                    <div class="case-kpi-grid">
                        <div class="case-kpi">
                            <div class="case-kpi__label"><i class="bi bi-clock" aria-hidden="true"></i>Tiempo ANS consumido</div>
                            <div class="case-kpi__value"><?= $e($elapsedLabel) ?></div>
                            <?php if (in_array($slaStatus, ['RED', 'BREACHED'], true)): ?>
                                <div class="case-kpi__meta">Fuera del tiempo objetivo</div>
                            <?php endif; ?>
                        </div>
                        <div class="case-kpi">
                            <div class="case-kpi__label"><i class="bi bi-calendar-event" aria-hidden="true"></i>Vencimiento ANS</div>
                            <div class="case-kpi__value"><?= $e($case['sla_due_at'] ?: '—') ?></div>
                        </div>
                        <div class="case-kpi">
                            <div class="case-kpi__label"><i class="bi bi-check2-circle" aria-hidden="true"></i>Primera gestión</div>
                            <div class="case-kpi__value"><?= $e($case['first_management_at'] ?: 'Pendiente') ?></div>
                            <?php if (empty($case['first_management_at'])): ?><div class="case-kpi__meta">Aún no se ha registrado una gestión</div><?php endif; ?>
                        </div>
                    </div>

                    <div class="case-data-grid">
                        <div class="case-datum"><div class="case-datum__label">Cola</div><div class="case-datum__value"><?= $e($case['queue_name'] ?: '—') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Agente asignado</div><div class="case-datum__value"><?= $e($case['assigned_user_name'] ?: 'Sin asignar') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Tipo de petición</div><div class="case-datum__value"><?= $e($case['petition_type'] ?: '—') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Regional</div><div class="case-datum__value"><?= $e($case['regional'] ?: '—') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Segmento</div><div class="case-datum__value"><?= $e($case['segment'] ?: '—') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Canal de origen</div><div class="case-datum__value"><?= $e($case['origin_channel'] ?: '—') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Radicación</div><div class="case-datum__value"><?= $e($case['radicated_at'] ?: '—') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Estado de petición</div><div class="case-datum__value"><?= $e($source['estado_peticion'] ?? '—') ?></div></div>
                        <div class="case-datum"><div class="case-datum__label">Última gestión</div><div class="case-datum__value"><?= $e($managementLabels[(string)($case['current_management_type_code'] ?? '')] ?? ((string)($case['current_management_type_code'] ?: 'Sin gestión'))) ?></div></div>
                    </div>

                    <?php if ($source !== []): ?>
                        <details class="case-source" open>
                            <summary>
                                <span><strong>Datos de origen</strong><small>Información proveniente del sistema de radicación</small></span>
                            </summary>
                            <div class="case-source-grid">
                                <?php foreach ($source as $key => $value): ?>
                                    <div class="case-source-row">
                                        <div class="case-source-key"><?= $e(ucwords(str_replace('_', ' ', (string)$key))) ?></div>
                                        <div class="case-source-value"><?= $e(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            </section>

            <section class="case-card management-history-card" aria-labelledby="management-history-title">
                <div class="case-card__head">
                    <div class="case-card__title">
                        <div class="case-card__icon case-card__icon--blue"><i class="bi bi-chat-square-text" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="management-history-title">Historial de gestión</h2>
                            <p>Gestiones registradas por los agentes en este caso.</p>
                        </div>
                    </div>
                    <?php if ($managements !== []): ?><span class="case-badge case-badge--neutral"><?= count($managements) ?> <?= count($managements) === 1 ? 'gestión' : 'gestiones' ?></span><?php endif; ?>
                </div>
                <div class="case-card__body">
                    <?php if ($managements !== []): ?>
                        <div class="management-history">
                            <?php foreach ($managements as $management): ?>
                                <?php
                                $mCode = (string)($management['management_type_code'] ?? '');
                                $mTitle = $managementLabels[$mCode] ?? ($mCode !== '' ? $mCode : 'Gestión');
                                ?>
                                <article class="management-entry">
                                    <div class="management-entry__icon"><i class="bi bi-check2" aria-hidden="true"></i></div>
                                    <div class="management-entry__body">
                                        <div class="management-entry__top">
                                            <strong><?= $e($mTitle) ?></strong>
                                            <time><?= $e($management['created_at'] ?? '') ?></time>
                                        </div>
                                        <div class="management-entry__actor"><?= $e($management['actor_name'] ?? 'Sistema') ?></div>
                                        <?php if (!empty($management['observation'])): ?><p><?= nl2br($e($management['observation'])) ?></p><?php endif; ?>
                                        <?php if (!empty($management['petition_type_selected'])): ?><span class="case-track-tag"><?= $e($management['petition_type_selected']) ?></span><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="case-empty">
                            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                            <strong>El caso aún no tiene gestiones registradas</strong>
                            <span>Cuando registres una gestión, aparecerá aquí el historial completo.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="case-sidebar">
            <?php if ($canManage): ?>
                <section class="case-card" aria-labelledby="management-title">
                    <div class="case-card__head">
                        <div class="case-card__title">
                            <div class="case-card__icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></div>
                            <div>
                                <h2 id="management-title">Registrar gestión</h2>
                                <p>Tipifica y registra la actuación realizada en este caso.</p>
                            </div>
                        </div>
                    </div>
                    <div class="case-card__body">
                        <form class="management-form" method="post" action="/cases/<?= (int)$case['id'] ?>/manage" enctype="multipart/form-data" id="managementForm">
                            <input type="hidden" name="_csrf" value="<?= $e(\App\Auth\Csrf::token()) ?>">

                            <div class="typification-intro">
                                <i class="bi bi-ui-checks-grid" aria-hidden="true"></i>
                                <div><strong>Tipificación del caso</strong><span>Selecciona la clasificación que corresponde a la gestión realizada.</span></div>
                            </div>

                            <label class="form-label" for="petitionType">Tipo de petición <span class="text-danger">*</span></label>
                            <select class="form-select" name="petition_type_selected" id="petitionType" required>
                                <option value="">Seleccionar tipo de petición...</option>
                                <?php foreach ($petitionTypes as $item): ?>
                                    <?php $code=(string)$item['code']; $label=(string)$item['label']; $isPresence=$code==='PRESENCIA_CONVIVENCIA_VINCULOS'; ?>
                                    <option value="<?= $e($code) ?>" <?= $currentPetitionTypeCode === $code ? 'selected' : '' ?>><?= $e($label) ?><?= $isPresence ? ' · Uso exclusivo de Presencia' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-help">La selección queda registrada en el caso y en su trazabilidad.</div>

                            <label class="form-label" for="managementType">Tipo de gestión <span class="text-danger">*</span></label>
                            <select class="form-select" name="management_type_code" id="managementType" required>
                                <option value="">Seleccionar tipo de gestión...</option>
                                <?php foreach ($managementTypes as $item): ?>
                                    <option value="<?= $e($item['code']) ?>"><?= $e($item['label']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <div id="escalationBlock" class="conditional-field" hidden>
                                <label class="form-label mt-0" for="escalationType">Subcategoría de escalamiento <span class="text-danger">*</span></label>
                                <select class="form-select" name="escalation_category_code" id="escalationType">
                                    <option value="">Seleccionar subcategoría...</option>
                                    <?php foreach ($escalations as $item): ?>
                                        <option value="<?= $e($item['code']) ?>"><?= $e($item['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-help">Obligatoria cuando el tipo de gestión es Escalado.</div>
                            </div>

                            <div id="petitionChangeBlock" class="conditional-field" hidden>
                                <div class="change-note"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span>El tipo de petición seleccionado arriba se registrará como la nueva tipificación.</span></div>
                            </div>

                            <div class="management-form-row">
                                <div>
                                    <label class="form-label" for="observation">Observación <span class="text-muted fw-normal">(opcional)</span></label>
                                    <div class="form-textarea-wrap">
                                        <textarea class="form-control" name="observation" id="observation" rows="5" maxlength="2000" placeholder="Describe brevemente la gestión realizada."></textarea>
                                        <span class="char-counter" id="observationCounter">0 / 2000</span>
                                    </div>
                                </div>
                            </div>

                            <label class="form-label" for="support">Soporte <span class="text-muted fw-normal">(opcional)</span></label>
                            <input class="form-control" type="file" name="support" id="support" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            <div class="field-help">PDF, Word, Excel o imagen · máximo 10 MB.</div>

                            <button class="btn btn-primary w-100 management-submit" type="submit" id="managementSubmit">
                                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Guardar tipificación y gestión
                            </button>
                        </form>
                    </div>
                </section>
            <?php endif; ?>

            <section class="case-card tracking-card" aria-labelledby="tracking-title">
                <div class="case-card__head">
                    <div class="case-card__title">
                        <div class="case-card__icon case-card__icon--blue"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="tracking-title">Historial del caso</h2>
                            <p>Seguimiento cronológico de asignaciones, gestiones y eventos del caso.</p>
                        </div>
                    </div>
                    <?php if ($tracking !== []): ?><span class="case-badge case-badge--neutral"><?= count($tracking) ?> eventos</span><?php endif; ?>
                </div>
                <div class="case-card__body">
                    <?php if ($tracking !== []): ?>
                        <div class="case-tracking" aria-label="Línea de tiempo del caso">
                            <?php foreach ($tracking as $item): ?>
                                <article class="case-track-item case-track-item--<?= $e($item['kind']) ?>">
                                    <div class="case-track-rail"><span class="case-track-dot"><i class="bi <?= $e($item['icon']) ?>" aria-hidden="true"></i></span></div>
                                    <div class="case-track-content">
                                        <div class="case-track-top">
                                            <div class="case-track-title"><?= $e($item['title']) ?></div>
                                            <time class="case-track-date" datetime="<?= $e($item['at']) ?>"><?= $e($item['at']) ?></time>
                                        </div>
                                        <div class="case-track-meta"><?= $e($item['actor']) ?></div>
                                        <?php if ($item['description'] !== ''): ?><div class="case-track-description"><?= nl2br($e($item['description'])) ?></div><?php endif; ?>
                                        <?php if ($item['tags'] !== []): ?><div class="case-track-tags"><?php foreach ($item['tags'] as $tag): ?><span class="case-track-tag"><?= $e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="case-empty"><i class="bi bi-clock-history" aria-hidden="true"></i><strong>Aún no hay actividad registrada</strong><span>Cuando el caso tenga una asignación, gestión o evento, aparecerá aquí.</span></div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($canReassign && (int)($case['assigned_user_id'] ?? 0) > 0 && $state === 'ASSIGNED' && $case['closed_at'] === null): ?>
                <section class="case-card" aria-labelledby="reassign-title">
                    <div class="case-card__head">
                        <div class="case-card__title">
                            <div class="case-card__icon case-card__icon--amber"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></div>
                            <div><h2 id="reassign-title">Reasignar caso</h2><p>Acción administrativa explícita con trazabilidad.</p></div>
                        </div>
                    </div>
                    <div class="case-card__body">
                        <div class="reassign-box">
                            <div class="reassign-title"><i class="bi bi-shield-check" aria-hidden="true"></i>Control de reasignación</div>
                            <div class="reassign-help">Solo se muestran agentes habilitados y elegibles con capacidad disponible.</div>
                            <?php if ($reassignmentCandidates !== []): ?>
                                <form method="post" action="/cases/<?= (int)$case['id'] ?>/reassign" id="reassignmentForm">
                                    <input type="hidden" name="_csrf" value="<?= $e(\App\Auth\Csrf::token()) ?>">
                                    <label class="form-label" for="newUserId">Agente destino <span class="text-danger">*</span></label>
                                    <select class="form-select" name="new_user_id" id="newUserId" required>
                                        <option value="">Seleccionar agente...</option>
                                        <?php foreach ($reassignmentCandidates as $candidate): ?>
                                            <option value="<?= (int)$candidate['id'] ?>"><?= $e($candidate['full_name']) ?> (<?= $e($candidate['username']) ?>) · <?= (int)$candidate['free_capacity'] ?> cupos libres</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="form-label" for="reassignReason">Motivo de reasignación <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="reason" id="reassignReason" rows="4" maxlength="500" required placeholder="Indica por qué se realiza la reasignación."></textarea>
                                    <button class="btn btn-outline-success w-100 mt-3" type="submit" id="reassignSubmit"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Confirmar reasignación</button>
                                </form>
                            <?php else: ?>
                                <div class="case-alert case-alert--warning"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span>No hay agentes elegibles disponibles para recibir este caso en este momento. El caso permanece con el agente actual.</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>
<script>
(() => {
    const type = document.getElementById('managementType');
    const escalation = document.getElementById('escalationBlock');
    const escalationSelect = document.getElementById('escalationType');
    const petitionChange = document.getElementById('petitionChangeBlock');
    const observation = document.getElementById('observation');
    const observationCounter = document.getElementById('observationCounter');
    const managementForm = document.getElementById('managementForm');
    const managementSubmit = document.getElementById('managementSubmit');
    const reassignmentForm = document.getElementById('reassignmentForm');
    const reassignSubmit = document.getElementById('reassignSubmit');

    const refresh = () => {
        const isEscalated = type?.value === 'ESCALATED';
        const isPetitionChange = type?.value === 'PETITION_TYPE_CHANGE';

        if (escalation) {
            escalation.hidden = !isEscalated;
        }

        if (escalationSelect) {
            escalationSelect.required = isEscalated;
            if (!isEscalated) {
                escalationSelect.value = '';
            }
        }

        if (petitionChange) {
            petitionChange.hidden = !isPetitionChange;
        }
    };

    const updateCounter = () => {
        if (observation && observationCounter) {
            observationCounter.textContent = `${observation.value.length} / 2000`;
        }
    };

    observation?.addEventListener('input', updateCounter);
    type?.addEventListener('change', refresh);

    managementForm?.addEventListener('submit', () => {
        if (managementSubmit) {
            managementSubmit.disabled = true;
            managementSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando...';
        }
    });

    reassignmentForm?.addEventListener('submit', (event) => {
        if (!window.confirm('¿Confirmas la reasignación de este caso al agente seleccionado?')) {
            event.preventDefault();
            return;
        }

        if (reassignSubmit) {
            reassignSubmit.disabled = true;
            reassignSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Reasignando...';
        }
    });

    refresh();
    updateCounter();
})();
</script>
