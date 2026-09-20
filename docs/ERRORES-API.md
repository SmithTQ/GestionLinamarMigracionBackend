# Respuestas de error de la API

Todas las rutas `/api/*` devuelven errores con una estructura consistente para que el frontend pueda mostrarlos directamente.

## Estructura general

```json
{
  "codigo": 422,
  "mensaje": "Los datos enviados no son válidos. Revisa los campos indicados.",
  "datos": null,
  "errores": {
    "branch_id": ["El campo branch id es obligatorio."]
  }
}
```

`errores` solo aparece cuando existen errores de validación. Sus claves corresponden al nombre del campo enviado y sus valores son mensajes aptos para mostrar al usuario.

## Códigos principales

| Código | Significado | Mensaje predeterminado |
| --- | --- | --- |
| `401` | Sesión ausente, inválida o expirada | `Tu sesión no es válida o ha expirado. Inicia sesión nuevamente.` |
| `403` | Usuario autenticado sin permiso | `No tienes permisos para realizar esta operación.` |
| `404` | Recurso o ruta inexistente | `La ruta o recurso solicitado no existe.` |
| `409` | Conflicto con el estado actual | `La operación entra en conflicto con el estado actual del recurso.` |
| `422` | Datos inválidos o regla de negocio incumplida | Mensaje específico de la operación; si es validación, incluye `errores`. |
| `429` | Demasiadas solicitudes | `Se realizaron demasiadas solicitudes. Espera unos segundos e inténtalo nuevamente.` |
| `500` | Error interno | `Ocurrió un error inesperado. Intenta nuevamente.` |

Los errores `500` no exponen SQL, nombres de tablas, excepciones ni trazas. El detalle técnico se conserva únicamente en los logs del backend.

## Recomendación para Angular

1. Mostrar `mensaje` como mensaje general.
2. Si existe `errores`, asociar cada arreglo al control cuyo nombre coincida con la clave.
3. Para `401`, limpiar la sesión y redirigir al login.
4. Para `403`, mostrar acceso denegado sin reintentar automáticamente.
5. Para `500`, mostrar el mensaje genérico y registrar el identificador de la solicitud si el backend lo incorpora posteriormente.
