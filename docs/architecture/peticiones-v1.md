# PETICIONES v1

Esta migración crea de forma reproducible la primera versión activa de PETICIONES y la vincula con la cola PETICIONES.

Configuración:
- Hoja: `Asignacion Agente`
- Encabezado: fila 1
- Datos: fila 2
- Llave externa: `numero_peticion`
- Cola: PETICIONES
- Capacidad de cola: 5 (definida en la migración de alcance actualizado)

La versión contiene los diez campos observados en la base operativa revisada.

Importante:
El alcance actualizado menciona regional y canal de origen como campos mínimos, pero esos campos no forman parte de los encabezados de la base de Peticiones revisada. Se mantienen como brecha funcional y no se inventan en v1.
