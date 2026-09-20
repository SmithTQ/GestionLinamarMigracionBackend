<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DistrictLists\StoreDistrictListRequest;
use App\Http\Requests\Api\V1\DistrictLists\UpdateDistrictListRequest;
use App\Http\Resources\Api\V1\DistrictListResource;
use App\Models\District;
use App\Models\DistrictList;
use App\Models\User;
use App\Services\Authorization\OperationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Plantillas de distritos', description: 'Listas reutilizables de distritos para formularios')]
class DistrictListController extends Controller
{
    #[OA\Get(path: '/api/v1/district-lists', operationId: 'listDistrictLists', tags: ['Plantillas de distritos'], summary: 'Listar plantillas de distritos', responses: [new OA\Response(response: 200, description: 'Plantillas obtenidas')])]
    public function index(Request $request, OperationalScopeService $scope): JsonResponse
    {
        $branchId = $scope->validatedBranchId($request->user(), $request->integer('branch_id') ?: null);
        $items = $this->visibleQuery($request->user(), $scope)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->with(['branch', 'districts'])
            ->withCount('districts')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($subQuery) => $subQuery->where('code', 'like', $value)->orWhere('name', 'like', $value)->orWhere('description', 'like', $value));
            })
            ->tap(fn ($query) => $this->applySorting($query, $request, ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'], 'name'))
            ->paginate(min($request->integer('per_page', 20), 100));

        return $this->paginatedResponse('Plantillas de distritos obtenidas.', $items, DistrictListResource::class);
    }

    #[OA\Post(path: '/api/v1/district-lists', operationId: 'createDistrictList', tags: ['Plantillas de distritos'], summary: 'Crear plantilla de distritos', responses: [new OA\Response(response: 201, description: 'Plantilla creada')])]
    public function store(StoreDistrictListRequest $request, OperationalScopeService $scope): JsonResponse
    {
        $data = $request->validated();
        $this->ensureBranchAccess($request->user(), $data['branch_id'], $scope);
        $districtIds = $data['district_ids'];
        unset($data['district_ids']);
        $this->ensureActiveDistricts($districtIds);

        $list = DB::transaction(function () use ($data, $districtIds): DistrictList {
            $list = DistrictList::create($data);
            $this->syncDistricts($list, $districtIds);

            return $list;
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Plantilla de distritos creada.', 'datos' => new DistrictListResource($list->load(['branch', 'districts']))], 201);
    }

    #[OA\Get(path: '/api/v1/district-lists/{districtList}', operationId: 'showDistrictList', tags: ['Plantillas de distritos'], summary: 'Consultar plantilla de distritos', responses: [new OA\Response(response: 200, description: 'Plantilla obtenida')])]
    public function show(Request $request, int $districtList, OperationalScopeService $scope): JsonResponse
    {
        $list = $this->visibleQuery($request->user(), $scope)->with(['branch', 'districts'])->findOrFail($districtList);

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla de distritos obtenida.', 'datos' => new DistrictListResource($list)]);
    }

    #[OA\Patch(path: '/api/v1/district-lists/{districtList}', operationId: 'updateDistrictList', tags: ['Plantillas de distritos'], summary: 'Actualizar plantilla de distritos', responses: [new OA\Response(response: 200, description: 'Plantilla actualizada')])]
    public function update(UpdateDistrictListRequest $request, int $districtList, OperationalScopeService $scope): JsonResponse
    {
        $list = $this->visibleQuery($request->user(), $scope)->findOrFail($districtList);
        $data = $request->validated();
        if (array_key_exists('branch_id', $data)) {
            $this->ensureBranchAccess($request->user(), $data['branch_id'], $scope);
        }
        $districtIds = $data['district_ids'] ?? null;
        unset($data['district_ids']);
        if ($districtIds !== null) {
            $this->ensureActiveDistricts($districtIds);
        }

        DB::transaction(function () use ($list, $data, $districtIds): void {
            $list->update($data);
            if ($districtIds !== null) {
                $this->syncDistricts($list, $districtIds);
            }
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla de distritos actualizada.', 'datos' => new DistrictListResource($list->fresh(['branch', 'districts']))]);
    }

    #[OA\Delete(path: '/api/v1/district-lists/{districtList}', operationId: 'archiveDistrictList', tags: ['Plantillas de distritos'], summary: 'Desactivar plantilla de distritos', responses: [new OA\Response(response: 200, description: 'Plantilla desactivada')])]
    public function destroy(Request $request, int $districtList, OperationalScopeService $scope): JsonResponse
    {
        $list = $this->visibleQuery($request->user(), $scope)->findOrFail($districtList);
        abort_if($list->campaigns()->whereIn('status', ['draft', 'open'])->exists(), 422, 'El listado está siendo utilizado por una campaña activa.');
        $list->update(['is_active' => false]);
        $list->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla de distritos desactivada.', 'datos' => null]);
    }

    private function ensureActiveDistricts(array $districtIds): void
    {
        $activeCount = District::query()->whereIn('id', $districtIds)->where('is_active', true)->count();
        abort_unless($activeCount === count(array_unique($districtIds)), 422, 'La plantilla solo puede contener distritos activos.');
    }

    private function syncDistricts(DistrictList $list, array $districtIds): void
    {
        $sync = [];
        foreach (array_values($districtIds) as $position => $districtId) {
            $sync[$districtId] = ['sort_order' => ($position + 1) * 10];
        }
        $list->districts()->sync($sync);
    }

    private function visibleQuery(?User $actor, OperationalScopeService $scope)
    {
        abort_unless($actor instanceof User, 401, 'Debes iniciar sesión para consultar plantillas de distritos.');

        return DistrictList::query()->whereIn('branch_id', $scope->visibleBranches($actor)->select('branches.id'));
    }

    private function ensureBranchAccess(?User $actor, int $branchId, OperationalScopeService $scope): void
    {
        abort_unless($actor instanceof User && $scope->canAccessBranch($actor, $branchId), 403, 'No tienes acceso a la sucursal seleccionada.');
    }
}
