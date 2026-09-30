# Formulario profesional de creación de usuarios

Mejoras aplicadas:

- estructura visual por pasos;
- diseño Bootstrap consistente con ICBF Mail;
- resumen dinámico;
- roles y colas como tarjetas seleccionables;
- soporte visual para "Todas las colas";
- contador de colas seleccionadas;
- validación accesible en cliente;
- prevención de doble envío;
- generación de contraseña segura con Web Crypto;
- indicador de política de contraseña;
- mostrar/ocultar y copiar contraseña;
- `autocomplete` apropiado;
- límites de longitud alineados con el esquema SQL;
- validación de formato de username también en servidor;
- normalización de campos de una sola línea;
- límite de contraseña de 128 caracteres en servidor;
- CSRF y validaciones de roles/colas continúan siendo autoritativas en backend.

La validación del navegador mejora la experiencia, pero no reemplaza la validación de servidor.
