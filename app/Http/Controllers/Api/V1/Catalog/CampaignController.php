<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Api\V1\Campaigns\UpdateCampaignRequest;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\DistrictList;
use App\Services\Authorization\OperationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Campañas', description: 'Campañas y su ámbito de sucursales')]
class CampaignController extends Controller
{
    #[OA\Get(path: '/api/v1/campaigns/available', operationId: 'listAvailableCampaigns', tags: ['CampaÃ±as'], summary: 'Listar campaÃ±as disponibles para el usuario', responses: [new OA\Response(response: 200, description: 'CampaÃ±as disponibles')])]
    public function available(Request $request): JsonResponse
    {
        $scope = app(OperationalScopeService::class);
        $branchId = $scope->validatedBranchId($request->user(), $request->integer('branch_id') ?: null);
        $campaigns = $this->visibleQuery($request->user(), $scope)
            ->where('campaigns.status', 'open')
            ->when($branchId !== null, fn ($query) => $query->where('campaigns.branch_id', $branchId))
            ->select(['campaigns.id', 'campaigns.code', 'campaigns.name', 'campaigns.branch_id', 'campaigns.status'])
            ->with('branch')
            ->withExists(['forms as has_published_form' => fn ($query) => $query->where('status', 'published')])
            ->addSelect(['form_status' => CampaignForm::query()
                ->select('status')->whereColumn('campaign_id', 'campaigns.id')
                ->orderByRaw("CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'closed' THEN 3 ELSE 4 END")
                ->limit(1)])
            ->orderBy('campaigns.name')
            ->get();

        return response()->json(['codigo' => 200, 'mensaje' => 'CampaÃ±as disponibles obtenidas.', 'datos' => CampaignResource::collection($campaigns)]);
    }

    #[OA\Get(path: '/api/v1/campaigns', operationId: 'listCampaigns', tags: ['Campañas'], summary: 'Listar campañas', responses: [new OA\Response(response: 200, description: 'Campañas obtenidas')])]
    public function index(Request $request): JsonResponse
    {
        $campaigns = $this->visibleQuery($request->user(), app(OperationalScopeService::class))
            ->withCount([
                'orders as orders_count' => fn ($query) => $query->where('is_active', true),
                'orders as delivered_count' => fn ($query) => $query->where('is_active', true)->where('status', 'delivered'),
            ])
            ->withSum([
                'orders as total_obtained' => fn ($query) => $query->where('is_active', true)->where('status', 'delivered'),
            ], 'product_price')
            ->withExists(['forms as has_published_form' => fn ($query) => $query->where('status', 'published')])
            ->addSelect(['form_status' => CampaignForm::query()
                ->select('status')
                ->whereColumn('campaign_id', 'campaigns.id')
                ->orderByRaw("CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'closed' THEN 3 ELSE 4 END")
                ->limit(1)])
            ->with(['branch', 'districtLists' => fn ($query) => $query->withCount('districts')])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($subQuery) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $subQuery->where('code', 'like', $value)->orWhere('name', 'like', $value);
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('starts_on_from'), fn ($query) => $query->whereDate('starts_on', '>=', $request->date('starts_on_from')))
            ->when($request->filled('starts_on_to'), fn ($query) => $query->whereDate('starts_on', '<=', $request->date('starts_on_to')))
            ->when($request->filled('ends_on_from'), fn ($query) => $query->whereDate('ends_on', '>=', $request->date('ends_on_from')))
            ->when($request->filled('ends_on_to'), fn ($query) => $query->whereDate('ends_on', '<=', $request->date('ends_on_to')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('district_list_id'), fn ($query) => $query->whereHas('districtLists', fn ($relation) => $relation->whereKey($request->integer('district_list_id'))))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['starts_on' => 'starts_on', 'ends_on' => 'ends_on', 'code' => 'code', 'name' => 'name', 'status' => 'status', 'created_at' => 'created_at'], 'starts_on', 'desc'))
            ->paginate(min($request->integer('per_page', 15), 100));

        return $this->paginatedResponse('Campañas obtenidas.', $campaigns, CampaignResource::class);
    }

    #[OA\Post(
        path: '/api/v1/campaigns',
        operationId: 'createCampaign',
        tags: ['Campañas'],
        summary: 'Crear campaña',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'name', 'branch_id'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'CAMPANA_ENERO'),
                    new OA\Property(property: 'name', type: 'string', example: 'Pedidos de enero'),
                    new OA\Property(property: 'status', type: 'string', example: 'draft'),
                    new OA\Property(property: 'starts_on', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'ends_on', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'branch_id', type: 'integer', example: 1),
                    new OA\Property(property: 'district_list_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5]),
                ],
            ),
        ),
        responses: [new OA\Response(response: 201, description: 'Campaña creada')],
    )]
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $data = $request->validated();
        $districtListIds = $data['district_list_ids'] ?? [];
        unset($data['district_list_ids']);

        $branch = Branch::findOrFail($data['branch_id']);
        $scope = app(OperationalScopeService::class);
        $this->ensureBranchIsVisible($request->user(), $branch, $scope);
        $this->ensureDistrictListsAreUsable($districtListIds, $branch->id);

        $campaign = DB::transaction(function () use ($data, $districtListIds): Campaign {
            $campaign = Campaign::create($data);
            $campaign->districtLists()->sync($districtListIds);

            return $campaign;
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Campaña creada.', 'datos' => new CampaignResource($campaign->load(['branch', 'districtLists' => fn ($query) => $query->withCount('districts')]))], 201);
    }

    #[OA\Get(path: '/api/v1/campaigns/{campaign}', operationId: 'showCampaign', tags: ['Campañas'], summary: 'Consultar campaña', responses: [new OA\Response(response: 200, description: 'Campaña obtenida')])]
    public function show(Request $request, int $campaign): JsonResponse
    {
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->with(['branch', 'districtLists' => fn ($query) => $query->withCount('districts')->with('districts')])->findOrFail($campaign);

        return response()->json(['codigo' => 200, 'mensaje' => 'Campaña obtenida.', 'datos' => new CampaignResource($model)]);
    }

    #[OA\Patch(
        path: '/api/v1/campaigns/{campaign}',
        operationId: 'updateCampaign',
        tags: ['Campañas'],
        summary: 'Actualizar campaña',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'starts_on', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'ends_on', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'branch_id', type: 'integer'),
                    new OA\Property(property: 'district_list_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Campaña actualizada')],
    )]
    public function update(UpdateCampaignRequest $request, int $campaign): JsonResponse
    {
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($campaign);
        $data = $request->validated();
        $districtListIds = $data['district_list_ids'] ?? null;
        unset($data['district_list_ids']);

        if (array_key_exists('branch_id', $data)) {
            $this->ensureBranchIsVisible($request->user(), Branch::findOrFail($data['branch_id']), app(OperationalScopeService::class));
        }

        $targetBranchId = (int) ($data['branch_id'] ?? $model->branch_id);
        $districtListIds ??= $model->districtLists()->pluck('district_lists.id')->all();
        $model->update($data);
        if ($districtListIds !== []) {
            $this->ensureDistrictListsAreUsable($districtListIds, $targetBranchId);
            $model->districtLists()->sync($districtListIds);
        }

        return response()->json(['codigo' => 200, 'mensaje' => 'Campaña actualizada.', 'datos' => new CampaignResource($model->fresh(['branch', 'districtLists' => fn ($query) => $query->withCount('districts')->with('districts')]))]);
    }

    #[OA\Delete(path: '/api/v1/campaigns/{campaign}', operationId: 'archiveCampaign', tags: ['Campañas'], summary: 'Cerrar campaña', responses: [new OA\Response(response: 200, description: 'Campaña cerrada')])]
    public function destroy(Request $request, int $campaign): JsonResponse
    {
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($campaign);
        $model->update(['status' => 'closed']);
        $model->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Campaña cerrada.', 'datos' => null]);
    }

    private function ensureBranchIsVisible($user, Branch $branch, OperationalScopeService $scope): void
    {
        abort_unless($scope->canAccessBranch($user, $branch), 403, 'No tienes acceso a la sucursal seleccionada.');
    }

    private function ensureDistrictListsAreUsable(array $districtListIds, int $branchId): void
    {
        if ($districtListIds === []) {
            return;
        }

        $lists = DistrictList::query()
            ->whereIn('id', array_unique($districtListIds))
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->withCount('districts')
            ->get();

        abort_unless($lists->count() === count(array_unique($districtListIds)), 422, 'Todos los listados de cobertura deben estar activos y pertenecer a la sucursal de la campaña.');
        abort_if($lists->contains(fn (DistrictList $list): bool => $list->districts_count === 0), 422, 'La campaña no puede utilizar un listado de cobertura vacío.');
    }

    private function visibleQuery($user, OperationalScopeService $scope)
    {
        return $scope->visibleCampaigns($user)->whereIn('status', ['draft', 'open', 'closed']);
    }
}
