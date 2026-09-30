# Gestión de usuarios - estilo ICBF Mail

Este ajuste deja Crear/Editar Usuario con la misma estructura visual compacta del formulario de ICBF Mail:

- formulario centrado;
- una sola tarjeta principal;
- Bootstrap 5;
- iconos en etiquetas, no como bloques sobre inputs;
- secciones Información Básica / Configuración de Acceso / Roles y Permisos;
- selección múltiple de roles;
- selección múltiple de colas;
- botón Todas / Limpiar para colas;
- acciones compactas;
- caja de información final.

Se conserva la lógica propia de ICBF Reparto:
- documento obligatorio;
- política de contraseña de 12 caracteres;
- AGENTE requiere al menos una cola;
- `assign_enabled`;
- soporte para todas las colas;
- CSRF y validaciones de backend.
