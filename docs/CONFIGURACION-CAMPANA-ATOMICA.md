# Configuración atómica de campaña

Para guardar el formulario y asignar sus productos en una sola operación se dispone de:

```http
PUT /api/v1/campaigns/{campaign}/configuration
```

Requiere autenticación Bearer y el permiso `forms.manage`.

## Payload

```json
{
  "form": {
    "template_id": 2,
    "title": "Formulario de pedidos",
    "description": "Formulario para clientes",
    "fields": [
      {
        "field_id": 12,
        "is_enabled": true,
        "is_required": false,
        "label": "Numero de factura",
        "config": null,
        "sort_order": 120
      }
    ]
  },
  "products": [
    {
      "product_id": 9,
      "price": 69.90,
      "is_available": true,
      "sort_order": 1,
      "max_quantity": 2
    }
  ]
}
```

`branch_id` puede omitirse; en ese caso se utiliza la sucursal interna predeterminada. `template_id` continúa siendo obligatorio.

## Atomicidad

El backend ejecuta en una única transacción:

1. Resolver y asociar la sucursal interna.
2. Crear o actualizar el formulario en estado `draft`.
3. Sincronizar sus campos.
4. Sincronizar los productos de la campaña.

Si alguna validación o regla falla, se revierte toda la operación. No queda formulario parcial ni asignación parcial de productos.

Los endpoints existentes `POST /api/v1/campaign-forms` y `PUT /api/v1/campaigns/{campaign}/products` se mantienen para compatibilidad, pero no pueden ofrecer atomicidad entre sí cuando se invocan como peticiones independientes.
