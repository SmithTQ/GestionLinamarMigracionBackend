<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '0.1.0',
    title: 'Gestor de Pedidos Linamar API',
    description: 'Contrato HTTP del backend separado para la gestión de pedidos.',
    contact: new OA\Contact(email: 'soporte@linamar.local'),
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Entorno local Docker')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'token',
    description: 'Token personal emitido por Laravel Sanctum',
)]
#[OA\Tag(name: 'Sistema', description: 'Endpoints base del backend')]
final class Documentation {}
