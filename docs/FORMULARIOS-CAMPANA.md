# Formularios personalizados por campaña

## Decisión funcional

El sistema dejará de depender exclusivamente de Google Forms y Google Sheets para registrar pedidos. Cada campaña podrá publicar un formulario propio, basado en una plantilla común y con configuración limitada por campaña.

Google Sheets continuará disponible como mecanismo de importación y compatibilidad durante la transición.

## Alcance previsto

- Crear un formulario a partir de una plantilla base.
- Activar o cerrar el formulario según el estado de la campaña.
- Configurar título, descripción, imagen y textos de la campaña.
- Seleccionar los productos disponibles para la campaña.
- Configurar precio, presentación, imagen, orden y disponibilidad del producto.
- Habilitar u ocultar campos permitidos por la plantilla.
- Marcar campos como obligatorios u opcionales.

El endpoint `GET /api/v1/campaigns` incluye un resumen del formulario asociado para evitar consultas individuales desde el frontend:

```json
{
  "id": 12,
  "code": "CAMP-001",
  "name": "Campaña ejemplo",
  "form_status": "published",
  "has_published_form": true
}
```

`form_status` puede ser `published`, `draft`, `closed` o `null`. Si existen varios formularios, se aplica la prioridad `published > draft > closed`.
- Configurar el catálogo de distritos habilitados para despacho.
- Seleccionar la ubicación de entrega en un mapa.
- Guardar latitud, longitud y dirección resultante.
- Validar que la ubicación pertenezca a un distrito habilitado.
- Crear el pedido directamente en la campaña y sucursal correspondiente.
- Generar una clave pública de formulario sin exponer IDs internos ni permisos administrativos.

## Plantilla base prevista

Campos iniciales del formulario:

- Producto.
- Nombre del remitente.
- Teléfono del remitente.
- Nombre del destinatario.
- Teléfono del destinatario.
- Distrito de entrega.
- Ubicación en mapa.
- Dirección referencial obtenida o confirmada desde el mapa.
- Fecha de entrega.
- Rango horario.
- Dedicatoria u observaciones.

La campaña podrá activar o desactivar campos configurables, pero no podrá modificar arbitrariamente las reglas de seguridad, el vínculo de campaña/sucursal ni los campos técnicos del pedido.

## Diseño de dominio futuro

Se prevén estas entidades:

| Entidad | Propósito |
| --- | --- |
| `form_templates` | Plantillas base reutilizables |
| `campaign_forms` | Formulario publicado para una campaña |
| `form_fields` | Campos disponibles y configurables |
| `campaign_form_fields` | Configuración de campos por campaña |
| `products` | Catálogo general de productos |
| `campaign_products` | Productos, precio y disponibilidad por campaña |
| `districts` | Catálogo de distritos habilitables |
| `district_lists` | Listados de cobertura reutilizables relacionados con la campaña |
| `form_submissions` | Registro de envíos públicos e idempotencia |

Los pedidos conservarán la información operativa resultante del formulario y una referencia al envío original, sin depender de que el formulario siga publicado.

## Ubicación de entrega

El pedido deberá conservar:

- `district_id` cuando el distrito haya sido identificado.
- `address` como dirección legible.
- `latitude` y `longitude` como coordenadas validadas.
- `location_accuracy` cuando el proveedor de mapas la entregue.

Las coordenadas no sustituyen la dirección escrita o confirmada. El backend validará límites geográficos, distrito habilitado y datos mínimos antes de crear el pedido.

## Seguridad del formulario público

- El formulario público solo podrá crear pedidos para la campaña publicada.
- No podrá consultar usuarios, roles, pedidos ni datos administrativos.
- El token público será aleatorio, revocable y no contendrá IDs sensibles.
- Se aplicarán rate limiting, captcha o protección equivalente según el riesgo.
- Se evitarán pedidos duplicados mediante una clave de envío y huella de solicitud.
- La API pública no expondrá credenciales de Google ni secretos del backend.

## Orden de implementación posterior

1. Completar la migración de la operación actual y mantener Google Sheets.
2. Crear productos y distritos como catálogos administrables.
3. Crear plantillas y configuración de formularios por campaña.
4. Crear endpoint público de consulta del formulario.
5. Crear endpoint público de envío con validación de mapa.
6. Crear pedidos directamente desde los envíos.
7. Integrar el formulario configurable en Angular 21.
8. Retirar Google Forms solo después de validar la equivalencia funcional.
