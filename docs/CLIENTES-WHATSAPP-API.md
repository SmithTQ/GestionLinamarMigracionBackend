# Clientes e invitaciones por WhatsApp Web

## Generar invitación

Requiere `forms.manage`:

`POST /api/v1/customer-invitations`

```json
{
  "form_id": 1,
  "full_name": "Cliente Uno",
  "whatsapp_number": "+51 987 654 321",
  "expires_at": "2026-10-01T23:59:59Z"
}
```

La respuesta contiene `whatsapp_url`, que el frontend puede abrir. El enlace usa `wa.me`; el administrador debe confirmar manualmente el envío en WhatsApp Web.

## Gestión administrativa

- `GET /api/v1/customers?search=987`
- `GET /api/v1/customers/{customer}`
- `GET /api/v1/customer-invitations?form_id=1`
- `POST /api/v1/customer-invitations/{invitation}/revoke`

Las invitaciones usadas no pueden reactivarse. Las invitaciones pendientes o expiradas pueden regenerarse conservando el mismo cliente y formulario.

## Uso único

El token se vincula a cliente y formulario. Cuando se registra el primer pedido, la invitación queda `used` y no puede reutilizarse. La misma persona puede recibir otra invitación para una campaña diferente.

El número se normaliza y se guarda como dato del cliente. Los pedidos creados por invitación quedan relacionados mediante `customer_id`.
