# Catálogo de productos

## Estructura

- **Categoría:** agrupación principal, por ejemplo Flores o Regalos.
- **Subcategoría:** clasificación dentro de una categoría, por ejemplo Ramos o Arreglos.
- **Producto:** SKU, nombre, descripción, unidad, precio base, imagen y estado.
- **Producto de campaña:** disponibilidad, precio específico, orden visual y cantidad máxima para una campaña.

## Endpoints

Requieren `products.view` para consultar y `products.manage` para administrar:

- `GET /api/v1/product-categories`
- `POST /api/v1/product-categories`
- `PATCH /api/v1/product-categories/{category}`
- `DELETE /api/v1/product-categories/{category}`
- `GET /api/v1/product-subcategories?category_id=1`
- `POST /api/v1/product-subcategories`
- `PATCH /api/v1/product-subcategories/{subcategory}`
- `DELETE /api/v1/product-subcategories/{subcategory}`
- `GET /api/v1/products?search=ramo&subcategory_id=1`
- `POST /api/v1/products`
- `PATCH /api/v1/products/{product}`
- `DELETE /api/v1/products/{product}`

## Creación y actualización de productos

Los campos `sku` y `slug` son opcionales al crear un producto. Si no se envían, el backend genera el SKU y el slug automáticamente. El SKU no se modifica mediante la actualización normal; el slug se conserva cuando cambia el nombre y no se envía un slug nuevo.

La creación y actualización aceptan `multipart/form-data` con un campo `image` opcional. Se permiten JPG, JPEG, PNG y WebP, con un máximo de 5 MB y dimensiones entre 100 y 4000 píxeles. La imagen original y un thumbnail WebP de hasta 480 px se almacenan en el disco configurado por `PRODUCT_IMAGE_DISK`. La respuesta devuelve `image_url` e `image_thumbnail_url`.

Para actualizar un producto con archivo desde PHP, se recomienda enviar `POST /api/v1/products/{product}` con `_method=PATCH` dentro del multipart, ya que PHP no procesa multipart de forma uniforme en peticiones PATCH directas.

Las imágenes anteriores se eliminan al reemplazarlas cuando fueron gestionadas por el backend. Al desactivar un producto, la imagen se conserva.

## Productos por campaña

Consultar los productos disponibles:

`GET /api/v1/campaigns/{campaign}/products`

Asignar o reemplazar el listado de la campaña:

```json
{
  "products": [
    { "product_id": 1, "price": 64.90, "sort_order": 1, "max_quantity": 3 }
  ]
}
```

Los productos se mantienen como catálogo global y la campaña controla cuáles se muestran y con qué precio. Los pedidos guardan `product_id`, `product_price` y una copia de `product_name` para conservar el histórico.
