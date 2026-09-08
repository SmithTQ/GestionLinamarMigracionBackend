# API de despacho

Todas las rutas requieren `Authorization: Bearer <token>` y el permiso `routes.manage`.

## Motorizados

| Método | Ruta | Propósito |
| --- | --- | --- |
| `GET` | `/api/v1/couriers` | Listar motorizados visibles |
| `POST` | `/api/v1/couriers` | Crear motorizado |
| `PATCH` | `/api/v1/couriers/{courier}` | Actualizar disponibilidad y ámbito |

## Rutas

| Método | Ruta | Propósito |
| --- | --- | --- |
| `GET` | `/api/v1/routes` | Listar rutas |
| `POST` | `/api/v1/routes` | Crear ruta |
| `GET` | `/api/v1/routes/{route}` | Consultar ruta con pedidos |
| `PATCH` | `/api/v1/routes/{route}` | Actualizar datos de planificación |
| `PATCH` | `/api/v1/routes/{route}/courier` | Asignar motorizado |
| `POST` | `/api/v1/routes/{route}/orders` | Agregar pedidos |
| `PATCH` | `/api/v1/routes/{route}/status` | Cambiar estado |

## Estados de ruta

```text
draft -> planned -> assigned -> dispatched -> completed
  \-> cancelled    \-> cancelled
```

Una ruta solo puede despacharse si tiene motorizado y al menos un pedido activo. Los pedidos deben pertenecer a la misma campaña y sucursal de la ruta, y no pueden estar en otra ruta activa.

Las relaciones `route_order` conservan la fecha de asignación y permiten mantener historial cuando se implemente la desasignación.
