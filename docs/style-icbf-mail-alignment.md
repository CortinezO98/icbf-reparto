# Homologación visual con ICBF Mail

Este parche toma como referencia directa el frontend del proyecto ICBF Mail entregado junto con el snapshot de ICBF Reparto.

## Se homologó

- Bootstrap 5.3.3.
- Bootstrap Icons 1.11.3.
- Animate.css.
- SweetAlert2.
- Paleta principal verde `#4CAF50` y verde oscuro `#3f9c44`.
- Tipografía de Bootstrap (`system-ui`, con Segoe UI en Windows), eliminando la fuente Inter forzada.
- Navbar responsive con la misma composición visual.
- Tarjetas, tablas, inputs, botones, badges y fondos.
- Iconos Bootstrap en navegación, usuarios, cargas, casos, estructuras y colas.
- Login con la misma estructura visual del portal de correos.
- Selector de presencia adaptado al navbar Bootstrap sin cambiar la API actual de Reparto.

## No se cambió

- Lógica de negocio.
- Rutas backend.
- Repositorios.
- Migraciones.
- Motor de reparto.
- Seguridad/autorización.
- Contratos de presencia.

Los cambios son de presentación y compatibilidad de frontend.
