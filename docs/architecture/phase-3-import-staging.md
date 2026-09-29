# Fase 3 - Importación con staging

Este corte implementa la recepción segura de XLSX/CSV contra una versión activa de estructura.

Flujo:
1. El supervisor/admin selecciona estructura activa.
2. Se valida tamaño, extensión, MIME y huella SHA-256.
3. El archivo se guarda fuera de `public/` con nombre aleatorio.
4. PhpSpreadsheet lee datos con `readDataOnly`.
5. Se selecciona la hoja configurada.
6. Se normalizan encabezados y se valida cada fila.
7. Se detectan duplicados dentro del archivo.
8. Se registra `import_batches` y `import_batch_rows`.
9. Se presenta preview de válidas, inválidas y duplicadas.
10. Este corte no crea casos ni asigna agentes.

Límites iniciales:
- 20 MB por archivo.
- Hasta 10.000 filas por carga.
- Preview hasta 300 filas.
- XLSX y CSV según versión de estructura.
