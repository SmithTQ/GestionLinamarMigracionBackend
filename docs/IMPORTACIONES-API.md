# API de importaciones Google Sheets

## Endpoint

```http
POST /api/v1/imports/google-sheets
Authorization: Bearer <token>
```

Permiso requerido: `imports.create`.

Solicitud:

```json
{
  "campaign_id": 1,
  "branch_id": 1,
  "spreadsheet_id": "id-de-google-sheets",
  "range": "Respuestas de formulario 1!A2:Z"
}
```

## Mapeo compatible

| Índice | Campo de la hoja | Campo del pedido |
| --- | --- | --- |
| `0` | Marca temporal | `external_key` |
| `2` | Detalle/producto | `product_name` |
| `3` | Nombre remitente | `sender_name` |
| `4` | Teléfono remitente | `sender_phone` |
| `5` | Nombre destinatario | `recipient_name` |
| `6` | Teléfono destinatario | `recipient_phone` |
| `7` | Distrito | `district` |
| `8` | Dirección | `address` |
| `9` | Fecha de entrega | `delivery_date` |
| `10` | Dedicatoria | `dedication` |
| `11` | Hora de entrega | `delivery_time` |

La importación se registra en `imports`, informa filas insertadas, duplicadas, inválidas y fallidas, y evita duplicados por campaña, fuente y clave externa.

## Credenciales

Las credenciales nunca se guardan en el repositorio. Configurar en `.env`:

```env
GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/storage/app/private/google-credentials.json
```

El archivo debe existir dentro del contenedor mediante un mecanismo seguro de secretos o un volumen local excluido de Git. Si no existe, la API registra la importación como fallida y responde `502` sin exponer detalles sensibles.
