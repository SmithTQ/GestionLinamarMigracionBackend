<?php

namespace App\Services\Authorization;

use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserManagementScopeService
{
    public function usersQuery(User $actor, ?int $branchId = null): Builder
    {
        if ($actor->hasRole('super_admin')) {
            return User::query()->where('users.is_active', true);
        }

        $this->assertCampaignManager($actor);
        $branchId = $this->validatedActorBranch($actor, $branchId);

        return User::query()
            ->where('users.is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query
                ->where('roles.slug', 'dispatcher')
                ->where('roles.is_active', true))
            ->whereHas('branches', fn (Builder $query) => $query->whereKey($branchId));
    }

    public function validatedActorBranch(User $actor, ?int $branchId): int
    {
        $this->assertCampaignManager($actor);

        if ($branchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Debes seleccionar una sucursal para administrar usuarios.',
            ]);
        }

        if (! $this->canAccessBranch($actor, $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal seleccionada no pertenece a tu ámbito operativo.',
            ]);
        }

        return $branchId;
    }

    public function assertCanManageTarget(User $actor, User $target, ?int $contextBranchId = null): void
    {
        if ($actor->hasRole('super_admin')) {
            return;
        }

        $this->assertCampaignManager($actor);
        $this->assertDispatcher($target);
        $branchId = $this->validatedActorBranch($actor, $contextBranchId);

        if (! $target->branches()->whereKey($branchId)->exists()) {
            throw new AccessDeniedHttpException(
                'El Despachador no pertenece a la sucursal operativa seleccionada.',
            );
        }
    }

    public function assertCanAssignRoles(User $actor, array $roleIds): void
    {
        if ($actor->hasRole('super_admin')) {
            return;
        }

        $this->assertCampaignManager($actor);
        $dispatcherId = Role::query()->where('slug', 'dispatcher')->value('id');
        $normalized = array_values(array_unique(array_map('intval', $roleIds)));

        if ($dispatcherId === null || $normalized !== [(int) $dispatcherId]) {
            throw new AccessDeniedHttpException(
                'El Administrador operativo solo puede asignar el rol Despachador.',
            );
        }
    }

    public function assertSingleBranch(User $actor, array $branchIds): int
    {
        if ($actor->hasRole('super_admin')) {
            if (count($branchIds) !== 1) {
                throw ValidationException::withMessages(['branch_ids' => 'Debes asignar exactamente una sucursal.']);
            }

            return (int) $branchIds[0];
        }

        if (count($branchIds) !== 1) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Un Despachador debe pertenecer exactamente a una sucursal.',
            ]);
        }

        return $this->validatedActorBranch($actor, (int) $branchIds[0]);
    }

    public function assertDispatcher(User $target): void
    {
        if (! $target->hasRole('dispatcher')) {
            throw new AccessDeniedHttpException(
                'El Administrador operativo solo puede gestionar usuarios con rol Despachador.',
            );
        }
    }

    public function assertDispatcherMatchesCampaign(User $target, Campaign $campaign): void
    {
        $this->assertDispatcher($target);

        if (! $target->branches()->whereKey($campaign->branch_id)->exists()) {
            throw new AccessDeniedHttpException(
                'El Despachador debe pertenecer a la misma sucursal de la campaña.',
            );
        }
    }

    public function assertCampaignManager(User $actor): void
    {
        if (! $actor->hasRole('campaign_manager')) {
            throw new AccessDeniedHttpException(
                'No tienes permisos para gestionar usuarios operativos.',
            );
        }
    }

    public function canAccessBranch(User $actor, int $branchId): bool
    {
        return $actor->branches()
            ->whereKey($branchId)
            ->where('branches.is_active', true)
            ->exists();
    }
}
