<?php
/** @var list<array<string,mixed>> $users */
/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $queues */
/** @var array<string,int> $stats */
/** @var array<string,mixed> $pagination */
/** @var string|null $success */
/** @var string|null $error */

$search = trim((string)($_GET['search'] ?? ''));
$active = (string)($_GET['active'] ?? '');
$roleId = (string)($_GET['role_id'] ?? '');
$queueId = (string)($_GET['queue_id'] ?? '');

$query = static function(int $page) use ($search,$active,$roleId,$queueId): string {
    return http_build_query(array_filter([
        'page'=>$page,
        'search'=>$search,
        'active'=>$active,
        'role_id'=>$roleId,
        'queue_id'=>$queueId,
    ], static fn(mixed $v): bool => $v !== ''));
};
?>
<style>
.users-wrap{max-width:1180px;margin:0 auto}.users-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}.users-head h1{margin:0;font-size:1.75rem}.users-actions{display:flex;gap:9px;flex-wrap:wrap}.u-btn{border:1px solid #cbd5e1;background:#fff;color:#334155;padding:9px 13px;border-radius:7px;text-decoration:none;font-weight:650}.u-btn.green{background:#198f4c;border-color:#198f4c;color:#fff}.u-btn.blue{color:#0d6efd;border-color:#0d6efd}.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0}.stat{border-radius:14px;padding:18px 20px;min-height:90px;position:relative}.stat:nth-child(1){background:#e9f2ff}.stat:nth-child(2){background:#e9f7ef}.stat:nth-child(3){background:#e6f9fc}.stat:nth-child(4){background:#fff7df}.stat small{display:block;color:#475569}.stat strong{font-size:1.8rem;display:block;margin-top:5px}.panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 3px 12px rgba(15,23,42,.05);margin-top:18px;overflow:hidden}.panel-title{padding:12px 16px;border-bottom:1px solid #e2e8f0;background:#fbfcfe;font-weight:750}.filter-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;padding:16px;align-items:end}.filter-grid label{font-size:.88rem;margin:0 0 5px}.filter-grid input,.filter-grid select{height:40px}.table-box{overflow:auto}.users-table{min-width:1100px}.users-table th{font-size:.78rem;text-transform:uppercase}.badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:6px;font-size:.76rem;font-weight:750}.badge.role{background:#eaf2ff;color:#2563eb}.badge.ok{background:#dcfce7;color:#15803d}.badge.off{background:#edf2f7;color:#64748b}.badge.presence{background:#e6fffb;color:#0f766e}.actions{display:flex;gap:6px}.mini{padding:6px 9px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;text-decoration:none;color:#334155}.pagination{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;gap:12px}.pagination-links{display:flex;gap:6px}.pagination-links a{padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#334155}.flash-ok{padding:12px 14px;background:#dcfce7;color:#166534;border-radius:8px;margin:15px 0}.flash-error{padding:12px 14px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:15px 0}@media(max-width:900px){.stats-grid{grid-template-columns:1fr 1fr}.filter-grid{grid-template-columns:1fr 1fr}.filter-grid .filter-submit{grid-column:1/-1}}@media(max-width:560px){.stats-grid,.filter-grid{grid-template-columns:1fr}}
</style>

<div class="users-wrap">
    <div class="users-head">
        <div>
            <h1>👥 Gestión de Usuarios</h1>
            <div class="muted">Administra usuarios, perfiles, colas y habilitación para reparto.</div>
        </div>
        <div class="users-actions">
            <a class="u-btn green" href="/admin/users/create">⊕ Nuevo Usuario</a>
            <a class="u-btn blue" href="/admin/users/import">⇧ Importar</a>
            <a class="u-btn" href="/admin/users/export">⇩ Exportar</a>
        </div>
    </div>

    <?php if ($success): ?><div class="flash-ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="stats-grid">
        <div class="stat"><small>Total Usuarios</small><strong><?= (int)$stats['total_users'] ?></strong></div>
        <div class="stat"><small>Activos</small><strong><?= (int)$stats['active_users'] ?></strong></div>
        <div class="stat"><small>Asignables</small><strong><?= (int)$stats['assignable_users'] ?></strong></div>
        <div class="stat"><small>Agentes disponibles</small><strong><?= (int)$stats['available_agents'] ?></strong></div>
    </div>

    <div class="panel">
        <div class="panel-title">▽ Filtrar Usuarios</div>
        <form class="filter-grid" method="get" action="/admin/users">
            <div>
                <label>Buscar</label>
                <input name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Documento, usuario, email o nombre">
            </div>
            <div>
                <label>Estado</label>
                <select name="active">
                    <option value="">Todos</option>
                    <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Activos</option>
                    <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div>
                <label>Rol</label>
                <select name="role_id">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= $roleId === (string)$role['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Cola</label>
                <select name="queue_id">
                    <option value="">Todas las colas</option>
                    <?php foreach ($queues as $queue): ?>
                        <option value="<?= (int)$queue['id'] ?>" <?= $queueId === (string)$queue['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-submit"><button class="u-btn blue" type="submit">⌕ Filtrar</button></div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-title">▦ Lista de Usuarios <span class="badge off"><?= (int)$pagination['total'] ?></span></div>
        <div class="table-box">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Documento</th><th>Usuario</th><th>Nombre</th><th>Email</th>
                        <th>Roles</th><th>Colas</th><th>Reparto</th><th>Presencia</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= (int)$u['id'] ?></td>
                        <td><strong><?= htmlspecialchars((string)$u['document_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><strong><?= htmlspecialchars((string)$u['username'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars((string)$u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge role"><?= htmlspecialchars((string)($u['roles'] ?: 'Sin rol'), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars((string)($u['queues'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$u['assign_enabled'] === 1 ? '<span class="badge ok">Asignable</span>' : '<span class="badge off">No aplica</span>' ?></td>
                        <td><span class="badge presence"><?= htmlspecialchars((string)$u['presence_label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= (int)$u['is_active'] === 1 ? '<span class="badge ok">Activo</span>' : '<span class="badge off">Inactivo</span>' ?></td>
                        <td>
                            <div class="actions">
                                <a class="mini" href="/admin/users/<?= (int)$u['id'] ?>/edit" title="Editar">✎</a>
                                <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/toggle-active">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="mini" type="submit" title="<?= (int)$u['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>">
                                        <?= (int)$u['is_active'] === 1 ? 'Ⅱ' : '▶' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($users === []): ?>
                    <tr><td colspan="11" class="muted">No se encontraron usuarios con los filtros seleccionados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">Página <?= (int)$pagination['page'] ?> de <?= (int)$pagination['total_pages'] ?></span>
            <div class="pagination-links">
                <?php if ((int)$pagination['page'] > 1): ?>
                    <a href="/admin/users?<?= htmlspecialchars($query((int)$pagination['page'] - 1), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
                <?php endif; ?>
                <?php if ((int)$pagination['page'] < (int)$pagination['total_pages']): ?>
                    <a href="/admin/users?<?= htmlspecialchars($query((int)$pagination['page'] + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
