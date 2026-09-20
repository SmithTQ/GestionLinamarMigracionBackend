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
| `GET` | `/api/v1/campaigns/{campaign}/users` | `campaigns.users.view` |
| `POST` | `/api/v1/campaigns/{campaign}/users` | `campaigns.users.manage` |
| `DELETE` | `/api/v1/campaigns/{campaign}/users/{user}` | `campaigns.users.manage` |

## Creación y actualización

Los campos `role_ids`, `campaign_ids` y `branch_ids` permiten asignar acceso de forma explícita. Las contraseñas deben tener al menos 8 caracteres, letras, mayúsculas, minúsculas y números.

Solo un `super_admin` puede asignar el rol `super_admin`. La API impide desactivar al usuario actual y retirar el último super administrador activo.

## Ámbito

Un usuario que no es `super_admin` solo puede consultar usuarios relacionados con sus campañas o sucursales asignadas. Las asignaciones de campaña y sucursal también se validan contra el ámbito del actor.

Roles operativos actuales:

- `campaign_manager`: nombre visible `Administrador operativo`. Opera campañas, formularios, invitaciones, pedidos, productos y plantillas de distritos de sus sucursales asignadas.
- `dispatcher`: administra rutas y despacho de las campañas asignadas mediante `campaign_user` activo.
- `super_admin`: acceso global.

El `slug` de los roles es estable para no romper integraciones. Los permisos de nuevas pantallas se agregarán cuando esas funcionalidades sean definidas.

La documentación interactiva está disponible en `http://localhost:8000/api/documentation`.
## Reglas del Administrador operativo

El rol `campaign_manager` se muestra como `Administrador operativo` y cuenta con
los permisos `users.view`, `users.manage`, `routes.manage`,
`campaigns.users.view` y `campaigns.users.manage`.

Para este rol, `GET /users` requiere `branch_id` y solo devuelve Despachadores
activos de esa sucursal. `POST /users` permite únicamente el rol `dispatcher`,
exige exactamente una sucursal y rechaza `campaign_ids`; las campañas se
asignan desde el módulo de campañas.

`PATCH /users/{user}` y `DELETE /users/{user}` requieren `branch_id` como
contexto y solo permiten gestionar Despachadores de esa sucursal. `GET /roles`
devuelve únicamente el rol `dispatcher`.

`GET /campaigns/{campaign}/available-dispatchers` devuelve Despachadores activos
de la sucursal de la campaña que aún no están asignados. La asignación a una
campaña valida el rol `dispatcher` y la coincidencia de sucursal.
