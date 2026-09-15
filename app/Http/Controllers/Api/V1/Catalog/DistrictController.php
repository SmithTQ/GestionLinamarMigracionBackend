<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Districts\StoreDistrictRequest;
use App\Http\Requests\Api\V1\Districts\UpdateDistrictRequest;
use App\Http\Resources\Api\V1\DistrictResource;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Distritos', description: 'Catálogo de distritos y zonas de entrega')]
class DistrictController extends Controller
{
    #[OA\Get(path: '/api/v1/districts/departments', operationId: 'listDistrictDepartments', tags: ['Distritos'], summary: 'Listar departamentos', responses: [new OA\Response(response: 200, description: 'Departamentos obtenidos')])]
    public function departments(): JsonResponse
    {
        $departments = District::query()
            ->where('is_active', true)
            ->whereNotNull('department_code')
            ->whereNotNull('department')
            ->select(['department_code', 'department'])
            ->distinct()
            ->orderBy('department')
            ->get()
            ->map(fn (District $district): array => [
                'code' => $district->department_code,
                'name' => $district->department,
            ])
            ->values();

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Departamentos obtenidos.',
            'datos' => $departments,
        ]);
    }

    #[OA\Get(path: '/api/v1/districts/provinces', operationId: 'listDistrictProvinces', tags: ['Distritos'], summary: 'Listar provincias por departamento', parameters: [new OA\Parameter(name: 'department_code', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '15'))], responses: [new OA\Response(response: 200, description: 'Provincias obtenidas')])]
    public function provinces(Request $request): JsonResponse
    {
        $departmentCode = $request->string('department_code')->trim()->toString();
        abort_if($departmentCode === '', 422, 'El codigo de departamento es obligatorio.');

        $provinces = District::query()
            ->where('is_active', true)
            ->where('department_code', $departmentCode)
            ->whereNotNull('province_code')
            ->whereNotNull('province')
            ->select(['department_code', 'province_code', 'province'])
            ->distinct()
            ->orderBy('province')
            ->get()
            ->map(fn (District $district): array => [
                'code' => $district->province_code,
                'name' => $district->province,
            ])
            ->values();

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Provincias obtenidas.',
            'datos' => $provinces,
        ]);
    }

    #[OA\Get(path: '/api/v1/districts', operationId: 'listDistricts', tags: ['Distritos'], summary: 'Listar distritos', responses: [new OA\Response(response: 200, description: 'Distritos obtenidos')])]
    public function index(Request $request): JsonResponse
    {
        $districts = District::query()->where('is_active', true)
            ->when($request->filled('search'), fn ($query) => $query->where(function ($subQuery) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $subQuery->where('name', 'like', $value)->orWhere('code', 'like', $value);
            }))
            ->when($request->filled('department'), fn ($query) => $query->where('department', $request->string('department')->toString()))
            ->when($request->filled('department_code'), fn ($query) => $query->where('department_code', $request->string('department_code')->toString()))
            ->when($request->filled('province_code'), fn ($query) => $query->where('province_code', $request->string('province_code')->toString()))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['name' => 'name', 'code' => 'code', 'province' => 'province', 'department' => 'department', 'macroregion' => 'macroregion', 'created_at' => 'created_at'], 'name'))
            ->paginate(min($request->integer('per_page', 50), 100));

        return $this->paginatedResponse('Distritos obtenidos.', $districts, DistrictResource::class);
    }

    #[OA\Post(path: '/api/v1/districts', operationId: 'createDistrict', tags: ['Distritos'], summary: 'Crear distrito', responses: [new OA\Response(response: 201, description: 'Distrito creado')])]
    public function store(StoreDistrictRequest $request): JsonResponse
    {
        $district = District::create($request->validated());

        return response()->json(['codigo' => 201, 'mensaje' => 'Distrito creado.', 'datos' => new DistrictResource($district)], 201);
    }

    #[OA\Get(path: '/api/v1/districts/{district}', operationId: 'showDistrict', tags: ['Distritos'], summary: 'Consultar distrito', responses: [new OA\Response(response: 200, description: 'Distrito obtenido')])]
    public function show(int $district): JsonResponse
    {
        return response()->json(['codigo' => 200, 'mensaje' => 'Distrito obtenido.', 'datos' => new DistrictResource(District::findOrFail($district))]);
    }

    #[OA\Patch(path: '/api/v1/districts/{district}', operationId: 'updateDistrict', tags: ['Distritos'], summary: 'Actualizar distrito', responses: [new OA\Response(response: 200, description: 'Distrito actualizado')])]
    public function update(UpdateDistrictRequest $request, int $district): JsonResponse
    {
        $model = District::findOrFail($district);
        $model->update($request->validated());

        return response()->json(['codigo' => 200, 'mensaje' => 'Distrito actualizado.', 'datos' => new DistrictResource($model->fresh())]);
    }

    #[OA\Delete(path: '/api/v1/districts/{district}', operationId: 'archiveDistrict', tags: ['Distritos'], summary: 'Desactivar distrito', responses: [new OA\Response(response: 200, description: 'Distrito desactivado')])]
    public function destroy(int $district): JsonResponse
    {
        $model = District::findOrFail($district);
        $model->update(['is_active' => false]);
        $model->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Distrito desactivado.', 'datos' => null]);
    }
}
