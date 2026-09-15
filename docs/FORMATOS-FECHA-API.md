# Formatos de fecha en respuestas

Las respuestas de los endpoints exponen las fechas en formato legible para usuarios:

- Fecha: `DD/MM/YYYY`, por ejemplo `13/09/2026`.
- Fecha y hora: `DD/MM/YYYY HH:mm`, por ejemplo `13/09/2026 18:30`.

Los campos técnicos conservan una versión ISO adicional con sufijo `_iso`, por ejemplo `created_at_iso`, `delivery_date_iso` o `published_at_iso`, para integraciones y ordenamientos en frontend.

Esto aplica a pedidos, campañas, formularios, sucursales, usuarios, importaciones y rutas.
