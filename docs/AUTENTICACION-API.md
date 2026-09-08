# Autenticación API

La API usa tokens personales revocables de Laravel Sanctum. El frontend debe enviar el token en cada solicitud protegida:

```http
Authorization: Bearer <token>
```

## Endpoints

| Método | Ruta | Autenticación | Propósito |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/login` | No | Iniciar sesión por usuario o correo |
| `GET` | `/api/v1/auth/me` | Sí | Obtener usuario, roles, permisos y ámbitos |
| `POST` | `/api/v1/auth/logout` | Sí | Revocar el token actual |

## Login

Solicitud:

```json
{
  "login": "usuario@dominio.test",
  "password": "contraseña"
}
```

Respuesta exitosa:

```json
{
  "codigo": 200,
  "mensaje": "Sesión iniciada correctamente.",
  "datos": {
    "token": "...",
    "token_type": "Bearer",
    "user": {}
  }
}
```

Los intentos de login están limitados a 5 por minuto por login y dirección IP. Las credenciales inválidas siempre devuelven un mensaje genérico.

## Integración Angular

- Guardar el token únicamente según la política de seguridad definida para el frontend.
- Agregar el encabezado Bearer mediante un interceptor.
- Si una respuesta protegida devuelve `401`, limpiar la sesión y redirigir al login.
- No mostrar ni registrar el token en consola, errores o telemetría.
