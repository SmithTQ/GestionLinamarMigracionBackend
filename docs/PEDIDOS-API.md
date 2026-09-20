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

`delivery_date` es opcional y acepta únicamente `YYYY-MM-DD`, `DD/MM/YYYY` o `DD-MM-YYYY`. El backend normaliza cualquiera de esos formatos a `YYYY-MM-DD` antes de filtrar. Una fecha inexistente o un formato no documentado devuelve `422`; un valor vacío no aplica el filtro.

La misma normalización se aplica al crear o actualizar pedidos y a los envíos de formularios públicos. Los valores vacíos se persisten como `null`, nunca como cadena vacía.

`delivery_time` es un campo de texto opcional de hasta 255 caracteres. Puede contener valores como `10:00`, `Por la tarde` o `Horario coordinado`; ya no se restringe al formato de hora `H:i`.

`delivery_reference` es un texto obligatorio de hasta 1000 caracteres para nuevos pedidos. Describe una referencia física del lugar de entrega y se conserva en la respuesta del pedido. Los registros históricos pueden mantenerlo vacío o `NULL`.

## Evidencia interna de entrega

Los endpoints autenticados `GET /api/v1/orders` y
`GET /api/v1/orders/{order}` incluyen `delivery_evidence`. El valor es `null`
si el pedido no tiene evidencia; cuando existe contiene únicamente:

```json
{
  "id": 10,
  "delivered_at": "20/09/2026 14:30",
  "delivered_at_iso": "2026-09-20T19:30:00.000000Z",
  "delivered_by_courier_id": 3,
  "delivered_by_courier_name": "Juan Perez"
}
```

Para descargar la imagen se utiliza el endpoint autenticado:

```text
GET /api/v1/orders/{order}/delivery-evidence
```

Requiere `orders.view`, respeta el alcance operativo del usuario y devuelve la
imagen desde almacenamiento privado con caché privada. Nunca expone `path`,
`disk` ni rutas internas de almacenamiento.

## Idempotencia

Cuando se informa `external_source` y `external_key`, la combinación con `campaign_id` identifica el pedido importado. Repetir la misma solicitud devuelve el pedido existente sin duplicarlo.

## Estados

```text
pending -> validated -> planned -> assigned -> in_transit -> delivered
                                      \-> failed -> in_transit
```

También se permite cancelar desde `pending`, `validated`, `planned` y `assigned`. Las transiciones inválidas devuelven `422`.

Los pedidos solo pueden registrarse o modificarse mientras la campaña esté en estado `open`.
