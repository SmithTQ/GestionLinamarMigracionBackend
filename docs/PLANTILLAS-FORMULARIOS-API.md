# Administración de plantillas y campos

Todos los endpoints requieren autenticación Bearer.

## Permisos

- `forms.view`: consultar plantillas y campos.
- `forms.manage`: crear, editar, ordenar y desactivar plantillas y campos.

## Plantillas

```http
GET    /api/v1/form-templates
POST   /api/v1/form-templates
GET    /api/v1/form-templates/{template}
PATCH  /api/v1/form-templates/{template}
DELETE /api/v1/form-templates/{template}
```

El listado admite `search`, `is_active`, `sort_by`, `sort_dir` y `per_page`. La operación `DELETE` desactiva la plantilla; no la elimina físicamente.

```json
{
  "code": "campaign-order-v2",
  "name": "Formulario de pedidos avanzado",
  "description": "Plantilla con datos adicionales",
  "is_active": true
}
```

El código es único y utiliza formato `kebab-case`. El código no se modifica después de crear la plantilla; para cambios estructurales se recomienda crear una nueva versión.

## Campos

```http
GET    /api/v1/form-templates/{template}/fields
POST   /api/v1/form-templates/{template}/fields
PATCH  /api/v1/form-templates/{template}/fields/{field}
DELETE /api/v1/form-templates/{template}/fields/{field}
```

Tipos permitidos: `text`, `textarea`, `number`, `date`, `phone`, `select`, `file`, `product`, `district`, `map` y `time`.

```json
{
  "key": "invoice_number",
  "label": "Numero de factura",
  "description": "Dato opcional para conciliación.",
  "type": "text",
  "field_group": "custom",
  "is_system": false,
  "validation_rules": {
    "max_length": 50
  },
  "sort_order": 120
}
```

La clave utiliza `snake_case` y es única dentro de la plantilla. Los campos del sistema no pueden eliminarse ni cambiar de clave o tipo. Los campos personalizados se desactivan mediante `DELETE`; no se eliminan físicamente.

Los campos `required_base` obligatorios del sistema son `product`, `sender_name`, `sender_phone`, `recipient_name`, `recipient_phone`, `district` y `location`.

Los campos `optional_base` iniciales son `address`, `delivery_date`, `delivery_time`, `dedication`, `adicional` y `photo`. Pueden habilitarse, deshabilitarse y marcarse como requeridos por formulario.

La etiqueta visible de `dedication` es `Dedicatoria`. La clave técnica se mantiene sin cambios para conservar compatibilidad.

`delivery_time` es texto opcional de hasta 255 caracteres; puede contener una hora, un rango o una descripción como `Por la tarde`.

`photo` es un archivo opcional de imagen. Los campos custom de tipo `file` también se validan como imágenes y sus metadatos se almacenan fuera de `payload`.

## Compatibilidad

La plantilla `campaign-order-v1` continúa siendo compatible. Los formularios existentes conservan sus registros en `campaign_form_fields`. Un campo utilizado por un formulario publicado no puede cambiar estructuralmente de clave o tipo.
