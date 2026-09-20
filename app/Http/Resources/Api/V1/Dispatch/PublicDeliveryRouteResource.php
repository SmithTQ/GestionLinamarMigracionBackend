<?php

namespace App\Http\Resources\Api\V1\Dispatch;

use App\Models\Courier;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderDeliveryEvidence;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryRoute */
class PublicDeliveryRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $token = (string) $request->route('token');

        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'navigation_url' => $this->navigation_url,
            'courier' => $this->whenLoaded('courier', function (): ?array {
                /** @var Courier|null $courier */
                $courier = $this->courier;

                return $courier === null ? null : [
                    'name' => $courier->name,
                    'phone' => $courier->phone,
                ];
            }),
            'orders' => $this->whenLoaded('orders', function () use ($token): array {
                $orders = [];
                foreach ($this->orders as $model) {
                    /** @var Order $order */
                    $order = $model;
                    $pivot = $order->getRelation('pivot');
                    /** @var Product|null $product */
                    $product = $order->relationLoaded('product') ? $order->product : null;
                    $candidate = $order->relationLoaded('deliveryEvidences')
                        ? $order->deliveryEvidences->firstWhere('delivery_route_id', $this->id)
                        : null;
                    $evidence = $candidate instanceof OrderDeliveryEvidence ? $candidate : null;

                    $orders[] = [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'sort_order' => $pivot?->getAttribute('sort_order') ?? $pivot?->getAttribute('position'),
                        'status' => $order->status,
                        'recipient_name' => $order->recipient_name,
                        'recipient_phone' => $order->recipient_phone,
                        'product' => $product === null ? null : [
                            'id' => $product->id,
                            'sku' => $product->sku,
                            'name' => $product->name,
                            'image_thumbnail_url' => $product->image_thumbnail_url,
                            'image_url' => $product->image_url,
                        ],
                        'sender' => [
                            'name' => $order->sender_name,
                            'phone' => $order->sender_phone,
                        ],
                        'recipient' => [
                            'name' => $order->recipient_name,
                            'phone' => $order->recipient_phone,
                        ],
                        'district' => $order->district,
                        'address' => $order->address,
                        'delivery_reference' => $order->delivery_reference,
                        'latitude' => $order->latitude,
                        'longitude' => $order->longitude,
                        'delivery_date' => $this->formatDate($order->delivery_date),
                        'delivery_time' => $order->delivery_time,
                        'delivery_evidence' => $this->evidenceData($evidence, $token, $order->id),
                    ];
                }

                return $orders;
            }),
        ];
    }

    private function evidenceData(?OrderDeliveryEvidence $evidence, string $token, int $orderId): ?array
    {
        if ($evidence === null) {
            return null;
        }

        $deliveredAtValue = $evidence->getAttribute('delivered_at');
        $deliveredAt = $deliveredAtValue === null
            ? null
            : CarbonImmutable::parse((string) $deliveredAtValue);
        /** @var Courier|null $courier */
        $courier = $evidence->courier;

        return [
            'id' => $evidence->id,
            'url' => $evidence->url($token, $orderId),
            'thumbnail_url' => $evidence->thumbnail_path === null ? null : $evidence->url($token, $orderId),
            'delivered_at' => $deliveredAt === null ? null : $deliveredAt->format('d/m/Y H:i'),
            'delivered_at_iso' => $deliveredAt === null ? null : $deliveredAt->toISOString(),
            'delivered_by_courier_id' => $evidence->delivered_by_courier_id,
            'delivered_by_courier_name' => $courier?->name,
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->format('Y-m-d') : ($value === null ? null : (string) $value);
    }
}
