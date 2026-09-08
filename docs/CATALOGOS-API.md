# API de campañas y sucursales

Todas las rutas requieren `Authorization: Bearer <token>` y el permiso indicado.

## Sucursales

| Método | Ruta | Permiso |
| --- | --- | --- |
| `GET` | `/api/v1/branches` | `branches.view` |
| `POST` | `/api/v1/branches` | `branches.manage` |
| `GET` | `/api/v1/branches/{branch}` | `branches.view` |
| `PATCH` | `/api/v1/branches/{branch}` | `branches.manage` |
| `DELETE` | `/api/v1/branches/{branch}` | `branches.manage` |

La eliminación es lógica. Los usuarios que no son `super_admin` solo pueden consultar y modificar sucursales asignadas.

## Campañas

| Método | Ruta | Permiso |
| --- | --- | --- |
| `GET` | `/api/v1/campaigns` | `campaigns.view` |
| `POST` | `/api/v1/campaigns` | `campaigns.manage` |
| `GET` | `/api/v1/campaigns/{campaign}` | `campaigns.view` |
| `PATCH` | `/api/v1/campaigns/{campaign}` | `campaigns.manage` |
| `DELETE` | `/api/v1/campaigns/{campaign}` | `campaigns.manage` |

Una campaña puede recibir `branch_ids` al crearla o actualizarla. La API valida que las sucursales existan y que estén dentro del ámbito del usuario, salvo para `super_admin`.

Estados válidos: `draft`, `open`, `closed` y `cancelled`.

La documentación interactiva está disponible en `http://localhost:8000/api/documentation`.
