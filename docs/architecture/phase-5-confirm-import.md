# Fase 5 - Confirmación de importación y creación de casos

La confirmación convierte únicamente filas `VALID` del staging en casos operativos.

## Reglas

- Solo lotes `VALIDATED` o `VALIDATED_WITH_ERRORS` pueden confirmarse.
- La operación se ejecuta en una transacción.
- El lote se bloquea con `FOR UPDATE`.
- Las filas válidas también se bloquean durante la confirmación.
- Si ya existe un caso con la misma llave externa, la fila se marca `DUPLICATE`.
- La llave externa corresponde al SIM según el alcance funcional.
- Cada caso recibe un número interno `REP-*`.
- Cada creación genera un evento `CASE_CREATED`.
- Un lote `COMPLETED` no puede confirmarse nuevamente.
- Las filas `INVALID` no crean casos.
- Este corte no realiza reparto automático.

## Estado inicial del caso

Todo caso confirmado nace como:

`PENDING_ASSIGNMENT`

El motor de asignación se implementa en el siguiente corte.
