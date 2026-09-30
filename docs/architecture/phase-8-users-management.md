# Fase 8 - Gestión de usuarios

La administración de usuarios se adapta al caso de reparto ICBF.

## Perfiles

- ADMIN
- SUPERVISOR
- AGENTE

## Agentes

Un usuario con rol AGENTE debe tener al menos una cola.

Al asignar una cola al agente, el sistema activa automáticamente las habilidades requeridas de esa cola mediante `queue_skills`.

Esto evita inconsistencias entre:

- perfil;
- cola;
- habilidad;
- motor de reparto.

## Reparto

`assign_enabled` es una habilitación administrativa.

No sustituye la presencia operacional. Para recibir casos se requiere además:

- agente activo;
- rol AGENTE;
- cola activa;
- habilidad requerida;
- estado Disponible;
- heartbeat vigente;
- capacidad libre.

## Administración

La pantalla permite:

- crear usuarios;
- editar usuarios;
- activar/desactivar;
- administrar roles;
- administrar colas de agentes;
- cambiar contraseña desde edición;
- habilitar/deshabilitar reparto.

Desactivar un usuario finaliza su presencia operacional actual.
