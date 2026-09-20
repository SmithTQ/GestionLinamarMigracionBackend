# Cancelación de rutas

## Endpoint

```http
PATCH /api/v1/routes/{route}/status
```

Payload:

```json
{
  "status": "cancelled"
}
```

La operación conserva la ruta y su información histórica. Solo pueden
cancelarse rutas en estado `draft`, `planned` o `assigned`. Las rutas
`dispatched`, `completed` o `cancelled` responden `422`.

Al cancelar una ruta:

- La ruta pasa a estado `cancelled` y registra `cancelled_at`.
- Los registros de `route_order` no se eliminan; se marcan con
  `is_active = false` y `removed_at`.
- Los pedidos activos que no pertenezcan a otra ruta activa regresan a estado
  `pending` y vuelven a aparecer en `GET /api/v1/routes/eligible-orders`.
- Los pedidos cancelados, entregados o inactivos no se modifican.
- La operación completa se ejecuta dentro de una transacción y revierte todos
  los cambios si ocurre un error.

La autorización y el ámbito de campaña/sucursal se mantienen sin cambios.
