<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DistrictLists\StoreDistrictListRequest;
use App\Http\Requests\Api\V1\DistrictLists\UpdateDistrictListRequest;
use App\Http\Resources\Api\V1\DistrictListResource;
use App\Models\District;
use App\Models\DistrictList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Plantillas de distritos', description: 'Listas reutilizables de distritos para formularios')]
class DistrictListController extends Controller
{
    #[OA\Get(path: '/api/v1/district-lists', operationId: 'listDistrictLists', tags: ['Plantillas de distritos'], summary: 'Listar plantillas de distritos', responses: [new OA\Response(response: 200, description: 'Plantillas obtenidas')])]
    public function index(Request $request): JsonResponse
    {
        $items = DistrictList::query()
            ->where('is_active', true)
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
    public function store(StoreDistrictListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $districtIds = $data['district_ids'];
        unset($data['district_ids']);
        $this->ensureActiveDistricts($districtIds);

        $list = DB::transaction(function () use ($data, $districtIds): DistrictList {
            $list = DistrictList::create($data);
            $this->syncDistricts($list, $districtIds);

            return $list;
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Plantilla de distritos creada.', 'datos' => new DistrictListResource($list->load('districts'))], 201);
    }

    #[OA\Get(path: '/api/v1/district-lists/{districtList}', operationId: 'showDistrictList', tags: ['Plantillas de distritos'], summary: 'Consultar plantilla de distritos', responses: [new OA\Response(response: 200, description: 'Plantilla obtenida')])]
    public function show(int $districtList): JsonResponse
    {
        $list = DistrictList::with('districts')->findOrFail($districtList);

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla de distritos obtenida.', 'datos' => new DistrictListResource($list)]);
    }

    #[OA\Patch(path: '/api/v1/district-lists/{districtList}', operationId: 'updateDistrictList', tags: ['Plantillas de distritos'], summary: 'Actualizar plantilla de distritos', responses: [new OA\Response(response: 200, description: 'Plantilla actualizada')])]
    public function update(UpdateDistrictListRequest $request, int $districtList): JsonResponse
    {
        $list = DistrictList::findOrFail($districtList);
        $data = $request->validated();
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

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla de distritos actualizada.', 'datos' => new DistrictListResource($list->fresh('districts'))]);
    }

    #[OA\Delete(path: '/api/v1/district-lists/{districtList}', operationId: 'archiveDistrictList', tags: ['Plantillas de distritos'], summary: 'Desactivar plantilla de distritos', responses: [new OA\Response(response: 200, description: 'Plantilla desactivada')])]
    public function destroy(int $districtList): JsonResponse
    {
        $list = DistrictList::findOrFail($districtList);
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
}
