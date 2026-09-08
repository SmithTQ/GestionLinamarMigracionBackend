<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function applySorting(Builder $query, Request $request, array $allowed, string $default, string $defaultDirection = 'asc'): Builder
    {
        $requested = $request->string('sort_by')->toString();
        $column = $allowed[$requested] ?? $allowed[$default] ?? $default;
        $direction = strtolower($request->string('sort_dir')->toString());
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : $defaultDirection;

        return $query->orderBy($column, $direction);
    }

    protected function paginatedResponse(string $message, LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        $serialized = $resourceClass::collection($paginator)->response()->getData(true);

        return response()->json([
            'codigo' => 200,
            'mensaje' => $message,
            'datos' => $serialized['data'] ?? [],
            'paginacion' => $serialized['meta'] ?? null,
            'enlaces' => $serialized['links'] ?? null,
        ]);
    }
}
