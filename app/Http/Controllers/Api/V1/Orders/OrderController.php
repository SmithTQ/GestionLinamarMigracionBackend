<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\ChangeOrderStatusRequest;
use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Http\Requests\Api\V1\Orders\UpdateOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryDateNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Pedidos', description: 'Pedidos, filtros y transiciones de estado')]
class OrderController extends Controller
{
    #[OA\Get(path: '/api/v1/orders', operationId: 'listOrders', tags: ['Pedidos'], summary: 'Listar pedidos', responses: [new OA\Response(response: 200, description: 'Pedidos obtenidos')])]
    public function index(Request $request): JsonResponse
    {
        $deliveryDate = null;
        if ($request->has('delivery_date')) {
            try {
                $deliveryDate = DeliveryDateNormalizer::normalize($request->input('delivery_date'));
            } catch (InvalidArgumentException) {
                abort(422, 'El formato de delivery_date no es válido.');
            }
        }

        $orders = $this->visibleQuery($request->user())
            ->with(['campaign', 'branch', 'districtCatalog', 'product', 'formSubmission.form.fields', 'formSubmission.files.field'])
            ->when($request->filled('campaign_id'), fn (Builder $query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('branch_id'), fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('district'), fn (Builder $query) => $query->where('district', 'like', '%'.$request->string('district')->toString().'%'))
            ->when($deliveryDate !== null, fn (Builder $query) => $query->whereDate('delivery_date', $deliveryDate))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['id' => 'id', 'order_number' => 'order_number', 'product_name' => 'product_name', 'recipient_name' => 'recipient_name', 'district' => 'district', 'delivery_date' => 'delivery_date', 'status' => 'status', 'created_at' => 'created_at'], 'id', 'desc'))
            ->paginate(min($request->integer('per_page', 20), 100));

        return $this->paginatedResponse('Pedidos obtenidos.', $orders, OrderResource::class);
    }

    #[OA\Post(path: '/api/v1/orders', operationId: 'createOrder', tags: ['Pedidos'], summary: 'Crear pedido', responses: [new OA\Response(response: 201, description: 'Pedido creado'), new OA\Response(response: 200, description: 'Pedido ya existente por idempotencia')])]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $campaign = Campaign::findOrFail($data['campaign_id']);
        $branch = Branch::findOrFail($data['branch_id']);
        $this->ensureOrderScope($request->user(), $campaign, $branch);
        $this->ensureDistrictBelongsToCampaign($campaign, $data['district_id'] ?? null);
        $this->applyCampaignProduct($campaign, $data);
        abort_if($campaign->status !== 'open', 422, 'La campaña no está abierta para registrar pedidos.');

        if (! empty($data['external_source']) && ! empty($data['external_key'])) {
            $existing = Order::withTrashed()->where($this->sourceKey($data))->first();
            if ($existing) {
                return response()->json(['codigo' => 200, 'mensaje' => 'El pedido ya existía y no fue duplicado.', 'datos' => new OrderResource($existing->load(['campaign', 'branch', 'districtCatalog']))]);
            }
        }

        $order = DB::transaction(fn (): Order => Order::create($data));

        return response()->json(['codigo' => 201, 'mensaje' => 'Pedido creado.', 'datos' => new OrderResource($order->load(['campaign', 'branch', 'districtCatalog']))], 201);
    }

    #[OA\Get(path: '/api/v1/orders/{order}', operationId: 'showOrder', tags: ['Pedidos'], summary: 'Consultar pedido', responses: [new OA\Response(response: 200, description: 'Pedido obtenido')])]
    public function show(Request $request, int $order): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->with(['campaign', 'branch', 'districtCatalog', 'formSubmission.form.fields', 'formSubmission.files.field'])->findOrFail($order);

        return response()->json(['codigo' => 200, 'mensaje' => 'Pedido obtenido.', 'datos' => new OrderResource($model)]);
    }

    #[OA\Patch(path: '/api/v1/orders/{order}', operationId: 'updateOrder', tags: ['Pedidos'], summary: 'Actualizar datos operativos', responses: [new OA\Response(response: 200, description: 'Pedido actualizado')])]
    public function update(UpdateOrderRequest $request, int $order): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->with('campaign')->findOrFail($order);
        abort_if(in_array($model->status, ['delivered', 'cancelled'], true), 422, 'El pedido no admite cambios en su estado actual.');
        abort_if($model->campaign->status !== 'open', 422, 'La campaña no está abierta para modificar pedidos.');

        $data = $request->validated();
        $this->ensureDistrictBelongsToCampaign($model->campaign, $data['district_id'] ?? null);
        $model->update($data);

        return response()->json(['codigo' => 200, 'mensaje' => 'Pedido actualizado.', 'datos' => new OrderResource($model->fresh(['campaign', 'branch', 'districtCatalog']))]);
    }

    #[OA\Patch(path: '/api/v1/orders/{order}/status', operationId: 'changeOrderStatus', tags: ['Pedidos'], summary: 'Cambiar estado del pedido', responses: [new OA\Response(response: 200, description: 'Estado actualizado')])]
    public function changeStatus(ChangeOrderStatusRequest $request, int $order): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($order);
        $next = $request->string('status')->toString();
        $allowed = [
            'pending' => ['validated', 'cancelled'],
            'validated' => ['planned', 'cancelled'],
            'planned' => ['assigned', 'cancelled'],
            'assigned' => ['in_transit', 'cancelled'],
            'in_transit' => ['delivered', 'failed'],
            'failed' => ['in_transit', 'cancelled'],
            'delivered' => [],
            'cancelled' => [],
        ];

        abort_unless(in_array($next, $allowed[$model->status] ?? [], true), 422, 'La transición de estado no está permitida.');
        $model->update(['status' => $next]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Estado del pedido actualizado.', 'datos' => new OrderResource($model->fresh(['campaign', 'branch']))]);
    }

    #[OA\Delete(path: '/api/v1/orders/{order}', operationId: 'archiveOrder', tags: ['Pedidos'], summary: 'Inhabilitar pedido', responses: [new OA\Response(response: 200, description: 'Pedido inhabilitado')])]
    public function destroy(Request $request, int $order): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($order);
        abort_if(in_array($model->status, ['delivered', 'cancelled'], true), 422, 'El pedido no puede inhabilitarse en su estado actual.');
        $model->update(['is_active' => false]);
        $model->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Pedido inhabilitado.', 'datos' => null]);
    }

    private function visibleQuery(User $actor): Builder
    {
        $query = Order::query()->where('is_active', true);
        if (! $actor->hasRole('super_admin')) {
            $query->whereHas('campaign.users', fn (Builder $relation) => $relation->whereKey($actor->id))
                ->whereHas('branch.users', fn (Builder $relation) => $relation->whereKey($actor->id));
        }

        return $query;
    }

    private function ensureOrderScope(User $actor, Campaign $campaign, Branch $branch): void
    {
        abort_unless($campaign->branches()->whereKey($branch->id)->exists(), 422, 'La sucursal no pertenece a la campaña.');
        if (! $actor->hasRole('super_admin')) {
            abort_unless($campaign->users()->whereKey($actor->id)->exists() && $branch->users()->whereKey($actor->id)->exists(), 403, 'El pedido está fuera de tu ámbito.');
        }
    }

    private function sourceKey(array $data): array
    {
        return [
            'campaign_id' => $data['campaign_id'],
            'external_source' => $data['external_source'],
            'external_key' => $data['external_key'],
        ];
    }

    private function ensureDistrictBelongsToCampaign(Campaign $campaign, ?int $districtId): void
    {
        if ($districtId === null) {
            return;
        }

        abort_unless(
            $campaign->districtLists()
                ->whereHas('districts', fn ($query) => $query->whereKey($districtId)->where('districts.is_active', true))
                ->exists(),
            422,
            'El distrito no pertenece a la cobertura de la campaña.'
        );
    }

    private function applyCampaignProduct(Campaign $campaign, array &$data): void
    {
        if (empty($data['product_id'])) {
            return;
        }

        $product = Product::findOrFail($data['product_id']);
        $campaignProduct = $campaign->products()->whereKey($product->id)->wherePivot('is_available', true)->first();
        abort_unless($campaignProduct, 422, 'El producto no está disponible en la campaña.');
        $data['product_name'] = $data['product_name'] ?? $product->name;
        $data['product_price'] = $data['product_price'] ?? $campaignProduct->pivot->price ?? $product->base_price;
    }
}
