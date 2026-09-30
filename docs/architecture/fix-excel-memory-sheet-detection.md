# Corrección de importación XLSX

## Problema observado

La base de Peticiones contiene la hoja correcta `Asignacion Agente`, pero su dimensión interna de Excel llega hasta columnas muy lejanas debido a formato residual.

PhpSpreadsheet utilizaba `getHighestDataColumn()` y terminaba intentando materializar un rango extremadamente ancho, agotando el límite de memoria de PHP.

## Corrección

- Se consultan los nombres de hojas antes de cargar el libro.
- Para modo EXACT solo se carga la hoja seleccionada.
- Se utiliza `IReadFilter` para limitar la lectura a las primeras 128 columnas y al rango de filas permitido.
- El ancho real se determina por la última celda no vacía de la fila de encabezado.
- CSV ignora el selector de hoja, porque el formato no tiene múltiples hojas.
- Se valida la presencia de todos los encabezados obligatorios antes de procesar las filas.
- Se rechazan encabezados duplicados/ambiguos.

Esta corrección evita aumentar artificialmente `memory_limit` como solución principal.
