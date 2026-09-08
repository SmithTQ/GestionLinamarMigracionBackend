<?php

namespace App\Http\Controllers\Api\V1\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Couriers\StoreCourierRequest;
use App\Http\Requests\Api\V1\Couriers\UpdateCourierRequest;
use App\Http\Resources\Api\V1\Dispatch\CourierResource;
use App\Models\Branch;
use App\Models\Courier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Motorizados', description: 'Catálogo y disponibilidad de motorizados')]
class CourierController extends Controller
{
    #[OA\Get(path: '/api/v1/couriers', operationId: 'listCouriers', tags: ['Motorizados'], summary: 'Listar motorizados', responses: [new OA\Response(response: 200, description: 'Motorizados obtenidos')])]
    public function index(Request $request): JsonResponse
    {
        $query = Courier::query()->with('branches')->where('is_active', true);
        if (! $request->user()->hasRole('super_admin')) {
            $query->whereHas('branches.users', fn ($relation) => $relation->whereKey($request->user()->id));
        }

        $query->when($request->filled('search'), fn ($builder) => $builder->where(function ($subQuery) use ($request): void {
            $value = '%'.$request->string('search')->toString().'%';
            $subQuery->where('name', 'like', $value)->orWhere('phone', 'like', $value)->orWhere('description', 'like', $value);
        }));
        $query->when($request->has('is_available'), fn ($builder) => $builder->where('is_available', $request->boolean('is_available')));
        $query->when($request->filled('branch_id'), fn ($builder) => $builder->whereHas('branches', fn ($relation) => $relation->whereKey($request->integer('branch_id'))));

        return $this->paginatedResponse('Motorizados obtenidos.', $this->applySorting($query, $request, ['name' => 'name', 'phone' => 'phone', 'is_available' => 'is_available', 'created_at' => 'created_at'], 'name')->paginate(min($request->integer('per_page', 20), 100)), CourierResource::class);
    }

    #[OA\Post(path: '/api/v1/couriers', operationId: 'createCourier', tags: ['Motorizados'], summary: 'Crear motorizado', responses: [new OA\Response(response: 201, description: 'Motorizado creado')])]
    public function store(StoreCourierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branchIds = $data['branch_ids'] ?? [];
        unset($data['branch_ids']);
        $this->ensureBranchesVisible($request, $branchIds);

        $courier = DB::transaction(function () use ($data, $branchIds): Courier {
            $courier = Courier::create($data);
            $courier->branches()->sync($branchIds);
            return $courier;
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Motorizado creado.', 'datos' => new CourierResource($courier->load('branches'))], 201);
    }

    #[OA\Patch(path: '/api/v1/couriers/{courier}', operationId: 'updateCourier', tags: ['Motorizados'], summary: 'Actualizar motorizado', responses: [new OA\Response(response: 200, description: 'Motorizado actualizado')])]
    public function update(UpdateCourierRequest $request, int $courier): JsonResponse
    {
        $model = Courier::findOrFail($courier);
        $data = $request->validated();
        $branchIds = $data['branch_ids'] ?? null;
        unset($data['branch_ids']);
        if ($branchIds !== null) $this->ensureBranchesVisible($request, $branchIds);

        DB::transaction(function () use ($model, $data, $branchIds): void {
            $model->update($data);
            if ($branchIds !== null) $model->branches()->sync($branchIds);
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Motorizado actualizado.', 'datos' => new CourierResource($model->fresh('branches'))]);
    }

    private function ensureBranchesVisible(Request $request, array $branchIds): void
    {
        if ($request->user()->hasRole('super_admin') || $branchIds === []) return;
        $count = Branch::whereIn('id', $branchIds)->whereHas('users', fn ($query) => $query->whereKey($request->user()->id))->count();
        abort_if($count !== count(array_unique($branchIds)), 403, 'Una sucursal está fuera de tu ámbito.');
    }
}
