<?php

namespace App\Services\Dispatch;

use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderDeliveryEvidence;
use App\Models\RouteAccessToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicDeliveryConfirmationService
{
    public function execute(RouteAccessToken $accessToken, int $orderId, UploadedFile $file, ?string $note = null): OrderDeliveryEvidence
    {
        $storedPath = null;
        $diskName = config('forms.file_disk', 'local');

        try {
            return DB::transaction(function () use ($accessToken, $orderId, $file, $note, $diskName, &$storedPath): OrderDeliveryEvidence {
                /** @var DeliveryRoute $route */
                $route = DeliveryRoute::query()->lockForUpdate()->findOrFail($accessToken->delivery_route_id);
                abort_if(in_array($route->status, ['cancelled', 'completed'], true), 422, 'La ruta ya no permite confirmar entregas.');

                /** @var Order $order */
                $order = Order::query()->lockForUpdate()->findOrFail($orderId);
                $stop = DB::table('route_order')
                    ->where('route_id', $route->id)
                    ->where('order_id', $order->id)
                    ->where('is_active', true)
                    ->first();
                abort_unless($stop !== null, 422, 'El pedido no pertenece a una parada activa de esta ruta.');
                abort_if(! $order->is_active || $order->status === 'cancelled', 422, 'El pedido no permite confirmar una entrega.');

                $existing = OrderDeliveryEvidence::query()
                    ->where('delivery_route_id', $route->id)
                    ->where('order_id', $order->id)
                    ->with('courier')
                    ->first();
                if ($order->status === 'delivered' && $existing !== null) {
                    return $existing;
                }

                abort_unless(in_array($order->status, ['planned', 'assigned', 'in_transit'], true), 422, 'El estado actual del pedido no permite confirmar la entrega.');

                $storedPath = $file->store('delivery-evidence/'.$route->id, $diskName);
                abort_unless($storedPath !== false, 422, 'No se pudo almacenar la evidencia de entrega.');

                $evidence = OrderDeliveryEvidence::create([
                    'order_id' => $order->id,
                    'delivery_route_id' => $route->id,
                    'delivered_by_courier_id' => $route->courier_id,
                    'disk' => $diskName,
                    'path' => $storedPath,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'note' => $note,
                    'delivered_at' => now(),
                ]);
                $order->update(['status' => 'delivered']);

                return $evidence->load('courier');
            });
        } catch (\Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk($diskName)->delete($storedPath);
            }

            throw $exception;
        }
    }
}
