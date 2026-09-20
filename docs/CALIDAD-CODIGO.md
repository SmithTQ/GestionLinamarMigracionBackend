# Calidad de código

## Comandos locales

```bash
composer quality
composer analyse
vendor/bin/pint --test
php artisan test
```

`composer analyse` ejecuta PHPStan con Larastan en nivel 5.

## Línea base

El archivo `phpstan-baseline.neon` registra los hallazgos heredados detectados al incorporar el análisis estático. La línea base no desactiva PHPStan: cualquier error nuevo que no esté registrado hará fallar el análisis.

La deuda existente debe reducirse por módulos. Al corregir un grupo de errores, se debe regenerar la línea base y verificar que el número de errores disminuya, sin agregar nuevas excepciones.

## CI

El workflow `.github/workflows/quality.yml` ejecuta Pint, PHPUnit y PHPStan en cada pull request y en las ramas `main`, `dev`, `qa` y `prod`.
