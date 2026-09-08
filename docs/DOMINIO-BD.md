# Fundación del dominio

## Alcance implementado

La migración `2026_09_07_210000_create_operational_foundation_tables` crea la base multi-campaña y multi-sucursal del backend.

| Tabla | Propósito |
| --- | --- |
| `users` | Identidad Laravel, con usuario, estado activo y borrado lógico |
| `branches` | Catálogo de sucursales operativas |
| `campaigns` | Campañas independientes de pedidos y su ciclo de vida |
| `campaign_branch` | Sucursales habilitadas para cada campaña |
| `roles` | Perfiles de acceso del sistema |
| `permissions` | Capacidades atómicas por módulo y acción |
| `permission_role` | Permisos asignados a cada rol |
| `role_user` | Roles asignados a usuarios |
| `campaign_user` | Campañas visibles para cada usuario |
| `branch_user` | Sucursales visibles para cada usuario |

## Reglas de integridad

- Los códigos de campañas y sucursales son únicos.
- Las tablas pivote tienen claves primarias compuestas para evitar duplicados.
- Las relaciones tienen claves foráneas y eliminación controlada.
- Usuarios, campañas y sucursales admiten borrado lógico.
- Los roles y permisos se identifican por `slug` estable para Policies y middleware.
- Los permisos iniciales se cargan con `db:seed` de forma idempotente.

## Datos iniciales

Se crean los roles `super_admin`, `campaign_manager`, `dispatcher`, `courier` y `viewer`, junto con los permisos de campañas, sucursales, usuarios, pedidos, rutas e importaciones.

No se crean usuarios administradores automáticamente. El alta del primer usuario se implementará con el flujo seguro de autenticación y configuración inicial.

## Próximo módulo

El siguiente paso es implementar autenticación API con Sanctum, login/logout, usuario actual, validaciones, rate limiting y Policies basadas en estos roles y permisos.
