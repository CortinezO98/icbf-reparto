# Fix Users v2

Corrige dos problemas del primer instalador de Users v2:

1. El instalador interpolaba accidentalmente `$router` dentro de cadenas PHP y dejó rutas inválidas.
2. PHPStan requería especificar `list<string>` en los parámetros de `roleIdsFromCodes()` y `queueIdsFromCodes()`.

El reparador elimina únicamente las rutas Users v2 actuales y reinserta una versión canónica, preservando las demás rutas del proyecto.
