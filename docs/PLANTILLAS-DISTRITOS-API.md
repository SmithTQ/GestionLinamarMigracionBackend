# Plantillas de distritos

El catalogo `districts` contiene el listado maestro de distritos y sus codigos UBIGEO oficiales. Las plantillas permiten reutilizar subconjuntos de ese catalogo en distintos formularios.

## Permisos

- `district_lists.view`: consultar plantillas.
- `district_lists.manage`: crear, actualizar y desactivar plantillas.

## Endpoints

- `GET /api/v1/district-lists`: lista paginada. Soporta `search`, `per_page`, `sort` y `direction`.
- `POST /api/v1/district-lists`: crea una plantilla.
- `GET /api/v1/district-lists/{id}`: consulta una plantilla con sus distritos.
- `PATCH /api/v1/district-lists/{id}`: actualiza datos y distritos de una plantilla.
- `DELETE /api/v1/district-lists/{id}`: desactiva la plantilla mediante borrado logico.

### Crear plantilla

```json
{
  "code": "LIMA_METROPOLITANA",
  "name": "Lima Metropolitana",
  "description": "Distritos con cobertura regular.",
  "district_ids": [1301, 1302, 1303]
}
```

Solo se pueden asociar distritos activos del catalogo maestro. El orden del array se conserva en la plantilla.

## Uso en campañas

Los listados se relacionan con la campaña, no directamente con el formulario.

```json
{
  "district_list_ids": [4, 5],
  "title": "Pedidos de campana"
}
```

El detalle de la campaña devuelve `district_lists` y cada listado puede incluir sus distritos. Los formularios heredan la cobertura de su campaña.

Los pedidos administrativos y los formularios públicos solo aceptan distritos activos incluidos en alguno de los listados asociados a la campaña.
