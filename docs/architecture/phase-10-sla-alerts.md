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

actualiza `cases.sla_*` y sincroniza `case_alerts`.

En producción debe programarse con cron o scheduler (por ejemplo cada 5 minutos).
La pantalla `/sla` también recalcula al abrir para evitar mostrar información obsoleta si el worker se retrasa.

## Alertas

- `NO_MANAGEMENT`
- `NEAR_SLA`
- `SLA_BREACHED`

Las alertas usan `dedupe_key` para evitar duplicados y se resuelven automáticamente cuando deja de cumplirse la condición.
