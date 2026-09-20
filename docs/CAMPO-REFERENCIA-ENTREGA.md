# Campo `delivery_reference`

Los formularios de pedidos incluyen el campo base obligatorio `delivery_reference`.

- Etiqueta: `Referencia de entrega`.
- Tipo: `textarea`.
- Grupo: `required_base`.
- Longitud máxima: 1000 caracteres.
- No puede deshabilitarse ni convertirse en opcional.
- Debe describir una referencia física visible del punto de entrega.

El valor se persiste en `orders.delivery_reference` y también se conserva dentro de `form_submissions.payload`. Los pedidos históricos pueden tener `NULL`; los nuevos pedidos deben enviarlo mediante los formularios públicos, invitaciones o registro interno.
