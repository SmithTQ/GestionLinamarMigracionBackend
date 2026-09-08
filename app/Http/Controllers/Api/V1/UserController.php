<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\StoreUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRequest;
use App\Http\Resources\Api\V1\PermissionResource;
use App\Http\Resources\Api\V1\RoleResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Usuarios', description: 'Usuarios, roles, permisos y ámbitos')]
class UserController extends Controller
{
    #[OA\Get(path: '/api/v1/users', operationId: 'listUsers', tags: ['Usuarios'], summary: 'Listar usuarios', responses: [new OA\Response(response: 200, description: 'Usuarios obtenidos')])]
    public function index(Request $request): JsonResponse
    {
        $users = $this->visibleQuery($request->user())
            ->with(['roles.permissions', 'campaigns', 'branches'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where(function (Builder $subQuery) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $subQuery->where('name', 'like', $value)->orWhere('username', 'like', $value)->orWhere('email', 'like', $value);
            }))
            ->when($request->has('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('role_id'), fn (Builder $query) => $query->whereHas('roles', fn ($relation) => $relation->whereKey($request->integer('role_id'))))
            ->when($request->filled('campaign_id'), fn (Builder $query) => $query->whereHas('campaigns', fn ($relation) => $relation->whereKey($request->integer('campaign_id'))))
            ->when($request->filled('branch_id'), fn (Builder $query) => $query->whereHas('branches', fn ($relation) => $relation->whereKey($request->integer('branch_id'))))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['name' => 'name', 'username' => 'username', 'email' => 'email', 'created_at' => 'created_at'], 'name'))
            ->paginate(min($request->integer('per_page', 15), 100));

        return $this->paginatedResponse('Usuarios obtenidos.', $users, UserResource::class);
    }

    #[OA\Post(path: '/api/v1/users', operationId: 'createUser', tags: ['Usuarios'], summary: 'Crear usuario', responses: [new OA\Response(response: 201, description: 'Usuario creado')])]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $roleIds = $data['role_ids'] ?? [];
        $campaignIds = $data['campaign_ids'] ?? [];
        $branchIds = $data['branch_ids'] ?? [];
        unset($data['role_ids'], $data['campaign_ids'], $data['branch_ids']);

        $this->ensureRolesCanBeAssigned($request->user(), $roleIds);
        $this->ensureScopesAreVisible($request->user(), $campaignIds, $branchIds);

        $user = DB::transaction(function () use ($data, $roleIds, $campaignIds, $branchIds): User {
            $user = User::create($data);
            $user->roles()->sync($roleIds);
            $user->campaigns()->sync($campaignIds);
            $user->branches()->sync($branchIds);

            return $user;
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Usuario creado.', 'datos' => new UserResource($user->load(['roles.permissions', 'campaigns', 'branches']))], 201);
    }

    #[OA\Get(path: '/api/v1/users/{user}', operationId: 'showUser', tags: ['Usuarios'], summary: 'Consultar usuario', responses: [new OA\Response(response: 200, description: 'Usuario obtenido')])]
    public function show(Request $request, int $user): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->with(['roles.permissions', 'campaigns', 'branches'])->findOrFail($user);

        return response()->json(['codigo' => 200, 'mensaje' => 'Usuario obtenido.', 'datos' => new UserResource($model)]);
    }

    #[OA\Patch(path: '/api/v1/users/{user}', operationId: 'updateUser', tags: ['Usuarios'], summary: 'Actualizar usuario', responses: [new OA\Response(response: 200, description: 'Usuario actualizado')])]
    public function update(UpdateUserRequest $request, int $user): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($user);
        $data = $request->validated();
        $roleIds = $data['role_ids'] ?? null;
        $campaignIds = $data['campaign_ids'] ?? null;
        $branchIds = $data['branch_ids'] ?? null;
        unset($data['role_ids'], $data['campaign_ids'], $data['branch_ids']);

        if ($model->is($request->user()) && (($data['is_active'] ?? true) === false)) {
            abort(422, 'No puedes desactivar tu propio usuario.');
        }
        if ($roleIds !== null) {
            $this->ensureRolesCanBeAssigned($request->user(), $roleIds);
            $this->ensureNotRemovingLastAdmin($model, $roleIds);
        }
        $this->ensureScopesAreVisible($request->user(), $campaignIds ?? $model->campaigns()->pluck('campaigns.id')->all(), $branchIds ?? $model->branches()->pluck('branches.id')->all());

        DB::transaction(function () use ($model, $data, $roleIds, $campaignIds, $branchIds): void {
            $model->update($data);
            if ($roleIds !== null) $model->roles()->sync($roleIds);
            if ($campaignIds !== null) $model->campaigns()->sync($campaignIds);
            if ($branchIds !== null) $model->branches()->sync($branchIds);
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Usuario actualizado.', 'datos' => new UserResource($model->fresh(['roles.permissions', 'campaigns', 'branches']))]);
    }

    #[OA\Delete(path: '/api/v1/users/{user}', operationId: 'deactivateUser', tags: ['Usuarios'], summary: 'Desactivar usuario', responses: [new OA\Response(response: 200, description: 'Usuario desactivado')])]
    public function destroy(Request $request, int $user): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($user);
        abort_if($model->is($request->user()), 422, 'No puedes desactivar tu propio usuario.');
        $this->ensureNotRemovingLastAdmin($model, []);

        $model->update(['is_active' => false]);
        $model->tokens()->delete();
        $model->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Usuario desactivado.', 'datos' => null]);
    }

    #[OA\Get(path: '/api/v1/roles', operationId: 'listRoles', tags: ['Usuarios'], summary: 'Listar roles', responses: [new OA\Response(response: 200, description: 'Roles obtenidos')])]
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->where('is_active', true)->when(request()->filled('search'), fn ($query) => $query->where(fn ($sub) => $sub->where('name', 'like', '%'.request()->string('search')->toString().'%')->orWhere('slug', 'like', '%'.request()->string('search')->toString().'%')->orWhere('description', 'like', '%'.request()->string('search')->toString().'%')))->tap(fn ($query) => $this->applySorting($query, request(), ['name' => 'name', 'slug' => 'slug', 'created_at' => 'created_at'], 'name'))->get();
        return response()->json(['codigo' => 200, 'mensaje' => 'Roles obtenidos.', 'datos' => RoleResource::collection($roles)]);
    }

    #[OA\Get(path: '/api/v1/permissions', operationId: 'listPermissions', tags: ['Usuarios'], summary: 'Listar permisos', responses: [new OA\Response(response: 200, description: 'Permisos obtenidos')])]
    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('is_active', true)->when(request()->filled('search'), fn ($query) => $query->where(fn ($sub) => $sub->where('name', 'like', '%'.request()->string('search')->toString().'%')->orWhere('slug', 'like', '%'.request()->string('search')->toString().'%')))->when(request()->filled('module'), fn ($query) => $query->where('module', request()->string('module')->toString()))->when(request()->filled('action'), fn ($query) => $query->where('action', request()->string('action')->toString()))->tap(fn ($query) => $this->applySorting($query, request(), ['module' => 'module', 'action' => 'action', 'name' => 'name', 'slug' => 'slug'], 'module'))->get();
        return response()->json(['codigo' => 200, 'mensaje' => 'Permisos obtenidos.', 'datos' => PermissionResource::collection($permissions)]);
    }

    private function visibleQuery(User $actor): Builder
    {
        $query = User::query()->where('is_active', true);
        if (! $actor->hasRole('super_admin')) {
            $query->where(function (Builder $scope) use ($actor): void {
                $scope->whereHas('campaigns.users', fn (Builder $relation) => $relation->whereKey($actor->id))
                    ->orWhereHas('branches.users', fn (Builder $relation) => $relation->whereKey($actor->id));
            });
        }

        return $query;
    }

    private function ensureRolesCanBeAssigned(User $actor, array $roleIds): void
    {
        $containsAdmin = Role::whereIn('id', $roleIds)->where('slug', 'super_admin')->exists();
        abort_if($containsAdmin && ! $actor->hasRole('super_admin'), 403, 'Solo un super administrador puede asignar ese rol.');
    }

    private function ensureNotRemovingLastAdmin(User $target, array $newRoleIds): void
    {
        if (! $target->hasRole('super_admin') || ($newRoleIds !== [] && Role::whereIn('id', $newRoleIds)->where('slug', 'super_admin')->exists())) {
            return;
        }

        $admins = User::where('is_active', true)->whereHas('roles', fn (Builder $query) => $query->where('roles.slug', 'super_admin'))->count();
        abort_if($admins <= 1, 422, 'No se puede retirar el último super administrador.');
    }

    private function ensureScopesAreVisible(User $actor, array $campaignIds, array $branchIds): void
    {
        if ($actor->hasRole('super_admin')) return;

        $campaignCount = Campaign::whereIn('id', $campaignIds)->whereHas('users', fn (Builder $query) => $query->whereKey($actor->id))->count();
        $branchCount = Branch::whereIn('id', $branchIds)->whereHas('users', fn (Builder $query) => $query->whereKey($actor->id))->count();
        abort_if($campaignCount !== count(array_unique($campaignIds)) || $branchCount !== count(array_unique($branchIds)), 403, 'El ámbito asignado está fuera de tus permisos.');
    }
}
