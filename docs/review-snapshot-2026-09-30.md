# Revisión técnica del snapshot recibido

## Errores corregidos

1. `UserRepository` consultaba `users.last_login_at`, pero `0001_foundation.sql` no crea esa columna.
   Esto explica el error 500 al entrar a Gestión de Usuarios con `APP_DEBUG=0`.

2. `CaseOperationsRepository::findCase()` consultaba `import_batch_rows.normalized_data_json`,
   pero el esquema real usa `normalized_json`. El detalle de un caso habría fallado al abrirlo.

3. `routes/web.php` todavía no tenía `CasesController` ni las rutas `/cases`.
   Los archivos del módulo existían, pero el módulo no estaba expuesto por el router.

4. `tools/apply-cases-routes.php` interpolaba `$router` en una cadena PHP.
   Se reemplazó por una versión idempotente con nowdoc.

## Hardening incluido

- Los errores de importación ya no muestran mensajes crudos de excepciones al navegador.
- `import_batches.valid_rows` ya no se sobrescribe con la cantidad de casos creados al confirmar.
- El sufijo aleatorio de `CaseNumberGenerator` pasa de 32 bits a 64 bits.
- Se añadió `tools/runtime-diagnostics.php`.

## Estado funcional observado en el snapshot

Ya existen:
- RBAC;
- importación por estructuras;
- staging y validación;
- confirmación de lotes;
- casos;
- colas y skills;
- reparto automático;
- presencia/heartbeat;
- administración de usuarios V2;
- bandeja y gestión de casos en código.

Pendiente para siguientes bloques:
- ANS/SLA con calendario laboral y semáforo;
- alertas;
- reasignación por fin de turno/no disponibilidad;
- panel supervisor de casos y reasignación;
- dashboard/reportes;
- pruebas de integración para usuarios/importación/reparto/casos.
