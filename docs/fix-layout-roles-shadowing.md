# Corrección de colisión de variable `$roles`

`UsersController::createForm()` entrega `$roles` a `app/Views/users/create.php`.

El layout global también usaba una variable llamada `$roles` para los roles de la sesión actual.
Como el layout se carga antes de incluir la vista, esa variable global sobrescribía el arreglo que
el controlador había preparado para el formulario.

La corrección cambia la variable interna del layout a `$currentUserRoles`.
