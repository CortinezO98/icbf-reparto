<?php
/** @var string|null $error */
/** @var array<string,mixed>|null $result */
?>
<style>
.ui-wrap{max-width:900px;margin:0 auto}.ui-head{display:flex;justify-content:space-between;gap:15px;align-items:flex-start}.ui-head h1{margin:0}.ui-card{background:#fff;border:1px solid #dfe3e8;border-radius:12px;box-shadow:0 4px 13px rgba(15,23,42,.06);margin-top:20px;overflow:hidden}.ui-card-head{padding:10px 16px;background:#fafbfc;border-bottom:1px solid #dfe3e8;font-weight:750}.ui-body{padding:20px}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;text-align:center}.step-icon{width:64px;height:64px;border-radius:50%;background:#e8f1ff;color:#0d6efd;display:grid;place-items:center;margin:0 auto 8px;font-size:1.7rem}.ui-info{background:#dff7ff;color:#0f5f70;padding:14px;border-radius:8px}.ui-warning{background:#fff3cd;color:#7c5a00;padding:14px;border-radius:8px}.ui-green{background:#198f4c;border:1px solid #198f4c;color:#fff;border-radius:7px;padding:10px 14px;font-weight:700;text-decoration:none;display:inline-block}.ui-blue{width:100%;background:#0d6efd;border:1px solid #0d6efd;color:#fff;border-radius:7px;padding:12px;font-weight:700}.ui-outline{background:#fff;border:1px solid #94a3b8;color:#475569;border-radius:7px;padding:9px 12px;text-decoration:none}.result-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.result-box{padding:14px;border-radius:9px;background:#f8fafc}.pass-table{width:100%;font-size:.88rem}.errors{max-height:220px;overflow:auto;background:#fff7f7;padding:12px;border-radius:8px}@media(max-width:700px){.steps,.result-grid{grid-template-columns:1fr 1fr}}
</style>

<div class="ui-wrap">
    <div class="ui-head">
        <div><h1><i class="bi bi-upload text-primary me-2"></i>Importar Usuarios</h1><div class="muted">Importa múltiples usuarios desde un archivo Excel o CSV.</div></div>
        <a class="ui-outline" href="/admin/users"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    </div>

    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        En la columna <strong>Colas</strong> puedes usar códigos concretos separados por coma
        o escribir <code>TODAS</code> para asociar el agente a todas las colas activas.
    </div>

    <div class="ui-card">
        <div class="ui-card-head"><i class="bi bi-list-check me-2"></i>Pasos para Importar</div>
        <div class="ui-body">
            <div class="steps">
                <div><div class="step-icon"><i class="bi bi-download"></i></div><strong>1. Descargar Plantilla</strong><div class="muted">Usa el formato correcto</div></div>
                <div><div class="step-icon"><i class="bi bi-pencil-square"></i></div><strong>2. Llenar Datos</strong><div class="muted">Completa la información</div></div>
                <div><div class="step-icon"><i class="bi bi-upload"></i></div><strong>3. Subir Archivo</strong><div class="muted">Valida los datos</div></div>
                <div><div class="step-icon"><i class="bi bi-check-lg"></i></div><strong>4. Revisar Resultado</strong><div class="muted">Confirma el proceso</div></div>
            </div>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-body">
            <h2 style="margin-top:0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Plantilla</h2>
            <div class="ui-info">
                <strong>Formato requerido</strong><br>
                Columnas obligatorias: <strong>Usuario, Correo, Nombre Completo, Roles</strong>.<br>
                Para AGENTE: <strong>Colas</strong> es obligatoria. Roles y colas múltiples se separan por coma.
            </div>
            <p><a class="ui-green" href="/admin/users/template"><i class="bi bi-download me-1"></i>Descargar Plantilla</a></p>

            <h2><i class="bi bi-upload me-2"></i>Subir Archivo</h2>
            <form method="post" action="/admin/users/import" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <label>Archivo Excel/CSV</label>
                <input type="file" name="users_file" accept=".xlsx,.csv" required>
                <small class="muted">Formatos aceptados: .xlsx, .csv · máximo 5 MB · máximo 5.000 filas.</small>

                <label style="display:flex;gap:8px;align-items:center;margin-top:14px">
                    <input type="checkbox" name="skip_duplicates" value="1" checked style="width:auto">
                    Omitir usuarios duplicados
                </label>

                <button class="ui-blue" type="submit" style="margin-top:16px"><i class="bi bi-cloud-arrow-up me-1"></i>Iniciar Importación</button>
            </form>

            <div class="ui-warning" style="margin-top:18px"><strong><i class="bi bi-exclamation-triangle me-1"></i>Validación previa</strong><br>El sistema valida correo, roles, colas, duplicados y política de contraseña antes de crear cada usuario. Las skills se asignan automáticamente según las colas.</div>
        </div>
    </div>

    <?php if (is_array($result)): ?>
        <div class="ui-card">
            <div class="ui-card-head">Resultado de importación</div>
            <div class="ui-body">
                <div class="result-grid">
                    <div class="result-box"><span class="muted">Creados</span><strong style="font-size:1.7rem;display:block"><?= (int)$result['created'] ?></strong></div>
                    <div class="result-box"><span class="muted">Omitidos</span><strong style="font-size:1.7rem;display:block"><?= (int)$result['skipped'] ?></strong></div>
                    <div class="result-box"><span class="muted">Inválidos</span><strong style="font-size:1.7rem;display:block"><?= (int)$result['invalid'] ?></strong></div>
                </div>

                <?php if (!empty($result['generated_passwords'])): ?>
                    <h3>Contraseñas temporales generadas</h3>
                    <div class="ui-warning">Guarda esta información de manera segura. No se vuelve a mostrar desde el sistema.</div>
                    <table class="pass-table"><thead><tr><th>Usuario</th><th>Contraseña temporal</th></tr></thead><tbody>
                    <?php foreach ($result['generated_passwords'] as $item): ?>
                        <tr><td><?= htmlspecialchars((string)$item['username'], ENT_QUOTES, 'UTF-8') ?></td><td><code><?= htmlspecialchars((string)$item['password'], ENT_QUOTES, 'UTF-8') ?></code></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>

                <?php if (!empty($result['errors'])): ?>
                    <h3>Observaciones</h3>
                    <div class="errors">
                        <?php foreach ($result['errors'] as $message): ?><div><?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
