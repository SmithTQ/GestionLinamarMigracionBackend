# Formularios por campaña

## Plantillas

`GET /api/v1/form-templates` devuelve la plantilla base y sus campos disponibles. La plantilla inicial `campaign-order-v1` contiene producto, remitente, destinatario, distrito, mapa, dirección, fecha, horario y dedicatoria.

## Administración

Requiere `forms.view` para consultar y `forms.manage` para modificar:

- `GET /api/v1/campaign-forms`
- `POST /api/v1/campaign-forms`
- `GET /api/v1/campaign-forms/{form}`
- `PATCH /api/v1/campaign-forms/{form}`
- `POST /api/v1/campaign-forms/{form}/publish`
- `POST /api/v1/campaign-forms/{form}/close`

Cada formulario está vinculado a una campaña y sucursal. Se crea como `draft`, no puede editarse mientras está publicado y solo puede publicarse si la campaña está abierta.

La respuesta administrativa incluye `public_key`, que será utilizado en la siguiente etapa para consultar el formulario desde la API pública. Los IDs y permisos administrativos no se expondrán en ese flujo.

## Siguiente etapa

La consulta pública por `public_key` y el envío protegido se implementarán después de completar la validación de campos dinámicos, productos, distritos y coordenadas. El envío generará directamente un pedido con idempotencia y límite de solicitudes.
