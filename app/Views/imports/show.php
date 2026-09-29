<?php
/** @var array<string,mixed> $batch */
/** @var list<array<string,mixed>> $rows */
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <h1 style="margin:0">Lote <?= htmlspecialchars((string)$batch['batch_number'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="muted">
                <?= htmlspecialchars((string)$batch['structure_name'], ENT_QUOTES, 'UTF-8') ?>
                · <?= htmlspecialchars((string)$batch['original_filename'], ENT_QUOTES, 'UTF-8') ?>
                · hoja <?= htmlspecialchars((string)($batch['selected_sheet_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <a class="btn btn-light" href="/imports">Volver</a>
    </div>

    <div class="grid">
        <div><strong>Total</strong><br><?= (int)$batch['total_rows'] ?></div>
        <div><strong>Válidas</strong><br><?= (int)$batch['valid_rows'] ?></div>
        <div><strong>Inválidas</strong><br><?= (int)$batch['invalid_rows'] ?></div>
        <div><strong>Duplicadas</strong><br><?= (int)$batch['duplicate_rows'] ?></div>
        <div><strong>Estado</strong><br><?= htmlspecialchars((string)$batch['status'], ENT_QUOTES, 'UTF-8') ?></div>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <h2 style="margin-top:0">Previsualización</h2>
    <p class="muted">Se muestran hasta 300 filas. Ningún caso ha sido creado todavía.</p>
    <div style="overflow:auto">
        <table>
            <thead><tr><th>Fila</th><th>Llave externa</th><th>Estado</th><th>Datos normalizados</th><th>Errores</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['source_row_number'] ?></td>
                    <td><?= htmlspecialchars((string)($r['external_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$r['validation_status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><pre style="white-space:pre-wrap;margin:0"><?= htmlspecialchars(json_encode($r['normalized'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre></td>
                    <td><?= htmlspecialchars(implode(' | ', $r['errors']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
