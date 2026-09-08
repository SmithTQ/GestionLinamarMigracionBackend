# Usuarios, roles y permisos

Todas las rutas requieren un token Bearer y el permiso indicado.

## Endpoints

| Método | Ruta | Permiso |
| --- | --- | --- |
| `GET` | `/api/v1/users` | `users.view` |
| `POST` | `/api/v1/users` | `users.manage` |
| `GET` | `/api/v1/users/{user}` | `users.view` |
| `PATCH` | `/api/v1/users/{user}` | `users.manage` |
| `DELETE` | `/api/v1/users/{user}` | `users.manage` |
| `GET` | `/api/v1/roles` | `users.view` |
| `GET` | `/api/v1/permissions` | `users.view` |

## Creación y actualización

Los campos `role_ids`, `campaign_ids` y `branch_ids` permiten asignar acceso de forma explícita. Las contraseñas deben tener al menos 8 caracteres, letras, mayúsculas, minúsculas y números.

Solo un `super_admin` puede asignar el rol `super_admin`. La API impide desactivar al usuario actual y retirar el último super administrador activo.

## Ámbito

Un usuario que no es `super_admin` solo puede consultar usuarios relacionados con sus campañas o sucursales asignadas. Las asignaciones de campaña y sucursal también se validan contra el ámbito del actor.

La documentación interactiva está disponible en `http://localhost:8000/api/documentation`.
