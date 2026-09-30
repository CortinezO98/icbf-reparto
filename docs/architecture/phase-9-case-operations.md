# Fase 9 - Operación de casos

Incluye:

- bandeja `/cases`;
- vista propia para AGENTE;
- vista amplia para perfiles con `CASE_VIEW_TEAM` o `CASE_VIEW_ALL`;
- filtros;
- detalle del caso;
- datos normalizados del archivo origen;
- historial de eventos;
- historial de gestiones;
- registro de gestión;
- soporte privado;
- cierre;
- direccionamiento;
- escalamiento;
- cambio de tipo de petición;
- reporte a policía.

## Regla importante

El tipo de gestión no se usa como estado operacional salvo `CLOSED`.

Ejemplos:

- `DIRECTED`: el caso puede seguir operativo, pero queda registrado como gestión actual.
- `ESCALATED`: queda registrada la gestión y su categoría.
- `PETITION_TYPE_CHANGE`: actualiza el tipo de petición.
- `CLOSED`: cambia el estado operacional a `CLOSED` y termina la asignación abierta.

Esto conserva la separación entre estado del caso y resultado/tipificación de gestión.
