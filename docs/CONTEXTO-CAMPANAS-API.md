# Contexto de campana y alcance de datos

## Objetivo

Las campanas son el limite funcional para operar formularios, productos,
pedidos, invitaciones y rutas. Los catalogos maestros, como productos,
distritos y plantillas, permanecen globales; su configuracion de uso se
relaciona con cada campana.

## Campanas disponibles

`GET /api/v1/campaigns/available`

Requiere Bearer token y `campaigns.view`. Un `super_admin` recibe las campanas
activas del sistema. Los demas usuarios reciben solo campanas con una asignacion
activa en `campaign_user`.

Cada elemento incluye `form_status` (`published`, `draft`, `closed` o `null`) y
`has_published_form` (booleano). Si existen varios formularios, el estado aplica
la prioridad `published`, `draft`, `closed`.

```json
{
  "codigo": 200,
  "datos": [
    {
      "id": 12,
      "code": "CAMP-001",
      "name": "Campana ejemplo",
      "status": "open",
      "has_published_form": true,
      "form_status": "published"
    }
  ]
}
```

## Asignacion de usuarios

| Metodo | Ruta | Permiso |
| --- | --- | --- |
| `GET` | `/api/v1/campaigns/{campaign}/users` | `campaigns.users.view` |
| `POST` | `/api/v1/campaigns/{campaign}/users` | `campaigns.users.manage` |
| `DELETE` | `/api/v1/campaigns/{campaign}/users/{user}` | `campaigns.users.manage` |

El `POST` recibe `user_id` y opcionalmente `is_active`. El `DELETE` desactiva la
asignacion, no elimina el historico. Solo una asignacion activa habilita el
acceso del usuario a la campana.

## Flujo interno

```text
GET  /api/v1/internal/forms/{publicKey}
POST /api/v1/internal/forms/{publicKey}/submissions
```

Ambos requieren autenticacion y respetan el alcance de la campana. El registro
interno requiere `orders.manage`, conserva la idempotencia mediante
`submission_key` y usa las mismas validaciones del formulario.

## Invitaciones publicas

```text
GET  /api/v1/public/invitations/{token}
POST /api/v1/public/invitations/{token}/submissions
```

La creacion del pedido y el cambio de invitacion a `used` ocurren en una
transaccion con bloqueo de fila. Una segunda solicitud concurrente recibe `409`.

El endpoint generico `POST /api/v1/public/forms/{publicKey}/submissions` se
conserva temporalmente por compatibilidad con clientes existentes. El frontend
administrativo debe usar el flujo `/internal/forms`.

## Seguridad y base de datos

- `campaign_user.is_active` revoca inmediatamente el acceso sin borrar historial.
- Formularios, productos de campana, invitaciones, pedidos y rutas respetan el
  alcance de campana.
- La migracion de contexto es aditiva: agrega asignaciones activas, timestamps,
  relacion de invitacion con pedido y permisos de usuarios de campana.
- No se ejecutaron migraciones contra la base de datos real.
