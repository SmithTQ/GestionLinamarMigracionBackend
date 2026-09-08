# Distritos y ubicación

## Catálogo

Requiere `branches.view` para consultar y `branches.manage` para administrar:

- `GET /api/v1/districts?search=Miraflores&department=Lima`
- `POST /api/v1/districts`
- `GET /api/v1/districts/{district}`
- `PATCH /api/v1/districts/{district}`
- `DELETE /api/v1/districts/{district}`

Un distrito desactivado no aparece en el listado público del catálogo y se conserva mediante borrado lógico.

## Campañas

Al crear o actualizar una campaña se puede enviar `district_ids`. Esto define las zonas permitidas para pedidos de esa campaña:

```json
{
  "district_ids": [1, 2]
}
```

## Pedidos

El campo textual `district` se conserva para compatibilidad con Google Sheets. Los nuevos formularios pueden enviar además:

```json
{
  "district_id": 1,
  "latitude": -12.1211,
  "longitude": -77.0302,
  "location_accuracy": 12.5
}
```

El backend valida que `district_id` esté asignado a la campaña. Las coordenadas tienen límites geográficos y son opcionales mientras se mantiene el flujo de importación legado.
