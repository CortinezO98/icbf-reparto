# Fase 7 - Presencia operativa de agentes

La presencia se implementa como estado operacional separado de `users.assign_enabled`.

## Estados seleccionables

- AVAILABLE - Disponible
- TRAINING - En capacitación
- MEETING - Reunión
- BREAK - Break
- ASYNC_ACTIVITY - Actividades asincrónicas
- BATHROOM - Baño
- TECH_FAILURE - Falla tecnológica
- FEEDBACK - Retroalimentación
- ACTIVE_BREAK - Pausas Activas

Existe además `OFFLINE` como estado técnico, no seleccionable por el agente.

## Regla de reparto

Solo `AVAILABLE` puede recibir nuevas asignaciones.

Además el heartbeat debe estar vigente. El valor por defecto es:

- heartbeat: 30 segundos
- stale: 90 segundos

Si una sesión vuelve después de superar el stale timeout, el backend fuerza `OFFLINE`.
El agente debe seleccionar nuevamente `Disponible`.

## Login / logout

- Login de un AGENTE: queda `OFFLINE`; nunca `AVAILABLE` automáticamente.
- Logout de un AGENTE: queda `OFFLINE`.

## Supervisor

`/supervisor/agents` muestra:

- agentes disponibles;
- capacidad libre;
- casos pendientes;
- total agentes;
- estado individual;
- colas;
- carga activa;
- última señal.

## Variables opcionales

AGENT_PRESENCE_HEARTBEAT_SECONDS=30
AGENT_PRESENCE_STALE_SECONDS=90
