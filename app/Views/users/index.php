<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div>
            <h1 style="margin:0">Usuarios</h1>
            <p class="muted">Administración inicial de usuarios y perfiles.</p>
        </div>
        <a class="btn btn-primary" href="/admin/users/create">Crear usuario</a>
    </div>

    <div style="overflow:auto;margin-top:18px">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Documento</th>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Roles</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars((string)$u['document_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($u['roles'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$u['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
