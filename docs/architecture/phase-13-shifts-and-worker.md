# Fase 13 - Turnos, cronograma y automatización

Este bloque convierte el motor de asignación existente en una operación continua y agrega el cronograma que determina la elegibilidad operacional por turno.

## Turnos

Se incorporan:

- catálogo de turnos reutilizables;
- asociación agente + cola + turno;
- cronograma recurrente por día de semana;
- cronograma por fecha específica;
- vigencia opcional desde/hasta;
- activación y desactivación de registros;
- validación de cruces horarios.

## Regla de elegibilidad

Un agente puede recibir un caso únicamente si mantiene las condiciones existentes:

- usuario activo;
- rol AGENTE;
- reparto habilitado;
- cola activa;
- skills requeridas;
- presencia Disponible;
- heartbeat vigente;
- capacidad libre;
- turno vigente para la cola.

El turno se evalúa en America/Bogota y se aplica al momento en que el motor realiza la asignación.

## Fin de turno

El worker detecta turnos finalizados de forma idempotente mediante agent_shift_runs.

Al finalizar un turno:

1. se registra el evento de fin de turno;
2. se evita procesarlo nuevamente para la misma fecha;
3. se libera la asignación abierta del agente para esa cola;
4. se registra SHIFT_END en case_assignments;
5. se devuelve el caso a PENDING_ASSIGNMENT;
6. se registra CASE_RELEASED_SHIFT_END;
7. el motor intenta reasignar el caso respetando cola, skills, presencia, heartbeat, capacidad y turno vigente;
8. si no existe candidato, el caso permanece pendiente para el siguiente ciclo del worker.

Si el agente tiene otro turno vigente en otra cola, no se fuerza su presencia a OFFLINE.

## Worker

bin/worker.php ejecuta continuamente:

- procesamiento de fin de turno;
- reparto automático de casos pendientes;
- evaluación ANS.

Variables:

- WORKER_INTERVAL_SECONDS=10;
- SLA_WORKER_INTERVAL_SECONDS=300.

El worker utiliza un GET_LOCK de MariaDB para evitar dos instancias activas simultáneamente.

## Trazabilidad

Las asignaciones normales usan assignment_type=AUTO.

Las reasignaciones producidas por fin de turno usan assignment_type=REASSIGN.

El historial conserva la asignación anterior con ended_at y end_reason=SHIFT_END, mientras el nuevo ciclo queda registrado en case_assignments y case_events.

## Administración

La pantalla /admin/shifts permite:

- crear turnos;
- asociar agentes a colas y turnos;
- crear cronogramas semanales;
- crear excepciones por fecha;
- activar/desactivar cronogramas.

La administración está protegida con SHIFT_VIEW y SHIFT_MANAGE.