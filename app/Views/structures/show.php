<?php
/** @var array<string,mixed> $structure */
/** @var list<array<string,mixed>> $versions */
/** @var array<int,list<array<string,mixed>>> $versionFields */
/** @var string|null $error */
/** @var string|null $success */

$e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$statusLabel = static fn(string $status): string => match ($status) {
    'ACTIVE' => 'Activa',
    'INACTIVE' => 'Inactiva',
    default => 'Borrador',
};
?>

<div class="structure-page">
    <div class="page-heading">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="structure-icon">
                    <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                </span>
                <span class="text-uppercase small fw-semibold text-secondary">Administración · Estructura</span>
            </div>
            <h1 class="mb-1"><?= $e($structure['name']) ?></h1>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge rounded-pill text-bg-light border">
                    Código: <?= $e($structure['code']) ?>
                </span>
                <span class="badge rounded-pill text-bg-light border">
                    <?= count($versions) ?> versión<?= count($versions) === 1 ? '' : 'es' ?>
                </span>
            </div>
        </div>
        <a href="/admin/structures" class="btn btn-light border">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
            Volver a estructuras
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
            <div><?= $e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-start gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
            <div><?= $e($success) ?></div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm structure-hero mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Constructor de versiones</span>
                        <span class="small text-secondary">Seguro y versionado</span>
                    </div>
                    <h2 class="h4 fw-bold mb-2">Crea una versión directamente desde tu Excel</h2>
                    <p class="text-secondary mb-3">
                        El sistema leerá los encabezados de la primera hoja, los normalizará y creará un borrador
                        para que puedas revisar la llave externa antes de activarlo.
                    </p>
                    <div class="d-flex flex-wrap gap-2 small text-secondary">
                        <span><i class="bi bi-shield-check text-success me-1"></i>Máximo 20 MB</span>
                        <span><i class="bi bi-columns-gap text-success me-1"></i>Hasta 128 columnas</span>
                        <span><i class="bi bi-table text-success me-1"></i>Hasta 10.000 filas</span>
                        <span><i class="bi bi-file-earmark-excel text-success me-1"></i>XLSX / CSV</span>
                    </div>
                </div>
                <div class="col-lg-5">
                    <form method="post"
                          action="/admin/structures/<?= (int)$structure['id'] ?>/versions/create-from-excel"
                          enctype="multipart/form-data"
                          class="upload-version-box">
                        <input type="hidden" name="_csrf"
                               value="<?= $e(AppAuthCsrf::token()) ?>">

                        <label for="structure_file" class="form-label fw-semibold">
                            Archivo de estructura
                        </label>
                        <input id="structure_file"
                               class="form-control"
                               type="file"
                               name="structure_file"
                               accept=".xlsx,.csv"
                               required>
                        <div class="form-text mb-3">
                            Primera hoja · encabezados en la fila 1 · datos desde la fila 2.
                        </div>

                        <button class="btn btn-brand w-100" type="submit">
                            <i class="bi bi-magic me-1" aria-hidden="true"></i>
                            Crear borrador desde Excel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Versiones de la estructura</h2>
            <p class="text-secondary small mb-0">
                Las versiones activas se utilizan para validar futuras cargas. Las versiones históricas se conservan.
            </p>
        </div>
    </div>

    <?php if ($versions === []): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body empty py-5">
                <i class="bi bi-file-earmark-plus d-block mb-2"></i>
                <h3 class="h6 fw-bold">Aún no hay versiones</h3>
                <p class="text-secondary mb-0">Carga un Excel arriba o utiliza el modo manual.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($versions as $v): ?>
        <?php
        $vid = (int)$v['id'];
        $fields = $versionFields[$vid] ?? [];
        $hasKey = false;
        foreach ($fields as $field) {
            if ((int)($field['is_external_key'] ?? 0) === 1) {
                $hasKey = true;
                break;
            }
        }
        ?>
        <section class="card border-0 shadow-sm version-card mb-4">
            <div class="card-header bg-white p-3 p-lg-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="version-number">v<?= (int)$v['version_number'] ?></span>
                            <span class="badge rounded-pill <?= $v['status'] === 'ACTIVE' ? 'bg-success-subtle text-success border border-success-subtle' : ($v['status'] === 'INACTIVE' ? 'bg-secondary-subtle text-secondary border' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle') ?>">
                                <?= $e($statusLabel((string)$v['status'])) ?>
                            </span>
                        </div>
                        <div class="small text-secondary">
                            <span class="me-3"><i class="bi bi-table me-1"></i>Hoja: <?= $e($v['target_sheet_value'] ?: 'Primera hoja') ?></span>
                            <span class="me-3"><i class="bi bi-list-ol me-1"></i>Encabezado: fila <?= (int)$v['header_row'] ?></span>
                            <span><i class="bi bi-layout-text-sidebar me-1"></i>Datos: fila <?= (int)$v['data_start_row'] ?></span>
                        </div>
                    </div>

                    <?php if ($v['status'] !== 'ACTIVE'): ?>
                        <form method="post"
                              action="/admin/structures/<?= (int)$structure['id'] ?>/versions/<?= $vid ?>/activate"
                              class="m-0">
                            <input type="hidden" name="_csrf" value="<?= $e(AppAuthCsrf::token()) ?>">
                            <button class="btn btn-brand"
                                    type="submit"
                                    <?= !$hasKey ? 'disabled title="Selecciona una llave externa antes de activar."' : '' ?>>
                                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                                Activar versión
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body p-3 p-lg-4">
                <?php if ($v['status'] === 'DRAFT' && !$hasKey): ?>
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
                        <i class="bi bi-key-fill mt-1" aria-hidden="true"></i>
                        <div>
                            <strong>Falta definir la llave externa.</strong>
                            <div class="small mt-1">
                                Es el campo que identifica de forma única cada petición. Selecciónalo antes de activar la versión.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">Encabezados detectados</h3>
                        <p class="small text-secondary mb-0">
                            <?= count($fields) ?> campo<?= count($fields) === 1 ? '' : 's' ?> configurado<?= count($fields) === 1 ? '' : 's' ?>.
                        </p>
                    </div>
                    <?php if ($v['status'] === 'DRAFT'): ?>
                        <span class="small text-secondary">
                            <i class="bi bi-pencil-square me-1"></i>Configurable antes de activar
                        </span>
                    <?php endif; ?>
                </div>

                <form method="post"
                      action="/admin/structures/<?= (int)$structure['id'] ?>/versions/<?= $vid ?>/fields/update">
                    <input type="hidden" name="_csrf" value="<?= $e(\App\Auth\Csrf::token()) ?>">

                    <div class="table-responsive">
                        <table class="table align-middle structure-fields-table">
                            <thead>
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th style="min-width:180px">Encabezado</th>
                                    <th style="min-width:150px">Código técnico</th>
                                    <th style="min-width:145px">Tipo</th>
                                    <th style="min-width:180px">Nombre visible</th>
                                    <th>Reglas</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($fields as $index => $f): ?>
                                <?php $fid = (int)$f['id']; ?>
                                <tr>
                                    <td class="text-secondary"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= $e($f['excel_header']) ?></div>
                                        <?php if (!empty($f['header_aliases_json'])): ?>
                                            <div class="small text-secondary mt-1">
                                                <i class="bi bi-arrow-return-right me-1"></i>Con alias configurado
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= $e($f['field_code']) ?></code></td>
                                    <td>
                                        <?php if ($v['status'] === 'DRAFT'): ?>
                                            <select class="form-select form-select-sm"
                                                    name="fields[<?= $fid ?>][data_type]">
                                                <?php foreach (['STRING','INTEGER','DECIMAL','DATE','DATETIME','BOOLEAN','CATALOG'] as $type): ?>
                                                    <option value="<?= $type ?>" <?= (string)$f['data_type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <span class="badge text-bg-light border"><?= $e($f['data_type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($v['status'] === 'DRAFT'): ?>
                                            <input class="form-control form-control-sm mb-2"
                                                   name="fields[<?= $fid ?>][display_name]"
                                                   value="<?= $e($f['display_name']) ?>"
                                                   maxlength="180"
                                                   required>
                                            <input class="form-control form-control-sm"
                                                   name="fields[<?= $fid ?>][header_aliases]"
                                                   value="<?= $e(implode('; ', is_array(json_decode((string)($f['header_aliases_json'] ?? ''), true)) ? json_decode((string)$f['header_aliases_json'], true) : [])) ?>"
                                                   placeholder="Alias de encabezado (opcional)">
                                        <?php else: ?>
                                            <div class="fw-semibold"><?= $e($f['display_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($v['status'] === 'DRAFT'): ?>
                                            <div class="d-flex flex-column gap-2 small">
                                                <label class="form-check mb-0">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           name="fields[<?= $fid ?>][is_required]"
                                                           value="1"
                                                           <?= (int)$f['is_required'] === 1 ? 'checked' : '' ?>>
                                                    <span class="form-check-label">Obligatorio</span>
                                                </label>
                                                <label class="form-check mb-0">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           name="fields[<?= $fid ?>][is_reportable]"
                                                           value="1"
                                                           <?= (int)$f['is_reportable'] === 1 ? 'checked' : '' ?>>
                                                    <span class="form-check-label">Reportable</span>
                                                </label>
                                                <input class="form-control form-control-sm"
                                                       type="number"
                                                       min="0"
                                                       name="fields[<?= $fid ?>][max_length]"
                                                       value="<?= (int)($f['max_length'] ?? 0) ?>"
                                                       placeholder="Longitud máx.">
                                                <input class="form-control form-control-sm"
                                                       name="fields[<?= $fid ?>][date_format]"
                                                       value="<?= $e($f['date_format'] ?? '') ?>"
                                                       placeholder="Formato fecha">
                                                <input class="form-control form-control-sm"
                                                       name="fields[<?= $fid ?>][validation_regex]"
                                                       value="<?= $e($f['validation_regex'] ?? '') ?>"
                                                       placeholder="Regex opcional">
                                                <input type="hidden"
                                                       name="fields[<?= $fid ?>][sort_order]"
                                                       value="<?= $index + 1 ?>">
                                            </div>
                                        <?php else: ?>
                                            <span class="text-secondary small">
                                                <?= (int)$f['is_required'] === 1 ? 'Obligatorio' : 'Opcional' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($v['status'] === 'DRAFT' && $fields !== []): ?>
                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-outline-brand" type="submit">
                                <i class="bi bi-save2 me-1" aria-hidden="true"></i>
                                Guardar configuración de campos
                            </button>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($v['status'] === 'DRAFT' && $fields !== []): ?>
                    <div class="mt-4 p-3 rounded-3 border bg-light-subtle">
                        <form method="post"
                              action="/admin/structures/<?= (int)$structure['id'] ?>/versions/<?= $vid ?>/external-key"
                              class="row g-3 align-items-end">
                            <input type="hidden" name="_csrf" value="<?= $e(AppAuthCsrf::token()) ?>">
                            <div class="col-lg-8">
                                <label for="external_key_<?= $vid ?>" class="form-label fw-semibold mb-1">
                                    Llave externa de la versión
                                </label>
                                <div class="form-text mt-0 mb-2">
                                    Selecciona el encabezado que identifica de manera única cada registro.
                                </div>
                                <select id="external_key_<?= $vid ?>" name="external_key_field_code" class="form-select" required>
                                    <option value="">Seleccionar campo...</option>
                                    <?php foreach ($fields as $f): ?>
                                        <option value="<?= $e($f['field_code']) ?>"
                                            <?= (int)$f['is_external_key'] === 1 ? 'selected' : '' ?>>
                                            <?= $e($f['display_name']) ?> · <?= $e($f['field_code']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <button class="btn btn-outline-brand w-100" type="submit">
                                    <i class="bi bi-key me-1" aria-hidden="true"></i>
                                    Guardar llave externa
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($v['status'] === 'DRAFT'): ?>
                    <details class="manual-field-panel mt-4">
                        <summary>
                            <span>
                                <i class="bi bi-sliders me-2"></i>
                                Agregar campo manualmente
                            </span>
                            <span class="small text-secondary">Opcional</span>
                        </summary>

                        <form method="post"
                              action="/admin/structures/<?= (int)$structure['id'] ?>/versions/<?= $vid ?>/fields/create"
                              class="pt-4">
                            <input type="hidden" name="_csrf" value="<?= $e(AppAuthCsrf::token()) ?>">

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Código técnico</label>
                                    <input class="form-control" name="field_code" required placeholder="numero_peticion">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nombre visible</label>
                                    <input class="form-control" name="display_name" required placeholder="Número de petición">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Encabezado Excel</label>
                                    <input class="form-control" name="excel_header" required placeholder="Número de Petición">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tipo de dato</label>
                                    <select class="form-select" name="data_type">
                                        <option>STRING</option>
                                        <option>INTEGER</option>
                                        <option>DECIMAL</option>
                                        <option>DATE</option>
                                        <option>DATETIME</option>
                                        <option>BOOLEAN</option>
                                        <option>CATALOG</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Longitud máxima</label>
                                    <input class="form-control" type="number" min="0" name="max_length">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Formato de fecha</label>
                                    <input class="form-control" name="date_format" placeholder="d/m/Y">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Orden</label>
                                    <input class="form-control" type="number" name="sort_order" value="<?= count($fields) + 1 ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alias de encabezado</label>
                                    <input class="form-control" name="header_aliases" placeholder="Número Peticion; Numero Peticion">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Regla de validación (opcional)</label>
                                    <input class="form-control" name="validation_regex" placeholder="Ejemplo: ^[0-9]+$">
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-3 mt-3">
                                <label class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_required" value="1">
                                    <span class="form-check-label">Obligatorio</span>
                                </label>
                                <label class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_reportable" value="1" checked>
                                    <span class="form-check-label">Reportable</span>
                                </label>
                            </div>

                            <button class="btn btn-light border mt-3" type="submit">
                                <i class="bi bi-plus-circle me-1"></i>
                                Agregar campo
                            </button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <details>
                <summary class="fw-semibold text-dark">
                    <i class="bi bi-tools text-brand me-2"></i>
                    Crear una versión manualmente
                </summary>

                <form method="post"
                      action="/admin/structures/<?= (int)$structure['id'] ?>/versions/create"
                      class="row g-3 mt-2">
                    <input type="hidden" name="_csrf" value="<?= $e(AppAuthCsrf::token()) ?>">

                    <div class="col-md-4">
                        <label class="form-label">Modo de hoja</label>
                        <select class="form-select" name="target_sheet_mode">
                            <option value="FIRST_MATCH">Primera hoja disponible</option>
                            <option value="EXACT">Nombre exacto</option>
                            <option value="REGEX">Patrón Regex</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombre o patrón de hoja</label>
                        <input class="form-control" name="target_sheet_value" placeholder="Asignacion Agente">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fila encabezado</label>
                        <input class="form-control" type="number" min="1" name="header_row" value="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Primera fila de datos</label>
                        <input class="form-control" type="number" min="2" name="data_start_row" value="2">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Código de llave externa</label>
                        <input class="form-control" name="external_key_field_code" placeholder="numero_peticion" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notas</label>
                        <input class="form-control" name="notes" placeholder="Descripción de la versión">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-4">
                        <label class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="allow_xlsx" value="1" checked>
                            <span class="form-check-label">Permitir XLSX</span>
                        </label>
                        <label class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="allow_csv" value="1" checked>
                            <span class="form-check-label">Permitir CSV</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-light border" type="submit">
                            <i class="bi bi-plus-circle me-1"></i>
                            Crear borrador manual
                        </button>
                    </div>
                </form>
            </details>
        </div>
    </div>
</div>

<style>
.structure-page .structure-icon{
    width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);font-size:1.2rem;
}
.structure-page .structure-hero{
    background:linear-gradient(135deg,#fff 0%,#f5fbf6 100%);
    border:1px solid rgba(76,175,80,.14)!important;
}
.structure-page .upload-version-box{
    padding:1.15rem;background:#fff;border:1px solid var(--border);border-radius:14px;
}
.structure-page .version-card{overflow:hidden;}
.structure-page .version-number{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:46px;height:30px;padding:0 .7rem;border-radius:999px;
    background:var(--brand-soft);color:var(--brand);font-weight:800;
}
.structure-page .structure-fields-table thead th{
    background:#f8f9fa;border-bottom:1px solid #dee2e6;
}
.structure-page .structure-fields-table code{
    color:#495057;background:#f1f3f5;padding:.2rem .4rem;border-radius:6px;
}
.structure-page .manual-field-panel{
    border:1px solid var(--border);border-radius:12px;background:#fff;
}
.structure-page .manual-field-panel summary{
    cursor:pointer;list-style:none;padding:1rem 1.1rem;
    display:flex;justify-content:space-between;align-items:center;
}
.structure-page .manual-field-panel summary::-webkit-details-marker{display:none;}
.structure-page .empty i{font-size:2rem;color:var(--brand);opacity:.65;}
</style>
