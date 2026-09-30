<?php
/** @var list<array<string,mixed>> $batches */
/** @var list<array<string,mixed>> $versions */
/** @var string|null $error */
/** @var string|null $success */
?>
<div class="grid">
    <div class="card">
        <h1 style="margin-top:0"><i class="bi bi-cloud-arrow-up text-brand me-2"></i>Nueva importación</h1>
        <p class="muted">
            Carga XLSX o CSV contra una estructura activa.
            La cola se resolverá automáticamente cuando la estructura tenga una sola asociada.
        </p>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert" style="background:#dcfce7;color:#166534">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/imports/upload" enctype="multipart/form-data">
            <input
                type="hidden"
                name="_csrf"
                value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <label>Estructura activa</label>
            <select name="structure_version_id" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($versions as $v): ?>
                    <option value="<?= (int)$v['id'] ?>">
                        <?= htmlspecialchars((string)$v['structure_name'], ENT_QUOTES, 'UTF-8') ?>
                        v<?= (int)$v['version_number'] ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Archivo</label>
            <input type="file" name="import_file" accept=".xlsx,.csv" required>

            <button class="btn btn-primary" type="submit" style="margin-top:16px">
                Validar archivo
            </button>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><i class="bi bi-shield-lock me-2"></i>Controles aplicados</h2>
        <p class="muted">
            Máximo 20 MB, MIME validado, SHA-256, nombre físico aleatorio,
            almacenamiento fuera de public, validación de encabezados,
            detección de duplicados y validación fila a fila.
        </p>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <h2 style="margin-top:0"><i class="bi bi-clock-history me-2"></i>Lotes recientes</h2>

    <div style="overflow:auto">
        <table>
            <thead>
            <tr>
                <th>Lote</th>
                <th>Estructura</th>
                <th>Cola</th>
                <th>Archivo</th>
                <th>Estado</th>
                <th>Válidas</th>
                <th>Errores</th>
                <th>Duplicadas</th>
                <th></th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($batches as $b): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$b['batch_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$b['structure_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($b['queue_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$b['original_filename'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$b['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$b['valid_rows'] ?></td>
                    <td><?= (int)$b['invalid_rows'] ?></td>
                    <td><?= (int)$b['duplicate_rows'] ?></td>
                    <td><a class="btn btn-light" href="/imports/<?= (int)$b['id'] ?>"><i class="bi bi-eye me-1"></i>Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
