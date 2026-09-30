# Fase 11 - Tablero de Control Operativo

El tablero consolida la información disponible de operación en una sola vista, tomando como base los módulos ya implementados.

## Información actual

- casos abiertos;
- pendientes de asignación;
- casos asignados;
- casos con primera gestión;
- casos cerrados;
- semáforo ANS y vencidos;
- alertas abiertas;
- presencia operacional de agentes;
- capacidad configurada y capacidad libre;
- carga por agente;
- carga por cola;
- tipos de gestión;
- regional;
- canal de origen;
- tendencia de casos recibidos y cerrados;
- últimas cargas de archivos.

## Periodos

La actividad puede consultarse para:

- Hoy;
- últimos 7 días;
- mes actual;
- histórico.

Los indicadores de carga actual y ANS siempre representan el estado vigente. Los indicadores de actividad se calculan con el periodo seleccionado.

## Filtros

El tablero permite filtrar actividad por:

- cola;
- agente;
- periodo.

## Acceso

El tablero consolidado requiere SLA_VIEW, por lo que queda disponible para ADMIN y SUPERVISOR con la configuración RBAC actual.

Los AGENTE continúan utilizando la bandeja de casos y no reciben indicadores globales del equipo.

## Rutas

- /dashboard - ruta canónica del tablero.
- / - mantiene compatibilidad y muestra el mismo tablero.

El detalle especializado de ANS continúa en /sla.
