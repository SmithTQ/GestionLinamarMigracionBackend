# Despliegue en Hostinger

Esta guia despliega el backend Laravel en Hostinger Business Web Hosting sin Docker. Docker se mantiene para desarrollo local.

## Arquitectura de carpetas

Mantener el proyecto completo fuera del document root:

```text
/home/u467203287/apps/linamar-backend/                 # Proyecto Laravel completo
/home/u467203287/domains/nicolaetincoquispe.com/
    public_html/api/                                   # Document root del subdominio
```

La opcion preferida es configurar el subdominio directamente a:

```text
/home/u467203287/apps/linamar-backend/public
```

Si Hostinger obliga a usar `public_html/api`, copiar alli solamente el contenido de `public/` y ajustar las rutas de `public/index.php` para apuntar al proyecto completo. Nunca publicar `.env`, `app/`, `config/`, `database/` ni `storage/app/private/`.

## Requisitos del hosting

- PHP 8.4.1 o superior. El lockfile actual no es compatible con PHP 8.3.
- Extensiones `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `ctype`, `xml`, `json` y `tokenizer`.
- SSH habilitado, recomendado para Composer y Artisan.
- Base de datos MariaDB/MySQL creada en hPanel.
- Subdominio de API apuntando al directorio publico.

## Preparar el repositorio

Trabajar en la rama aprobada para el ambiente. El flujo recomendado es:

```text
dev -> qa -> main
```

Antes de publicar:

```bash
composer validate --strict
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan test
vendor/bin/pint --test
phpstan analyse --memory-limit=1G
```

No subir `.env`, `vendor/`, `node_modules/`, `database/backups/`, logs, caches ni archivos subidos. El repositorio ya los excluye mediante `.gitignore`.

## Desplegar desde GitHub

En hPanel, abrir **Websites > Manage > Git**, seleccionar el repositorio y la rama aprobada. Usar una ruta de instalacion fuera de `public_html`, por ejemplo:

```text
/home/u467203287/apps/linamar-backend
```

Si el despliegue Git del plan no permite esa ruta, subir un ZIP del repositorio mediante File Manager o FTP y extraerlo fuera del document root.

## Instalar dependencias

Con SSH:

```bash
cd /home/u467203287/apps/linamar-backend
composer2 install --no-dev --prefer-dist --optimize-autoloader
```

Composer debe ejecutarse en el mismo proyecto y con PHP 8.3 o superior.

## Configurar `.env`

Copiar `.env.hostinger.example` como `.env` directamente en el servidor y completar los valores reales. Nunca guardar contrasenas, `APP_KEY` ni credenciales de Google en GitHub.

Valores obligatorios de produccion:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.nicolaetincoquispe.com
FRONTEND_URL=https://DOMINIO_REAL_DEL_FRONTEND
DB_HOST=HOST_REAL_DE_HOSTINGER
DB_DATABASE=BASE_REAL
DB_USERNAME=USUARIO_REAL
DB_PASSWORD=CONTRASENA_REAL
```

Generar una clave solo si la instalacion no tiene una:

```bash
php artisan key:generate --show
```

Si se despliega una copia existente, conservar su `APP_KEY`; cambiarla invalida datos cifrados y sesiones.

## Base de datos

1. Crear la base de datos y usuario en hPanel.
2. Importar en phpMyAdmin el backup aprobado para el ambiente.
3. Verificar que la importacion termino correctamente.
4. Ejecutar solo migraciones pendientes:

```bash
php artisan migrate --force
```

No ejecutar `migrate:fresh`, `migrate:refresh`, `db:wipe` ni seeders en produccion sin una aprobacion expresa y un backup nuevo.

## Cache y almacenamiento

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`storage/app/public` contiene imagenes publicas de productos. `storage/app/private` contiene archivos privados, como evidencias de entrega, y no debe exponerse mediante una URL directa.

Las carpetas `storage/` y `bootstrap/cache/` deben tener permisos de escritura para el usuario de PHP. Usualmente se utiliza `775`, según la configuracion del hosting.

## Cron

Si se habilitan tareas programadas, crear en hPanel un Cron Job equivalente a:

```bash
* * * * * /usr/bin/php /home/u467203287/apps/linamar-backend/artisan schedule:run >> /dev/null 2>&1
```

Confirmar en Hostinger la ruta del binario PHP seleccionado.

## Verificacion posterior

Comprobar:

```text
https://api.nicolaetincoquispe.com/up
https://api.nicolaetincoquispe.com/api/documentation
```

Luego probar login, listado de campanas, formularios publicos, pedidos, rutas, imagenes de productos y descarga autorizada de evidencias.

Revisar `storage/logs/laravel.log` si aparece un error, sin mostrarlo publicamente ni activar `APP_DEBUG` en produccion.

## Rollback

Antes de cada migracion:

1. Generar un backup completo de la base de datos.
2. Registrar el commit desplegado.
3. Aplicar la migracion.
4. Validar endpoints criticos.

Si una version falla, volver al commit anterior y restaurar la base de datos solamente con el backup correspondiente y aprobado.
