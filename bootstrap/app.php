<?php

use App\Http\Middleware\EnsurePermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $messageForHttpStatus = static fn (int $status): string => match ($status) {
            401 => 'Tu sesión no es válida o ha expirado. Inicia sesión nuevamente.',
            403 => 'No tienes permisos para realizar esta operación.',
            404 => 'La ruta o recurso solicitado no existe.',
            405 => 'El método HTTP utilizado no está permitido para este recurso.',
            409 => 'La operación entra en conflicto con el estado actual del recurso.',
            413 => 'El archivo o contenido enviado supera el tamaño permitido.',
            422 => 'Los datos enviados no son válidos.',
            429 => 'Se realizaron demasiadas solicitudes. Espera unos segundos e inténtalo nuevamente.',
            default => 'No se pudo procesar la solicitud.',
        };

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) use ($messageForHttpStatus): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'codigo' => 422,
                    'mensaje' => 'Los datos enviados no son válidos. Revisa los campos indicados.',
                    'datos' => null,
                    'errores' => $exception->errors(),
                ], 422);
            }

            $status = 500;
            $message = 'Ocurrió un error inesperado. Intenta nuevamente.';

            if ($exception instanceof AuthenticationException) {
                $status = 401;
                $message = 'Tu sesión no es válida o ha expirado. Inicia sesión nuevamente.';
            } elseif ($exception instanceof AuthorizationException) {
                $status = 403;
                $message = 'No tienes permisos para realizar esta operación.';
            } elseif ($exception instanceof ModelNotFoundException) {
                $status = 404;
                $message = 'No se encontró el recurso solicitado.';
            } elseif ($exception instanceof NotFoundHttpException) {
                $status = 404;
                $message = in_array(trim($exception->getMessage()), ['', 'Not Found'], true)
                    ? 'La ruta o recurso solicitado no existe.'
                    : $exception->getMessage();
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $message = $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : $messageForHttpStatus($status);
            }

            if ($status >= 500) {
                $message = 'Ocurrió un error inesperado. Intenta nuevamente.';
            }

            return response()->json([
                'codigo' => $status,
                'mensaje' => $message,
                'datos' => null,
            ], $status);
        });
    })->create();
