# Campos de formulario y archivos

## Grupos

- `required_base`: `product`, `sender_name`, `sender_phone`, `recipient_name`, `recipient_phone`, `district` y `location`.
- `optional_base`: `address`, `delivery_date`, `delivery_time`, `dedication`, `adicional` y `photo`.
- `custom`: campos definidos por el administrador.

Los campos `required_base` no pueden deshabilitarse ni marcarse como opcionales. `delivery_time` pertenece a `optional_base` y puede configurarse por formulario.

## Archivos

Los campos `photo` y custom `file` aceptan imágenes `jpg`, `jpeg`, `png` y `webp`, con un máximo configurable mediante `FORMS_FILE_MAX_SIZE_KB` (5 MB por defecto). Los binarios se guardan en el disco privado configurado por `FORMS_FILE_DISK` y sus metadatos en `form_submission_files`.

El detalle del pedido devuelve nombre, MIME, tamaño y un enlace autenticado. La ruta física nunca se expone.

```http
GET    /api/v1/form-submission-files/{file}
POST   /api/v1/orders/{order}/files/{file}
DELETE /api/v1/form-submission-files/{file}
```

El reemplazo y la eliminación solo están permitidos mientras el pedido no esté `delivered` ni `cancelled` y el usuario tenga acceso a la campaña y sucursal.
