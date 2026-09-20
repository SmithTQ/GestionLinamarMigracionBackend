# Auditoria RBAC y ambitos operativos

Fecha: 2026-09-18

## Alcance de la auditoria inicial

Esta fase fue exclusivamente de inspeccion. No se ejecutaron migraciones, seeders, actualizaciones, eliminaciones ni cambios de datos.

## Respaldo previo

Antes de la auditoria se genero el respaldo completo:

`database/backups/bdgestionlinamarmigracion-before-rbac-scope-refactor-20260918-204608.sql`

El respaldo incluye estructura, registros, triggers, eventos, rutinas y datos binarios. Se uso `--no-tablespaces` porque el usuario de la base de datos no tiene el privilegio `PROCESS`; esto no excluye tablas ni registros.

## Hallazgos actuales

- Hay 7 campanas y todas tienen sucursal `CENTRAL` con `branch_id = 2`.
- Hay 10 productos: 5 pueden asociarse a una unica sucursal y 5 no tienen una sucursal inferible.
- No hay productos con asociacion ambigua a multiples sucursales.
- Hay 1 listado de distritos y su sucursal puede inferirse sin ambiguedad.
- Existen 2 usuarios no eliminados: 1 super administrador y 1 administrador operativo.
- El administrador operativo tiene una sucursal asignada, pero no tiene campanas asignadas actualmente.
- No existen usuarios dispatcher activos en la base de datos auditada.
- Los roles actuales conservan la matriz anterior de permisos; la expansion a permisos granulares todavia no se ha aplicado en la base de datos.
- En la auditoria inicial, las tablas `products` y `district_lists` aun no tenian `branch_id`.

## Continuacion de la fase

- `products.branch_id` ya tiene una migracion preparada y la data existente fue asignada a `CENTRAL` despues del respaldo correspondiente.
- `district_lists.branch_id` tiene una migracion preparada, pero aun no se ha ejecutado sobre la base de datos de trabajo.
- La migracion de listados exige exactamente una sucursal activa cuando existan registros y no elimina ni sobrescribe registros no nulos.
- El backend ya valida el alcance de sucursal en productos y plantillas de distritos.

## Decisiones necesarias antes de modificar el esquema

### Productos sin sucursal

Los 5 productos sin sucursal inferible deben resolverse de forma explicita antes de agregar un `branch_id` obligatorio. No se asignara una sucursal automaticamente ni se eliminara ningun producto.

Opciones:

1. Asignarlos manualmente a una sucursal.
2. Mantener `branch_id` nullable para productos globales.
3. Archivarlos, unicamente con confirmacion expresa.

La opcion recomendada es la asignacion manual; permite mantener la integridad y evita cambiar el significado funcional de los productos.

### Matriz de roles

La solicitud actual propone que `campaign_manager` pueda gestionar rutas, mientras que el acuerdo anterior separaba la operacion de despacho en `dispatcher`. Esta diferencia debe confirmarse antes de cambiar permisos en produccion.

La migracion del nombre visible de `campaign_manager` ya esta preparada en el codigo, pero no se ha ejecutado sobre la base de datos.

## Siguiente fase propuesta

1. Confirmar el destino de los 5 productos sin sucursal.
2. Confirmar si el administrador operativo tendra permisos de rutas o si se mantiene la separacion con dispatcher.
3. Implementar `OperationalScopeService` y politicas reutilizables sin cambiar datos.
4. Preparar migraciones reversibles para `products.branch_id` y `district_lists.branch_id`.
5. Ejecutar la migracion de esquema solo despues de un nuevo respaldo y de resolver los registros pendientes.
6. Implementar los enlaces publicos seguros para rutas y sus pruebas.

## Regla de seguridad

Ninguna migracion de datos o esquema debe ejecutarse sin respaldo previo y confirmacion expresa. Las migraciones no deben usar `migrate:fresh`, `db:wipe`, `truncate` ni seeders destructivos sobre la base de datos de trabajo.
