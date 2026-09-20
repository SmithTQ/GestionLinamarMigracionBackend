<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class OperationalScopeService
{
    public function requiresBranchFilter(User $user): bool
    {
        return $user->hasRole('campaign_manager');
    }

    public function validatedBranchId(User $user, ?int $branchId): ?int
    {
        if ($branchId === null && $this->requiresBranchFilter($user)) {
            throw ValidationException::withMessages(['branch_id' => 'Debes seleccionar una sucursal para consultar este listado.']);
        }

        if ($branchId !== null && ! $this->canAccessBranch($user, $branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'La sucursal seleccionada no pertenece a tu ámbito operativo.']);
        }

        return $branchId;
    }

    public function visibleBranches(User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return Branch::query();
        }

        $isManager = $user->hasRole('campaign_manager');
        $isDispatcher = $user->hasRole('dispatcher');

        if (! $isManager && ! $isDispatcher) {
            return Branch::query()->whereRaw('1 = 0');
        }

        return Branch::query()->where(function (Builder $query) use ($user): void {
            if ($user->hasRole('campaign_manager')) {
                $query->whereHas('users', fn (Builder $relation) => $relation->whereKey($user->id));
            }

            if ($user->hasRole('dispatcher')) {
                $query->orWhereHas('campaigns.users', fn (Builder $relation) => $relation
                    ->whereKey($user->id)
                    ->where('campaign_user.is_active', true));
            }
        });
    }

    public function visibleCampaigns(User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return Campaign::query();
        }

        $isManager = $user->hasRole('campaign_manager');
        $isDispatcher = $user->hasRole('dispatcher');

        if (! $isManager && ! $isDispatcher) {
            return Campaign::query()->whereRaw('1 = 0');
        }

        return Campaign::query()->where(function (Builder $query) use ($user): void {
            if ($user->hasRole('campaign_manager')) {
                $query->whereHas('branch.users', fn (Builder $relation) => $relation->whereKey($user->id));
            }

            if ($user->hasRole('dispatcher')) {
                $query->orWhereHas('users', fn (Builder $relation) => $relation
                    ->whereKey($user->id)
                    ->where('campaign_user.is_active', true));
            }
        });
    }

    public function canAccessBranch(User $user, Branch|int $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        return $this->visibleBranches($user)->whereKey($branchId)->exists();
    }

    public function canAccessCampaign(User $user, Campaign|int $campaign): bool
    {
        $campaignId = $campaign instanceof Campaign ? $campaign->getKey() : $campaign;

        return $this->visibleCampaigns($user)->whereKey($campaignId)->exists();
    }
}
