<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Campaigns\AssignCampaignUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Authorization\OperationalScopeService;
use App\Services\Authorization\UserManagementScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Usuarios de campaÃ±a', description: 'AsignaciÃ³n de usuarios a campaÃ±as')]
class CampaignUserController extends Controller
{
    #[OA\Get(path: '/api/v1/campaigns/{campaign}/available-dispatchers', operationId: 'listAvailableCampaignDispatchers', tags: ['Usuarios de campaña'], summary: 'Listar despachadores disponibles para la campaña', responses: [new OA\Response(response: 200, description: 'Despachadores disponibles')])]
    public function availableDispatchers(Request $request, int $campaign, OperationalScopeService $scope): JsonResponse
    {
        $model = $this->manageableCampaign($request->user(), $campaign, true, $scope);
        $users = User::query()
            ->where('users.is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('roles.slug', 'dispatcher')->where('roles.is_active', true))
            ->whereHas('branches', fn (Builder $query) => $query->whereKey($model->branch_id))
            ->whereDoesntHave('campaigns', fn (Builder $query) => $query->whereKey($model->id))
            ->when($request->filled('search'), fn (Builder $query) => $query->where(function (Builder $search) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $search->where('users.name', 'like', $value)->orWhere('users.username', 'like', $value);
            }))
            ->with('roles')
            ->orderBy('users.name')
            ->get();

        return response()->json(['codigo' => 200, 'mensaje' => 'Despachadores disponibles obtenidos.', 'datos' => UserResource::collection($users)]);
    }

    #[OA\Get(path: '/api/v1/campaigns/{campaign}/users', operationId: 'listCampaignUsers', tags: ['Usuarios de campaÃ±a'], summary: 'Listar usuarios asignados a una campaÃ±a', responses: [new OA\Response(response: 200, description: 'Usuarios obtenidos')])]
    public function index(Request $request, int $campaign): JsonResponse
    {
        $model = $this->manageableCampaign($request->user(), $campaign, false, app(OperationalScopeService::class));
        $users = $model->users()
            ->wherePivot('is_active', true)
            ->when($request->filled('search'), fn (Builder $query) => $query->where(function (Builder $scope) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $scope->where('users.name', 'like', $value)->orWhere('users.username', 'like', $value);
            }))
            ->orderBy('users.name')
            ->paginate(min($request->integer('per_page', 20), 100));

        return $this->paginatedResponse('Usuarios de campaÃ±a obtenidos.', $users, UserResource::class);
    }

    #[OA\Post(path: '/api/v1/campaigns/{campaign}/users', operationId: 'assignCampaignUser', tags: ['Usuarios de campaÃ±a'], summary: 'Asignar usuario a una campaÃ±a', responses: [new OA\Response(response: 201, description: 'Usuario asignado'), new OA\Response(response: 409, description: 'AsignaciÃ³n duplicada')])]
    public function store(AssignCampaignUserRequest $request, int $campaign, UserManagementScopeService $userScope): JsonResponse
    {
        $model = $this->manageableCampaign($request->user(), $campaign, true, app(OperationalScopeService::class));
        $data = $request->validated();
        $user = User::where('is_active', true)->findOrFail($data['user_id']);
        $userScope->assertDispatcherMatchesCampaign($user, $model);
        $assignment = $model->users()->whereKey($user->id)->first();

        if ($assignment && (bool) ($assignment->pivot->is_active ?? true)) {
            return response()->json(['codigo' => 409, 'mensaje' => 'El usuario ya está asignado a la campaña.', 'datos' => null], 409);
        }

        DB::transaction(function () use ($model, $user, $data): void {
            $model->users()->syncWithoutDetaching([$user->id => ['is_active' => $data['is_active'] ?? true]]);
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Usuario asignado a la campaña.', 'datos' => new UserResource($user->fresh(['roles.permissions', 'campaigns', 'branches']))], 201);
    }

    #[OA\Delete(path: '/api/v1/campaigns/{campaign}/users/{user}', operationId: 'removeCampaignUser', tags: ['Usuarios de campaÃ±a'], summary: 'Desactivar usuario de una campaÃ±a', responses: [new OA\Response(response: 200, description: 'AsignaciÃ³n desactivada')])]
    public function destroy(Request $request, int $campaign, int $user): JsonResponse
    {
        $model = $this->manageableCampaign($request->user(), $campaign, true, app(OperationalScopeService::class));
        User::where('is_active', true)->findOrFail($user);
        abort_unless($model->users()->whereKey($user)->exists(), 404, 'El usuario no está asignado a la campaña.');
        $model->users()->updateExistingPivot($user, ['is_active' => false]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Asignación de campaña desactivada.', 'datos' => null]);
    }

    private function manageableCampaign(User $actor, int $campaign, bool $manage, OperationalScopeService $scope): Campaign
    {
        /** @var Campaign $model */
        $model = $scope->visibleCampaigns($actor)->whereKey($campaign)->with('branch')->firstOrFail();
        if ($manage) {
            abort_unless($actor->hasRole('super_admin') || $actor->hasRole('campaign_manager'), 403, 'No puedes administrar usuarios de esta campaña.');
        }

        return $model;
    }
}
