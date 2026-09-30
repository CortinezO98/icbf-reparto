# Fase 6 - Motor de reparto automático

## Elegibilidad

Un agente puede recibir un caso cuando:

1. Está activo.
2. Tiene `assign_enabled = 1`.
3. Tiene rol `AGENTE`.
4. Está asociado y habilitado en la cola.
5. Su presencia actual es `AVAILABLE`.
6. Cumple todas las habilidades requeridas por la cola.
7. Su cantidad de casos abiertos en esa cola es menor que su capacidad.

## Capacidad

La capacidad efectiva es:

`queue_agents.capacity_override`, cuando existe.

En caso contrario:

`work_queues.default_capacity`.

Valores del alcance actualizado:

- PETICIONES: 5
- ANEXOS: 5
- ANEXOS_200: 3
- IO: 13

## Equidad

El orden de selección usa `users.last_assigned_at`.

Los agentes que nunca han recibido un caso se priorizan primero. Después se selecciona
el agente con la asignación más antigua.

## Concurrencia

Cada asignación se ejecuta en una transacción.

Orden principal de bloqueo:

1. Caso pendiente.
2. Usuario candidato.

Después de bloquear al usuario se revalidan disponibilidad, elegibilidad y capacidad.
Esto evita exceder capacidad cuando dos procesos intentan repartir simultáneamente.

## Trazabilidad

Una asignación crea:

- actualización de `cases`;
- fila en `case_assignments`;
- evento `CASE_ASSIGNED`;
- actualización de `users.last_assigned_at`.

## Ejecución

Diagnóstico:

`php bin/assignment-diagnostics.php`

Reparto:

`php bin/assign-pending.php`

Opcionalmente:

`php bin/assign-pending.php --queue=1 --max=100`
