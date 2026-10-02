# Fase 10 - ANS y alertas

Política inicial:

- objetivo: 6 horas hábiles;
- lunes a viernes;
- 08:00 a 17:00;
- zona horaria `America/Bogota`;
- verde: menos de 2 horas;
- amarillo: desde 2 horas y menos de 5 horas;
- rojo: desde 5 horas y menos de 6 horas;
- vencido: 6 horas o más;
- alerta de caso sin gestión: desde 4 horas hábiles sin primera gestión.

La tabla `sla_holidays` permite excluir festivos o cierres operativos sin modificar código.

## Ejecución

El worker:

```bash
php bin/evaluate-sla.php
```

actualiza los casos abiertos y sincroniza `case_alerts`.

La pantalla `/sla` también recalcula al abrir para evitar mostrar información obsoleta si el worker se retrasa.

## Cierre de casos

Cuando una gestión cambia el caso a `CLOSED`, el flujo de cierre ejecuta una evaluación puntual del ANS.

Esto garantiza que:

- `sla_elapsed_minutes` conserve el tiempo hábil real hasta `closed_at`;
- `sla_due_at` conserve el vencimiento calculado;
- `sla_status` conserve el estado final del ANS;
- las alertas abiertas del caso se resuelvan al cerrar.

Los casos cerrados no se vuelven a evaluar como casos operativos abiertos.

## Alertas

- `NO_MANAGEMENT`
- `NEAR_SLA`
- `SLA_BREACHED`

Las alertas usan `dedupe_key` para evitar duplicados y se resuelven automáticamente cuando deja de cumplirse la condición.
