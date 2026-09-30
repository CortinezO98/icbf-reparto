# Gestión de Usuarios v2

Este bloque adapta la experiencia visual y funcional de Gestión de Usuarios del portal ICBF Mail al módulo ICBF Reparto.

Incluye:

- KPIs;
- búsqueda;
- filtros por estado, rol y cola;
- paginación;
- creación;
- edición;
- activación/desactivación;
- generación de contraseña temporal;
- roles;
- colas;
- habilitación administrativa para reparto;
- asignación automática de skills requeridas;
- importación XLSX/CSV;
- plantilla XLSX;
- exportación XLSX;
- presencia visible en el listado.

## Diferencia operacional

`assign_enabled` no equivale a `Disponible`.

Para recibir casos, un agente debe cumplir simultáneamente:

- activo;
- AGENTE;
- habilitado para reparto;
- cola;
- skills;
- presencia Disponible;
- heartbeat vigente;
- capacidad libre.
