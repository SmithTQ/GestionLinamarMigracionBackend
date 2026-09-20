# Fechas de entrega

El campo opcional `delivery_date` se normaliza en backend y se persiste siempre en formato ISO `YYYY-MM-DD`.

## Formatos aceptados

- `YYYY-MM-DD`, por ejemplo `2026-12-31`
- `DD/MM/YYYY`, por ejemplo `31/12/2026`
- `DD-MM-YYYY`, por ejemplo `31-12-2026`

La conversión se realiza antes de la validación y persistencia. Una fecha inexistente, como `31/02/2026`, o un formato no documentado devuelve HTTP `422`.

## Comportamiento por endpoint

La normalización se aplica en:

- `POST /api/v1/orders`
- `PATCH /api/v1/orders/{order}`
- `GET /api/v1/orders?delivery_date=...`
- `POST /api/v1/public/forms/{publicKey}/submissions`
- `POST /api/v1/public/invitations/{token}/submissions`

Si el campo se omite o llega vacío, se trata como `null`. En el listado de pedidos, un valor vacío no aplica el filtro.

## Respuesta y persistencia

Las respuestas exponen `delivery_date` como `YYYY-MM-DD`. La base de datos recibe el valor normalizado; no se almacenan cadenas vacías.

`delivery_time` se maneja independientemente como texto opcional de hasta 255 caracteres. No se transforma ni se limita al formato `H:i`.
