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

El listado de rutas incluye `active_orders_count`, que cuenta únicamente las
relaciones activas de `route_order`. Las relaciones históricas o retiradas no
se contabilizan.

## Nuevos endpoints de planificacion

| Metodo | Ruta | Proposito |
| --- | --- | --- |
| `GET` | `/api/v1/routes/eligible-orders` | Pedidos elegibles de una campaña |
| `GET` | `/api/v1/routes/map-orders` | Pedidos ubicables y estado de asignación para el mapa |
| `POST` | `/api/v1/routes/generate` | Generar ruta atomica desde pedidos seleccionados |
| `PUT` | `/api/v1/routes/{route}/stops` | Agregar, retirar o reordenar paradas |

`eligible-orders` requiere únicamente `campaign_id`. El backend obtiene la
sucursal desde la campaña y solo devuelve pedidos de esa campaña y sucursal,
activos, en estado `pending` o `validated`, con coordenadas y sin otra ruta
activa. Admite búsqueda, fecha, distrito, paginación y ordenamiento.

Si la campaña no tiene sucursal configurada, responde `422`.

`routes/generate` recibe `stops` con `order_id` y `sort_order`. La ruta, sus
paradas y el cambio de estado a `planned` se guardan en una sola transaccion.
`request_key` evita duplicados por reintentos. El codigo se genera en backend.
No recibe `branch_id`: la sucursal se resuelve desde `campaign_id` y se persiste
internamente en `delivery_routes.branch_id`. Los pedidos seleccionados deben
pertenecer a la misma campaña y sucursal.

La sucursal debe tener `address`, `latitude` y `longitude`. El backend persiste
`navigation_url` como enlace de Google Maps con el origen de la sucursal y las
paradas ordenadas, sin consumir Google Directions API.

`routes/{route}/stops` solo permite cambios antes de `dispatched`, `completed`
o `cancelled` y recalcula el enlace de navegacion.

## Mapa de planificación

`GET /api/v1/routes/map-orders` es independiente de `eligible-orders` y no lo
reemplaza. Requiere `campaign_id` y devuelve todos los pedidos activos y
ubicables de la campaña, incluidos los que ya tienen una asociación activa en
`route_order`.

Admite `page`, `per_page` (máximo 100), `search`, `delivery_date` en formato
`YYYY-MM-DD` y `district_id`. La sucursal no se recibe desde frontend: se
obtiene de `campaigns.branch_id` y se valida mediante el ámbito operativo del
usuario autenticado.

Los pedidos asignados incluyen `active_route` con `id`, `code`, `name`, `status`
y `courier_id`. Los pedidos pendientes tienen `active_route: null`.

La respuesta incluye un resumen calculado sobre todo el resultado filtrado, no
solo sobre la página actual:

```json
{
  "resumen": {
    "total": 25,
    "pending": 18,
    "assigned": 7
  }
}
```

## Estados de ruta

```text
draft -> planned -> assigned -> dispatched -> completed
  \-> cancelled    \-> cancelled
```

Una ruta solo puede despacharse si tiene motorizado y al menos un pedido activo. Los pedidos deben pertenecer a la misma campaña y sucursal de la ruta, y no pueden estar en otra ruta activa.

Las relaciones `route_order` conservan la fecha de asignación y permiten mantener historial cuando se implemente la desasignación.
