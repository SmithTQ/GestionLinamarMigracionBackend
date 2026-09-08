<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Branches\StoreBranchRequest;
use App\Http\Requests\Api\V1\Branches\UpdateBranchRequest;
use App\Http\Resources\Api\V1\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Sucursales', description: 'Catálogo y ámbito de sucursales')]
class BranchController extends Controller
{
    #[OA\Get(path: '/api/v1/branches', operationId: 'listBranches', tags: ['Sucursales'], summary: 'Listar sucursales', responses: [new OA\Response(response: 200, description: 'Sucursales obtenidas')])]
    public function index(Request $request): JsonResponse
    {
        $branches = $this->visibleQuery($request->user())
            ->when($request->filled('search'), fn ($query) => $query->where(function ($subQuery) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $subQuery->where('name', 'like', $value)->orWhere('code', 'like', $value);
            }))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'], 'name'))
            ->paginate(min($request->integer('per_page', 15), 100));

        return $this->paginatedResponse('Sucursales obtenidas.', $branches, BranchResource::class);
    }

    #[OA\Post(path: '/api/v1/branches', operationId: 'createBranch', tags: ['Sucursales'], summary: 'Crear sucursal', responses: [new OA\Response(response: 201, description: 'Sucursal creada')])]
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return response()->json(['codigo' => 201, 'mensaje' => 'Sucursal creada.', 'datos' => new BranchResource($branch)], 201);
    }

    #[OA\Get(path: '/api/v1/branches/{branch}', operationId: 'showBranch', tags: ['Sucursales'], summary: 'Consultar sucursal', responses: [new OA\Response(response: 200, description: 'Sucursal obtenida')])]
    public function show(Request $request, int $branch): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->with('campaigns')->findOrFail($branch);

        return response()->json(['codigo' => 200, 'mensaje' => 'Sucursal obtenida.', 'datos' => new BranchResource($model)]);
    }

    #[OA\Patch(path: '/api/v1/branches/{branch}', operationId: 'updateBranch', tags: ['Sucursales'], summary: 'Actualizar sucursal', responses: [new OA\Response(response: 200, description: 'Sucursal actualizada')])]
    public function update(UpdateBranchRequest $request, int $branch): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($branch);
        $model->update($request->validated());

        return response()->json(['codigo' => 200, 'mensaje' => 'Sucursal actualizada.', 'datos' => new BranchResource($model->fresh())]);
    }

    #[OA\Delete(path: '/api/v1/branches/{branch}', operationId: 'archiveBranch', tags: ['Sucursales'], summary: 'Desactivar sucursal', responses: [new OA\Response(response: 200, description: 'Sucursal desactivada')])]
    public function destroy(Request $request, int $branch): JsonResponse
    {
        $this->visibleQuery($request->user())->findOrFail($branch)->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Sucursal desactivada.', 'datos' => null]);
    }

    private function visibleQuery($user)
    {
        $query = Branch::query()->where('is_active', true);

        if (! $user->hasRole('super_admin')) {
            $query->whereHas('users', fn ($relation) => $relation->whereKey($user->id));
        }

        return $query;
    }
}
