<?php

namespace App\Services\Dispatch;

use App\Models\Courier;
use App\Models\DeliveryRoute;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RouteCourierInvitationService
{
    /**
     * @return array{courier: Courier, route: DeliveryRoute, public_url: string, whatsapp_url: string, expires_at: CarbonImmutable}
     */
    public function execute(int $routeId, string $name, string $phone, ?string $expiresAt = null): array
    {
        return DB::transaction(function () use ($routeId, $name, $phone, $expiresAt): array {
            /** @var DeliveryRoute $route */
            $route = DeliveryRoute::query()->with('branch')->lockForUpdate()->findOrFail($routeId);
            abort_if(in_array($route->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite asignar un motorizado.');
            abort_unless($route->branch !== null, 422, 'La ruta no tiene una sucursal configurada.');

            /** @var Courier|null $courier */
            $courier = Courier::query()->where('phone', $phone)->where('is_active', true)->lockForUpdate()->first();
            if ($courier === null) {
                $courier = Courier::create(['name' => $name, 'phone' => $phone, 'is_available' => true, 'is_active' => true]);
            } else {
                $branchIds = $courier->branches()->pluck('branches.id')->all();
                abort_if($branchIds !== [] && ! in_array($route->branch_id, $branchIds, true), 422, 'El motorizado pertenece a otra sucursal.');
                $courier->update(['name' => $name, 'is_available' => true]);
            }

            if (! $courier->branches()->whereKey($route->branch_id)->exists()) {
                $courier->branches()->attach($route->branch_id);
            }

            $route->update(['courier_id' => $courier->id, 'status' => 'assigned']);
            $route->accessTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $plainToken = Str::random(64);
            $expires = $expiresAt === null ? CarbonImmutable::now()->addDays(7) : CarbonImmutable::parse($expiresAt);
            $route->accessTokens()->create(['token_hash' => hash('sha256', $plainToken), 'expires_at' => $expires]);

            $publicUrl = rtrim((string) config('forms.frontend_url'), '/').'/ruta/'.$plainToken;
            $message = 'Hola '.$courier->name.'. Detalle de '.$route->code.' - '.$route->name.': '.$publicUrl;

            return [
                'courier' => $courier,
                'route' => $route->fresh(['branch']),
                'public_url' => $publicUrl,
                'whatsapp_url' => 'https://wa.me/'.$phone.'?text='.rawurlencode($message),
                'expires_at' => $expires,
            ];
        });
    }
}
