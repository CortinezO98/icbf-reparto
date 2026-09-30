<?php
/** @var array<string,mixed> $structure */
/** @var list<array<string,mixed>> $versions */
/** @var array<int,list<array<string,mixed>>> $versionFields */
/** @var string|null $error */
/** @var string|null $success */
?>

<div class="card">
    <h1><?= htmlspecialchars((string)$structure['name'],ENT_QUOTES,'UTF-8') ?></h1>

    <p class="muted">
        <?= htmlspecialchars((string)$structure['code'],ENT_QUOTES,'UTF-8') ?>
    </p>

    <?php if(!empty($error)): ?>
        <div class="alert">
            <?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <div class="alert" style="background:#dcfce7;color:#166534">
            <?= htmlspecialchars((string)$success,ENT_QUOTES,'UTF-8') ?>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:18px">
    <h2>Crear versión</h2>

    <form method="post" action="/admin/structures/<?= (int)$structure['id'] ?>/versions/create">

        <input
            type="hidden"
            name="_csrf"
            value="<?= htmlspecialchars(\App\Auth\Csrf::token(),ENT_QUOTES,'UTF-8') ?>"
        >

        <div class="grid">

            <div>
                <label>Modo de hoja</label>

                <select name="target_sheet_mode">
                    <option value="FIRST_MATCH">Primera coincidencia</option>
                    <option value="EXACT">Nombre exacto</option>
                    <option value="REGEX">Regex</option>
                </select>
            </div>

            <div>
                <label>Nombre/patrón hoja</label>
                <input name="target_sheet_value">
            </div>

            <div>
                <label>Fila encabezado</label>
                <input
                    type="number"
                    name="header_row"
                    min="1"
                    value="1"
                >
            </div>

            <div>
                <label>Primera fila datos</label>
                <input
                    type="number"
                    name="data_start_row"
                    min="2"
                    value="2"
                >
            </div>

            <div>
                <label>Código llave externa</label>
                <input
                    name="external_key_field_code"
                    placeholder="numero_peticion"
                    required
                >
            </div>

        </div>

        <label style="font-weight:400">
            <input
                type="checkbox"
                name="allow_xlsx"
                value="1"
                checked
                style="width:auto"
            >
            XLSX
        </label>

        <label style="font-weight:400">
            <input
                type="checkbox"
                name="allow_csv"
                value="1"
                checked
                style="width:auto"
            >
            CSV
        </label>

        <label>Notas</label>
        <input name="notes">

        <button
            class="btn btn-primary"
            style="margin-top:12px"
        >
            Crear versión borrador
        </button>

    </form>
</div>

<?php foreach($versions as $v): ?>

    <div class="card" style="margin-top:18px">

        <div style="display:flex;justify-content:space-between">

            <div>
                <h2>
                    Versión <?= (int)$v['version_number'] ?>
                </h2>

                <p class="muted">
                    <?= htmlspecialchars((string)$v['status'],ENT_QUOTES,'UTF-8') ?>
                    · encabezado <?= (int)$v['header_row'] ?>
                    · datos <?= (int)$v['data_start_row'] ?>
                </p>
            </div>

            <?php if($v['status'] !== 'ACTIVE'): ?>

                <form
                    method="post"
                    action="/admin/structures/<?= (int)$structure['id'] ?>/versions/<?= (int)$v['id'] ?>/activate"
                >

                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?= htmlspecialchars(\App\Auth\Csrf::token(),ENT_QUOTES,'UTF-8') ?>"
                    >

                    <button class="btn btn-primary">
                        Activar
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <table>

            <thead>
                <tr>
                    <th>Código</th>
                    <th>Encabezado</th>
                    <th>Tipo</th>
                    <th>Req.</th>
                    <th>Llave</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach(($versionFields[(int)$v['id']] ?? []) as $f): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars((string)$f['field_code'],ENT_QUOTES,'UTF-8') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$f['excel_header'],ENT_QUOTES,'UTF-8') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$f['data_type'],ENT_QUOTES,'UTF-8') ?>
                    </td>

                    <td>
                        <?= (int)$f['is_required'] ? 'Sí' : 'No' ?>
                    </td>

                    <td>
                        <?= (int)$f['is_external_key'] ? 'Sí' : 'No' ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

        <?php if($v['status'] === 'DRAFT'): ?>

            <h3>Agregar campo</h3>

            <form
                method="post"
                action="/admin/structures/<?= (int)$structure['id'] ?>/versions/<?= (int)$v['id'] ?>/fields/create"
            >

                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= htmlspecialchars(\App\Auth\Csrf::token(),ENT_QUOTES,'UTF-8') ?>"
                >

                <div class="grid">

                    <div>
                        <label>Código</label>
                        <input
                            name="field_code"
                            required
                        >
                    </div>

                    <div>
                        <label>Nombre visible</label>
                        <input
                            name="display_name"
                            required
                        >
                    </div>

                    <div>
                        <label>Encabezado Excel</label>
                        <input
                            name="excel_header"
                            required
                        >
                    </div>

                    <div>
                        <label>Tipo</label>

                        <select name="data_type">
                            <option>STRING</option>
                            <option>INTEGER</option>
                            <option>DECIMAL</option>
                            <option>DATE</option>
                            <option>TIME</option>
                            <option>DATETIME</option>
                            <option>BOOLEAN</option>
                            <option>CATALOG</option>
                        </select>
                    </div>

                    <div>
                        <label>Longitud máx.</label>
                        <input
                            type="number"
                            name="max_length"
                        >
                    </div>

                    <div>
                        <label>Formato fecha</label>
                        <input
                            name="date_format"
                            placeholder="d/m/Y"
                        >
                    </div>

                    <div>
                        <label>Orden</label>
                        <input
                            type="number"
                            name="sort_order"
                            value="0"
                        >
                    </div>

                </div>

                <label>Alias encabezado</label>

                <input
                    name="header_aliases"
                    placeholder="Número de Petición; Numero Peticion"
                >

                <label>Regex</label>
                <input name="validation_regex">

                <label style="font-weight:400">
                    <input
                        type="checkbox"
                        name="is_required"
                        value="1"
                        style="width:auto"
                    >
                    Obligatorio
                </label>

                <label style="font-weight:400">
                    <input
                        type="checkbox"
                        name="is_external_key"
                        value="1"
                        style="width:auto"
                    >
                    Llave externa
                </label>

                <label style="font-weight:400">
                    <input
                        type="checkbox"
                        name="is_reportable"
                        value="1"
                        style="width:auto"
                    >
                    Reportable
                </label>

                <br>

                <button
                    class="btn btn-primary"
                    style="margin-top:10px"
                >
                    Agregar campo
                </button>

            </form>

        <?php endif; ?>

    </div>

<?php endforeach; ?>