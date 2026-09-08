# API de pedidos

Todas las rutas requieren `Authorization: Bearer <token>` y el permiso correspondiente.

## Endpoints

| Método | Ruta | Permiso |
| --- | --- | --- |
| `GET` | `/api/v1/orders` | `orders.view` |
| `POST` | `/api/v1/orders` | `orders.manage` |
| `GET` | `/api/v1/orders/{order}` | `orders.view` |
| `PATCH` | `/api/v1/orders/{order}` | `orders.manage` |
| `PATCH` | `/api/v1/orders/{order}/status` | `orders.manage` |
| `DELETE` | `/api/v1/orders/{order}` | `orders.manage` |

## Filtros

`GET /api/v1/orders` acepta `campaign_id`, `branch_id`, `status`, `district`, `delivery_date`, `per_page` y `page`.

## Idempotencia

Cuando se informa `external_source` y `external_key`, la combinación con `campaign_id` identifica el pedido importado. Repetir la misma solicitud devuelve el pedido existente sin duplicarlo.

## Estados

```text
pending -> validated -> planned -> assigned -> in_transit -> delivered
                                      \-> failed -> in_transit
```

También se permite cancelar desde `pending`, `validated`, `planned` y `assigned`. Las transiciones inválidas devuelven `422`.

Los pedidos solo pueden registrarse o modificarse mientras la campaña esté en estado `open`.
