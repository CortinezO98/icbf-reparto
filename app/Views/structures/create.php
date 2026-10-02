<?php
/** @var string|null $error */
$e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="structure-create-page">
    <div class="page-heading">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="structure-icon"><i class="bi bi-file-earmark-plus" aria-hidden="true"></i></span>
                <span class="text-uppercase small fw-semibold text-secondary">Administración · Estructuras</span>
            </div>
            <h1 class="mb-1">Nueva estructura</h1>
            <p class="text-secondary mb-0">Define una estructura reutilizable para cargas Excel y CSV.</p>
        </div>
        <a href="/admin/structures" class="btn btn-light border">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex gap-2 align-items-start shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div><?= $e($error) ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white p-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="step-badge">1</span>
                        <div>
                            <h2 class="h5 fw-bold mb-1">Datos de la estructura</h2>
                            <p class="small text-secondary mb-0">El código es técnico; el nombre es el que verá el usuario.</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="/admin/structures/create" id="structureCreateForm">
                        <input type="hidden" name="_csrf" value="<?= $e(\App\Auth\Csrf::token()) ?>">

                        <div class="mb-3">
                            <label for="structureName" class="form-label fw-semibold">Nombre de la estructura</label>
                            <input id="structureName"
                                   class="form-control form-control-lg"
                                   name="name"
                                   maxlength="180"
                                   placeholder="Ej. DP Atención por ciclos de vida y nutrición"
                                   required
                                   autocomplete="off">
                            <div class="form-text">
                                Usa el nombre funcional que aparecerá en Administración y en las cargas.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="structureCode" class="form-label fw-semibold">Código técnico</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-code-slash"></i></span>
                                <input id="structureCode"
                                       class="form-control"
                                       name="code"
                                       maxlength="100"
                                       pattern="[A-Za-z0-9_]{2,100}"
                                       placeholder="DP_CICLOS_VIDA_NUTRICION"
                                       required
                                       autocomplete="off"
                                       spellcheck="false">
                            </div>
                            <div class="form-text">
                                Solo letras, números y guion bajo. Se propone automáticamente a partir del nombre.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="structureDescription" class="form-label fw-semibold">Descripción <span class="fw-normal text-secondary">(opcional)</span></label>
                            <textarea id="structureDescription"
                                      class="form-control"
                                      name="description"
                                      maxlength="500"
                                      rows="3"
                                      placeholder="Describe qué archivo representa esta estructura y para qué proceso se utilizará."></textarea>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-brand" type="submit">
                                <i class="bi bi-check2-circle me-1"></i>
                                Crear estructura
                            </button>
                            <a class="btn btn-light border" href="/admin/structures">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-lightbulb text-brand fs-4"></i>
                        <h2 class="h6 fw-bold mb-0">¿Qué sigue?</h2>
                    </div>

                    <div class="structure-step">
                        <span>1</span>
                        <div>
                            <strong>Crear estructura</strong>
                            <p>Define el contenedor técnico.</p>
                        </div>
                    </div>
                    <div class="structure-step">
                        <span>2</span>
                        <div>
                            <strong>Subir Excel</strong>
                            <p>El sistema detecta automáticamente los encabezados.</p>
                        </div>
                    </div>
                    <div class="structure-step">
                        <span>3</span>
                        <div>
                            <strong>Revisar borrador</strong>
                            <p>Selecciona la llave externa y ajusta la configuración.</p>
                        </div>
                    </div>
                    <div class="structure-step">
                        <span>4</span>
                        <div>
                            <strong>Activar versión</strong>
                            <p>Solo la versión activa se utilizará para nuevas cargas.</p>
                        </div>
                    </div>

                    <div class="alert alert-success mt-4 mb-0 small">
                        <i class="bi bi-shield-check me-1"></i>
                        Las versiones anteriores se conservan para trazabilidad.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const name = document.getElementById('structureName');
    const code = document.getElementById('structureCode');
    if (!name || !code) return;

    let codeEdited = false;

    code.addEventListener('input', () => {
        codeEdited = code.value.trim() !== '';
    });

    name.addEventListener('input', () => {
        if (codeEdited) return;

        let value = name.value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/_+/g, '_');

        code.value = value.slice(0, 100);
    });
})();
</script>

<style>
.structure-create-page .structure-icon{
    width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);font-size:1.2rem;
}
.structure-create-page .step-badge{
    width:34px;height:34px;border-radius:50%;display:grid;place-items:center;
    background:var(--brand-soft);color:var(--brand);font-weight:800;
}
.structure-create-page .structure-step{
    display:flex;gap:.8rem;padding:.85rem 0;border-bottom:1px solid #eef0f2;
}
.structure-create-page .structure-step:last-of-type{border-bottom:0;}
.structure-create-page .structure-step > span{
    width:28px;height:28px;flex:0 0 28px;border-radius:50%;
    background:#f1f8f2;color:var(--brand);display:grid;place-items:center;font-weight:700;
}
.structure-create-page .structure-step strong{font-size:.9rem;}
.structure-create-page .structure-step p{margin:.15rem 0 0;color:#6c757d;font-size:.82rem;}
</style>
