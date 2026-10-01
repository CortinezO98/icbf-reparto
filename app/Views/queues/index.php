<?php
/** @var list<array<string,mixed>> $queues */
/** @var list<array<string,mixed>> $activeVersions */
/** @var string|null $error */
/** @var string|null $success */
?>
<div class="card"><h1><i class="bi bi-diagram-2 text-brand me-2"></i>Colas de trabajo</h1><?php if(!empty($error)): ?><div class="alert"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?><?php if(!empty($success)): ?><div class="alert" style="background:#dcfce7;color:#166534"><?= htmlspecialchars((string)$success,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?><table><thead><tr><th>Código</th><th>Nombre</th><th>Capacidad</th><th>Prioridad</th><th>Agentes</th><th>Estructuras</th></tr></thead><tbody><?php foreach($queues as $q): ?><tr><td><?= htmlspecialchars((string)$q['code'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$q['name'],ENT_QUOTES,'UTF-8') ?></td><td><?= (int)$q['default_capacity'] ?></td><td><?= (int)$q['priority'] ?></td><td><?= (int)$q['agent_count'] ?></td><td><?= (int)$q['structure_count'] ?></td></tr><?php endforeach; ?></tbody></table></div><div class="grid" style="margin-top:18px"><div class="card"><h2><i class="bi bi-plus-circle me-2"></i>Crear cola</h2><form method="post" action="/admin/queues/create"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(),ENT_QUOTES,'UTF-8') ?>"><label>Código</label><input name="code" required><label>Nombre</label><input name="name" required><label>Descripción</label><input name="description"><label>Capacidad</label><input type="number" name="default_capacity" min="1" value="1"><label>Prioridad</label><input type="number" name="priority" value="100"><button class="btn btn-primary" style="margin-top:12px">Crear</button></form></div><div class="card"><h2><i class="bi bi-link-45deg me-2"></i>Asociar estructura activa</h2><form method="post" action="/admin/queues/attach-structure"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Auth\Csrf::token(),ENT_QUOTES,'UTF-8') ?>"><label>Cola</label><select name="queue_id" required><option value="">Seleccionar</option><?php foreach($queues as $q): ?><option value="<?= (int)$q['id'] ?>"><?= htmlspecialchars((string)$q['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select><label>Versión activa</label><select name="structure_version_id" required><option value="">Seleccionar</option><?php foreach($activeVersions as $v): ?><option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars((string)$v['label'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select><button class="btn btn-primary" style="margin-top:12px">Asociar</button></form></div></div>

<div class="card" style="margin-top:18px">
 <h2><i class="bi bi-person-workspace me-2"></i>Capacidad por agente y cola</h2>
 <p class="muted">La capacidad define el máximo de casos abiertos que el motor puede mantener simultáneamente para cada agente en cada cola.</p>
 <?php if ($agentCapacities !== []): ?>
 <div style="overflow-x:auto">
  <table>
   <thead><tr><th>Cola</th><th>Agente</th><th>Casos abiertos</th><th>Capacidad</th><th>Configuración</th></tr></thead>
   <tbody>
   <?php foreach ($agentCapacities as $item): ?>
    <?php $effectiveCapacity = $item['capacity_override'] !== null ? (int)$item['capacity_override'] : (int)$item['default_capacity']; ?>
    <tr>
     <td>
      <strong><?= htmlspecialchars((string)$item['queue_code'],ENT_QUOTES,'UTF-8') ?></strong>
      <div class="muted"><?= htmlspecialchars((string)$item['queue_name'],ENT_QUOTES,'UTF-8') ?></div>
     </td>
     <td>
      <strong><?= htmlspecialchars((string)$item['agent_name'],ENT_QUOTES,'UTF-8') ?></strong>
      <div class="muted"><?= htmlspecialchars((string)$item['agent_username'],ENT_QUOTES,'UTF-8') ?></div>
     </td>
     <td><?= (int)$item['open_cases'] ?></td>
     <td><span class="badge"><?= $effectiveCapacity ?></span></td>
     <td>
      <form method="post" action="/admin/queues/<?= (int)$item['queue_id'] ?>/agents/<?= (int)$item['user_id'] ?>/capacity" style="display:flex;gap:8px;align-items:center">
       <input type="hidden" name="_csrf" value="<?= htmlspecialchars(AppAuthCsrf::token(),ENT_QUOTES,'UTF-8') ?>">
       <input type="number" name="capacity" min="1" max="1000"
              value="<?= $item['capacity_override'] !== null ? (int)$item['capacity_override'] : '' ?>"
              placeholder="Por defecto" style="max-width:150px">
       <button class="btn btn-primary" type="submit">Guardar</button>
       <?php if ($item['capacity_override'] !== null): ?>
        <button class="btn btn-secondary" type="submit" name="capacity" value="">Usar capacidad de cola</button>
       <?php endif; ?>
      </form>
     </td>
    </tr>
   <?php endforeach; ?>
   </tbody>
  </table>
 </div>
 <?php else: ?>
  <div class="empty-state">No hay agentes asociados a las colas activas.</div>
 <?php endif; ?>
</div>
