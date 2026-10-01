# Fase 12 - Reportes operativos

La fase 12 agrega un módulo de consulta y exportación para ADMIN y SUPERVISOR.

## Acceso

La ruta canónica es:

- `/reports` - consulta de reportes.
- `/reports/export` - exportación CSV.

El acceso se controla mediante:

- `REPORT_VIEW` para consultar.
- `REPORT_EXPORT` para exportar.

Los permisos ya forman parte de RBAC y están asignados a ADMIN y SUPERVISOR.

## Filtros

El reporte permite filtrar por:

- periodo: hoy, últimos 7 días, mes actual o histórico;
- cola;
- agente;
- estado;
- ANS.

## Información

La vista consolida:

- total de casos;
- abiertos;
- pendientes de asignación;
- cerrados;
- casos con primera gestión;
- cumplimiento ANS de casos cerrados;
- distribución por estado;
- distribución por ANS;
- resumen por cola;
- resumen por agente;
- detalle de casos.

El detalle conserva los campos operativos disponibles en `cases`, incluyendo radicado, tipo de petición, regional, canal, asignación, gestión y cierre.

## Exportación

La exportación utiliza los mismos filtros de la consulta y genera CSV UTF-8 con BOM y separador `;`, compatible con Excel en configuración regional habitual de Colombia.

La exportación está limitada a 5.000 registros por solicitud para evitar respuestas excesivamente grandes.

## Seguridad

- Las rutas exigen autenticación y permiso explícito.
- No se construyen consultas SQL con valores de filtros interpolados.
- Los valores enumerados de estado y ANS se validan contra listas permitidas.
- Los identificadores de cola y agente se normalizan a enteros positivos.
- La salida HTML usa `htmlspecialchars`.
- La exportación no incluye contraseñas ni información de autenticación.

## Validación

Después de descargar la rama:

```bash
docker compose exec app composer analyse
docker compose exec app composer test
docker compose exec app composer lint
```

Luego iniciar sesión como ADMIN o SUPERVISOR y validar:

1. `/reports`.
2. Aplicación de cada filtro.
3. Enlaces hacia el detalle de cada caso.
4. Exportación CSV.
5. Acceso denegado para AGENTE.
