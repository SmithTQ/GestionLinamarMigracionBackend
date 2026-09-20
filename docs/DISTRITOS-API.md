# Distritos y ubicación

## Catálogo

Requiere `districts.view` para consultar y `districts.manage` para administrar:

- `GET /api/v1/districts?search=Miraflores&department=Lima`
- `GET /api/v1/districts/departments`: listado de departamentos con `code` y `name`.
- `GET /api/v1/districts/provinces?department_code=15`: listado de provincias del departamento indicado.
- `POST /api/v1/districts`
- `GET /api/v1/districts/{district}`
- `PATCH /api/v1/districts/{district}`
- `DELETE /api/v1/districts/{district}`

Un distrito desactivado no aparece en el listado público del catálogo y se conserva mediante borrado lógico.

## Campañas

Al crear o actualizar una campaña se puede enviar `district_list_ids`. Esto relaciona la campaña con uno o varios listados de cobertura reutilizables:

```json
{
  "district_list_ids": [1, 2]
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
