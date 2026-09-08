# API pública de formularios

La API pública no requiere autenticación administrativa. Solo funciona con el `public_key` de un formulario publicado y de una campaña abierta:

- `GET /api/v1/public/forms/{publicKey}`
- `POST /api/v1/public/forms/{publicKey}/submissions`

La consulta devuelve únicamente título, campos habilitados, productos disponibles y distritos permitidos. No expone usuarios, roles ni pedidos.

El envío usa `product_sku`, `district_code`, coordenadas y una `submission_key` generada por el frontend. La clave es idempotente por formulario: reenviar la misma clave no crea otro pedido. El backend crea el pedido con origen `public_form`, conserva el precio y nombre del producto, valida campaña, sucursal, producto, distrito y coordenadas, y aplica un límite de 20 solicitudes por minuto y dirección IP/formulario.
