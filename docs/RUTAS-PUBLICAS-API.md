# Enlaces publicos de rutas

## Endpoints

| Metodo | Ruta | Permiso |
| --- | --- | --- |
| `POST` | `/api/v1/routes/{route}/access-token` | `routes.manage` |
| `POST` | `/api/v1/routes/{route}/courier-invitations` | `routes.manage` |
| `DELETE` | `/api/v1/routes/{route}/access-token` | `routes.manage` |
| `GET` | `/api/v1/public/routes/{token}` | Publico con token |

El token se muestra una sola vez al crearlo y se guarda unicamente como hash
SHA-256. Tiene una vigencia predeterminada de 7 dias, puede revocarse y esta
protegido por rate limiting.

La respuesta publica esta limitada a una sola ruta y sus pedidos activos. No
incluye IDs internos, campana, sucursal, usuario ni datos administrativos.
Incluye solamente la informacion operativa que el motorizado necesita: codigo,
nombre de ruta, navegacion, destinatario, telefono, direccion, coordenadas,
fecha, horario y orden de la parada.

## Invitación de motorizado

`POST /api/v1/routes/{route}/courier-invitations` recibe `name`,
`whatsapp_number` y un `expires_at` opcional. Normaliza el número eliminando
espacios y símbolos, reutiliza un motorizado activo del mismo número, lo
asocia a la sucursal de la ruta y cambia la ruta a `assigned`.

La operación es transaccional. Los tokens activos anteriores se revocan y el
nuevo token se guarda únicamente como hash SHA-256. Si no se informa el
vencimiento, se utiliza una vigencia de 7 días.

La respuesta contiene una URL para el frontend, no para la API:
`{FRONTEND_URL}/ruta/{token}`. También contiene `whatsapp_url`, cuyo mensaje
incluye el nombre del motorizado, el código y nombre de la ruta y el enlace
público.

## Confirmacion publica de entrega

`GET /api/v1/public/routes/{token}` devuelve cada parada con su pedido, producto,
remitente, destinatario, ubicacion, referencia de entrega y evidencia registrada.
La evidencia es `null` mientras no se confirme la entrega.

El motorizado confirma una parada mediante:

```text
POST /api/v1/public/routes/{token}/orders/{order}/delivery-confirmation
Content-Type: multipart/form-data
```

Campos:

- `evidence`: imagen obligatoria `jpg`, `jpeg`, `png` o `webp`, máximo 5 MB.
- `note`: nota opcional de hasta 1000 caracteres.

El token debe estar vigente y no revocado. El pedido debe pertenecer a una
parada activa de la ruta y no estar cancelado. La operación guarda la imagen
en almacenamiento privado, registra la evidencia, cambia el pedido a
`delivered` y es idempotente: reintentar una entrega ya confirmada no crea
otra evidencia.

La imagen se consulta mediante la URL `delivery_evidence.url` usando el mismo
token público:

```text
GET /api/v1/public/routes/{token}/orders/{order}/delivery-evidence
```

## Contexto operativo

`GET /api/v1/auth/me` incluye `operational_context` con el modo
`super_admin`, `branch_admin` o `campaign_dispatcher`, sus sucursales y
campanas visibles, y valores predeterminados.

Los endpoints `GET /api/v1/campaigns/available`, `GET /api/v1/products` y
`GET /api/v1/district-lists` aceptan `branch_id`. Para usuarios operativos es
obligatorio y debe pertenecer a una sucursal asignada. Las campanas disponibles
para el contexto operativo se limitan al estado `open`.
