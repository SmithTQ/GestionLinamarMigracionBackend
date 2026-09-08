<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Api\V1\Campaigns\UpdateCampaignRequest;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Models\Campaign;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Campañas', description: 'Campañas y su ámbito de sucursales')]
class CampaignController extends Controller
{
    #[OA\Get(path: '/api/v1/campaigns', operationId: 'listCampaigns', tags: ['Campañas'], summary: 'Listar campañas', responses: [new OA\Response(response: 200, description: 'Campañas obtenidas')])]
    public function index(Request $request): JsonResponse
    {
        $campaigns = $this->visibleQuery($request->user())
            ->when($request->filled('search'), fn ($query) => $query->where(function ($subQuery) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $subQuery->where('code', 'like', $value)->orWhere('name', 'like', $value);
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('starts_on_from'), fn ($query) => $query->whereDate('starts_on', '>=', $request->date('starts_on_from')))
            ->when($request->filled('starts_on_to'), fn ($query) => $query->whereDate('starts_on', '<=', $request->date('starts_on_to')))
            ->when($request->filled('ends_on_from'), fn ($query) => $query->whereDate('ends_on', '>=', $request->date('ends_on_from')))
            ->when($request->filled('ends_on_to'), fn ($query) => $query->whereDate('ends_on', '<=', $request->date('ends_on_to')))
            ->when($request->filled('branch_id'), fn ($query) => $query->whereHas('branches', fn ($relation) => $relation->whereKey($request->integer('branch_id'))))
            ->when($request->filled('district_id'), fn ($query) => $query->whereHas('districts', fn ($relation) => $relation->whereKey($request->integer('district_id'))))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['starts_on' => 'starts_on', 'ends_on' => 'ends_on', 'code' => 'code', 'name' => 'name', 'status' => 'status', 'created_at' => 'created_at'], 'starts_on', 'desc'))
            ->paginate(min($request->integer('per_page', 15), 100));

        return $this->paginatedResponse('Campañas obtenidas.', $campaigns, CampaignResource::class);
    }

    #[OA\Post(path: '/api/v1/campaigns', operationId: 'createCampaign', tags: ['Campañas'], summary: 'Crear campaña', responses: [new OA\Response(response: 201, description: 'Campaña creada')])]
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branchIds = $data['branch_ids'] ?? [];
        $districtIds = $data['district_ids'] ?? [];
        unset($data['branch_ids'], $data['district_ids']);

        $this->ensureBranchesAreVisible($request->user(), $branchIds);

        $campaign = Campaign::create($data);
        $campaign->branches()->sync($branchIds);
        $campaign->districts()->sync($districtIds);

        return response()->json(['codigo' => 201, 'mensaje' => 'Campaña creada.', 'datos' => new CampaignResource($campaign->load(['branches', 'districts']))], 201);
    }

    #[OA\Get(path: '/api/v1/campaigns/{campaign}', operationId: 'showCampaign', tags: ['Campañas'], summary: 'Consultar campaña', responses: [new OA\Response(response: 200, description: 'Campaña obtenida')])]
    public function show(Request $request, int $campaign): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->with(['branches', 'districts'])->findOrFail($campaign);

        return response()->json(['codigo' => 200, 'mensaje' => 'Campaña obtenida.', 'datos' => new CampaignResource($model)]);
    }

    #[OA\Patch(path: '/api/v1/campaigns/{campaign}', operationId: 'updateCampaign', tags: ['Campañas'], summary: 'Actualizar campaña', responses: [new OA\Response(response: 200, description: 'Campaña actualizada')])]
    public function update(UpdateCampaignRequest $request, int $campaign): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($campaign);
        $data = $request->validated();
        $branchIds = $data['branch_ids'] ?? null;
        $districtIds = $data['district_ids'] ?? null;
        unset($data['branch_ids'], $data['district_ids']);

        $model->update($data);
        if ($branchIds !== null) {
            $this->ensureBranchesAreVisible($request->user(), $branchIds);
            $model->branches()->sync($branchIds);
        }
        if ($districtIds !== null) $model->districts()->sync($districtIds);

        return response()->json(['codigo' => 200, 'mensaje' => 'Campaña actualizada.', 'datos' => new CampaignResource($model->fresh(['branches', 'districts']))]);
    }

    #[OA\Delete(path: '/api/v1/campaigns/{campaign}', operationId: 'archiveCampaign', tags: ['Campañas'], summary: 'Cerrar campaña', responses: [new OA\Response(response: 200, description: 'Campaña cerrada')])]
    public function destroy(Request $request, int $campaign): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($campaign);
        $model->update(['status' => 'closed']);
        $model->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Campaña cerrada.', 'datos' => null]);
    }

    private function ensureBranchesAreVisible($user, array $branchIds): void
    {
        if ($user->hasRole('super_admin') || $branchIds === []) {
            return;
        }

        $visibleCount = Branch::query()
            ->whereIn('id', $branchIds)
            ->whereHas('users', fn ($relation) => $relation->whereKey($user->id))
            ->count();

        abort_if($visibleCount !== count(array_unique($branchIds)), 403, 'No tienes acceso a una de las sucursales seleccionadas.');
    }

    private function visibleQuery($user)
    {
        $query = Campaign::query()->whereIn('status', ['draft', 'open', 'closed']);

        if (! $user->hasRole('super_admin')) {
            $query->whereHas('users', fn ($relation) => $relation->whereKey($user->id));
        }

        return $query;
    }
}
