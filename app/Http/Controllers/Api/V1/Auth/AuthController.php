<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/login', operationId: 'login', tags: ['Autenticación'],
        summary: 'Iniciar sesión',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: 'usuario@linamar.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'contraseña'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sesión iniciada'),
            new OA\Response(response: 422, description: 'Datos inválidos'),
            new OA\Response(response: 429, description: 'Demasiados intentos'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->string('login')->toString());
        $user = User::query()
            ->where(fn ($query) => $query->where('email', $login)->orWhere('username', $login))
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'codigo' => 401,
                'mensaje' => 'Las credenciales no son válidas.',
                'datos' => null,
            ], 401);
        }

        $user->tokens()->where('name', 'angular')->delete();
        $token = $user->createToken('angular')->plainTextToken;

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Sesión iniciada correctamente.',
            'datos' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->load('roles.permissions'),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout', operationId: 'logout', tags: ['Autenticación'],
        summary: 'Cerrar la sesión actual', security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        if ($plainToken = $request->bearerToken()) {
            PersonalAccessToken::findToken($plainToken)?->delete();
        }

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Sesión cerrada correctamente.',
            'datos' => null,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/auth/me', operationId: 'me', tags: ['Autenticación'],
        summary: 'Obtener el usuario autenticado', security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Usuario autenticado'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ],
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Usuario obtenido correctamente.',
            'datos' => $request->user()->load('roles.permissions', 'campaigns', 'branches'),
        ]);
    }
}
