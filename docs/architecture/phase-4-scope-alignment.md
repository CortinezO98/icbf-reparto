# Fase 4 - Alineación con alcance actualizado y núcleo de casos

Este corte incorpora la actualización funcional recibida para el módulo de reparto.

## Capacidades operativas

- PETICIONES: 5 simultáneos
- ANEXOS: 5 simultáneos
- ANEXOS_200: 3 simultáneos
- IO: 13 simultáneos

## Presencia del agente

Se modelan como estados de presencia independientes del estado del caso:

- Disponible
- En capacitación
- Reunión
- Break
- Actividades asincrónicas
- Baño
- Falla tecnológica
- Retroalimentación
- Pausas Activas

## Núcleo de casos

`cases` mantiene el estado actual para consulta rápida.

`case_assignments`, `case_managements` y `case_events` conservan el historial y la trazabilidad.

La llave externa se modela inicialmente con `external_key`, asociada al número SIM.

## Tipificaciones

Los tipos de gestión y categorías de escalamiento se cargan como catálogos editables, no como ENUM de negocio.

## Habilidades

Las tablas `skills`, `user_skills` y `queue_skills` permiten limitar qué agentes pueden recibir qué colas.

## Pendientes

- Confirmación de importación -> creación/actualización de casos.
- Motor de reparto transaccional.
- Reasignación por fin de turno.
- ANS/calendario hábil.
- Paneles y reportes.
