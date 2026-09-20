# Prefill de invitaciones publicas

El endpoint:

```http
GET /api/v1/public/invitations/{token}
```

devuelve la misma informacion publica del formulario y agrega los datos minimos del cliente:

```json
{
  "prefill": {
    "sender_name": "Nombre del cliente",
    "sender_phone": "999999999"
  }
}
```

Los valores precargan los datos del remitente y provienen de `customer.full_name` y `customer.whatsapp_number`. No se exponen email, notes, IDs internos ni datos administrativos.

El token debe existir, estar pendiente y no encontrarse expirado. Ademas, el formulario debe estar publicado y su campana debe estar abierta. Una invitacion usada o expirada responde HTTP 404, conforme a la politica publica actual.

Los valores de `prefill` son editables por defecto. El endpoint no agrega `prefill_locked`.
