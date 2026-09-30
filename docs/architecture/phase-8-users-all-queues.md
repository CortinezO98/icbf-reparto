# Asociación de agentes a colas

Se agrega soporte explícito para seleccionar **Todas las colas activas** al crear o editar un AGENTE.

## Comportamiento

- Si se selecciona `Todas las colas activas`, el backend obtiene los IDs de todas las colas activas.
- La operación sigue usando `queue_agents`; no se crea una segunda fuente de verdad.
- Las habilidades requeridas se sincronizan mediante el flujo existente de `replaceQueuesAndSkills()`.
- Un usuario sin rol `AGENTE` no conserva asociaciones de cola ni `assign_enabled`.
- En importación masiva, la columna `Colas` acepta `TODAS`, `ALL`, `TODAS LAS COLAS`, `TODAS_LAS_COLAS` o `*`.

## Alcance de "todas"

Significa todas las colas activas **existentes al momento de guardar**. Si posteriormente se crea una cola nueva,
los agentes existentes no se asocian automáticamente a esa cola; esa decisión debe ser explícita para evitar
ampliar permisos/carga operacional sin aprobación.
