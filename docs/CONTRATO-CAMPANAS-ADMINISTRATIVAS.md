# Contrato administrativo de campañas

El listado `GET /api/v1/campaigns` devuelve las métricas necesarias para la
tabla administrativa sin cargar los pedidos en memoria:

```json
{
  "id": 1,
  "code": "CAMP-001",
  "name": "Campaña de ejemplo",
  "budget": "2500.00",
  "starts_on": "01/09/2026",
  "starts_on_iso": "2026-09-01",
  "ends_on": "30/09/2026",
  "ends_on_iso": "2026-09-30",
  "orders_count": 12,
  "delivered_count": 8,
  "total_obtained": "1250.00",
  "status": "open",
  "form_status": "published",
  "has_published_form": true
}
```

Las métricas consideran únicamente pedidos activos. `delivered_count` y
`total_obtained` consideran pedidos activos con estado `delivered`.

`budget` es opcional y se devuelve como importe con dos decimales. Las campañas
existentes sin presupuesto devuelven `null` hasta que se registre un valor.

El cálculo utiliza `withCount` y `withSum` sobre la consulta paginada, evitando
consultas N+1 y evitando cargar la colección completa de pedidos.
