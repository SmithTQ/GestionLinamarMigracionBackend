<?php

namespace App\Http\Controllers\Api\V1\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Routes\PublicDeliveryConfirmationRequest;
use App\Http\Resources\Api\V1\Dispatch\PublicDeliveryRouteResource;
use App\Models\OrderDeliveryEvidence;
use App\Services\Dispatch\PublicDeliveryConfirmationService;
use App\Services\Dispatch\PublicRouteAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class PublicDeliveryRouteController extends Controller
{
    #[OA\Get(path: '/api/v1/public/routes/{token}', operationId: 'showPublicRoute', tags: ['Public routes'], summary: 'Consultar ruta mediante token seguro', responses: [new OA\Response(response: 200, description: 'Ruta publica obtenida')])]
    public function show(string $token, PublicRouteAccessService $access): JsonResponse
    {
        $accessToken = $access->resolve($token, [
            'route.courier',
        ]);
        $accessToken->route->load(['orders' => fn ($query) => $query
            ->with(['product', 'deliveryEvidences.courier'])
            ->where('route_order.is_active', true)
            ->orderBy('route_order.sort_order')
            ->orderBy('route_order.position')]);

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Ruta publica obtenida.',
            'datos' => new PublicDeliveryRouteResource($accessToken->route),
        ]);
    }

    #[OA\Post(path: '/api/v1/public/routes/{token}/orders/{order}/delivery-confirmation', operationId: 'confirmPublicDelivery', tags: ['Public routes'], summary: 'Confirmar entrega con evidencia', responses: [new OA\Response(response: 200, description: 'Entrega confirmada')])]
    public function confirmDelivery(
        PublicDeliveryConfirmationRequest $request,
        string $token,
        int $order,
        PublicRouteAccessService $access,
        PublicDeliveryConfirmationService $confirmation
    ): JsonResponse {
        $accessToken = $access->resolve($token);
        $evidence = $confirmation->execute($accessToken, $order, $request->file('evidence'), $request->validated('note'));
        $accessToken->load(['route.courier']);
        $accessToken->route->load(['orders' => fn ($query) => $query
            ->with(['product', 'deliveryEvidences.courier'])
            ->where('route_order.is_active', true)
            ->orderBy('route_order.sort_order')
            ->orderBy('route_order.position')]);

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Entrega confirmada correctamente.',
            'datos' => new PublicDeliveryRouteResource($accessToken->route),
            'evidencia_id' => $evidence->id,
        ]);
    }

    public function evidence(string $token, int $order, PublicRouteAccessService $access): mixed
    {
        $accessToken = $access->resolve($token);
        $evidence = OrderDeliveryEvidence::query()
            ->where('delivery_route_id', $accessToken->delivery_route_id)
            ->where('order_id', $order)
            ->firstOrFail();
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404, 'La evidencia de entrega no esta disponible.');

        $stream = Storage::disk($evidence->disk)->readStream($evidence->path);
        abort_unless(is_resource($stream), 404, 'La evidencia de entrega no esta disponible.');

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $evidence->mime_type,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
