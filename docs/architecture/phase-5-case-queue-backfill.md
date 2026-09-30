# Corrección de cola en casos ya confirmados

Los primeros casos fueron confirmados cuando el lote aún no tenía `queue_id`.
Después se corrigió el lote para asociarlo con PETICIONES, pero los casos ya creados
conservaron `queue_id = NULL`.

La migración `0010_backfill_case_queue_from_batch.sql` corrige únicamente casos
sin cola que tengan un lote de origen con cola asociada.

Las cargas futuras no requieren este backfill porque `ImportStagingService`
resuelve automáticamente la única cola asociada antes de crear el lote.
