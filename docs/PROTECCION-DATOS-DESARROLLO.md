# Proteccion de datos de desarrollo

## Regla principal

La base `bdgestionlinamarmigracion` es la base de desarrollo compartida y no debe utilizarse para ejecutar pruebas automatizadas.

## Pruebas

PHPUnit se configura para usar SQLite en memoria. Ademas, Laravel aborta el arranque si el entorno `testing` detecta otra conexion o una base distinta de `:memory:`.

Antes de ejecutar pruebas, usar:

```bash
docker compose exec -T laravel.test php artisan test
```

No cambiar `DB_CONNECTION` a `mysql` dentro de `phpunit.xml` ni de `tests/bootstrap.php`.

## Base de desarrollo

Para trabajar con datos reales de desarrollo:

```bash
docker compose up -d
```

No ejecutar en esa base sin una copia previa:

```text
php artisan migrate:fresh
php artisan migrate:fresh --seed
php artisan db:wipe
DROP DATABASE
```

Las migraciones normales (`php artisan migrate`) son incrementales y no deben borrar registros existentes.

## Respaldos y autorizacion

Antes de cualquier cambio sobre la base compartida se debe crear un respaldo SQL actualizado en `database/backups/`.

No se debe ejecutar ninguna de estas operaciones sobre `bdgestionlinamarmigracion` sin autorizacion explicita:

- `migrate:fresh`, `migrate:fresh --seed` o `db:wipe`.
- `DROP DATABASE`, `TRUNCATE`, eliminaciones masivas o recreacion de volumenes Docker.
- Seeders o scripts que puedan modificar datos existentes.
- Importaciones o migraciones de datos desde otra base.

Cuando una tarea requiera modificar esquema o datos, se debe informar previamente el alcance, las tablas afectadas y el respaldo disponible, y esperar confirmacion antes de ejecutarla.

Los respaldos contienen credenciales hash y datos operativos. La carpeta `database/backups/` esta excluida de Git y no debe publicarse.
