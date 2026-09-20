<?php

namespace App\Services\Authorization;

use App\Models\DeliveryRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteCancellationService
{
    public function cancel(DeliveryRoute $route): DeliveryRoute
    {
        return DB::transaction(function () use ($route): DeliveryRoute {
            /** @var DeliveryRoute $lockedRoute */
            $lockedRoute = DeliveryRoute::query()->lockForUpdate()->findOrFail($route->id);

            if (! in_array($lockedRoute->status, ['draft', 'planned', 'assigned'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'La ruta solo puede cancelarse cuando está en estado borrador, planificada o asignada.',
                ]);
            }

            $orderIds = DB::table('route_order')
                ->where('route_id', $lockedRoute->id)
                ->where('is_active', true)
                ->pluck('order_id');

            DB::table('route_order')
                ->where('route_id', $lockedRoute->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'removed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($orderIds->isNotEmpty()) {
                $ordersWithAnotherActiveRoute = DB::table('route_order')
                    ->whereIn('order_id', $orderIds)
                    ->where('is_active', true)
                    ->pluck('order_id');
                $releasableOrderIds = $orderIds->diff($ordersWithAnotherActiveRoute);

                if ($releasableOrderIds->isNotEmpty()) {
                    DB::table('orders')
                        ->whereIn('id', $releasableOrderIds)
                        ->where('is_active', true)
                        ->whereNotIn('status', ['cancelled', 'delivered'])
                        ->update(['status' => 'pending', 'updated_at' => now()]);
                }
            }

            $lockedRoute->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return $lockedRoute->fresh(['campaign', 'branch', 'courier']);
        });
    }
}
