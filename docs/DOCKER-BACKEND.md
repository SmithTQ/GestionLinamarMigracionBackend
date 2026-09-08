# Backend Linamar con Docker

## Requisitos

- Docker Desktop iniciado con el motor Linux activo.
- Puertos disponibles: `8000`, `3307`, `6380`, `8025`, `1025` y `8080`.

No es necesario instalar XAMPP, Apache, MySQL ni Redis en Windows. Todos esos servicios se ejecutan dentro de Docker.

## Primer arranque

Desde esta carpeta:

```powershell
docker compose up -d --build
docker compose exec laravel.test php artisan migrate --force
```

La aplicación queda disponible en `http://localhost:8000`. phpMyAdmin queda en `http://localhost:8080` y Mailpit, para revisar correos durante desarrollo, queda en `http://localhost:8025`.

## Swagger / OpenAPI

La interfaz interactiva está disponible en `http://localhost:8000/api/documentation`.
El documento OpenAPI en JSON está disponible en `http://localhost:8000/docs`.

Después de agregar o modificar endpoints y su documentación, regenerar el contrato con:

```powershell
docker compose exec laravel.test php artisan l5-swagger:generate
```

La documentación no debe publicarse sin protección en producción. En ese entorno se debe desactivar `L5_SWAGGER_GENERATE_ALWAYS` y restringir las rutas de documentación mediante middleware o la configuración del servidor.

## Comandos habituales

```powershell
docker compose ps
docker compose logs -f laravel.test
docker compose exec laravel.test php artisan test
docker compose exec laravel.test php artisan about
docker compose down
```

`docker compose down` detiene y elimina los contenedores, pero conserva los datos de MySQL y Redis en volúmenes Docker.

## Reinicio desde cero

Esta operación elimina los datos locales de la base de datos y solo debe usarse cuando se quiera reconstruir el entorno de desarrollo:

```powershell
docker compose down -v
docker compose up -d --build
docker compose exec laravel.test php artisan migrate --force
```

## Configuración

Copiar `.env.example` a `.env` si todavía no existe y generar la clave con:

```powershell
docker compose exec laravel.test php artisan key:generate
```

Los secretos reales no deben versionarse. En el entorno local se mantienen en `.env`, que está excluido por `.gitignore`.

## Puertos

| Servicio | Dirección local |
| --- | --- |
| Laravel/Apache | `http://localhost:8000` |
| MySQL | `localhost:3307` |
| phpMyAdmin | `http://localhost:8080` |
| Redis | `localhost:6380` |
| Mailpit | `http://localhost:8025` |
