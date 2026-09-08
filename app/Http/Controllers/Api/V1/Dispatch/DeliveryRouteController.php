<?php

namespace App\Http\Controllers\Api\V1\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Routes\AssignCourierRequest;
use App\Http\Requests\Api\V1\Routes\AttachOrdersRequest;
use App\Http\Requests\Api\V1\Routes\ChangeRouteStatusRequest;
use App\Http\Requests\Api\V1\Routes\StoreRouteRequest;
use App\Http\Requests\Api\V1\Routes\UpdateRouteRequest;
use App\Http\Resources\Api\V1\Dispatch\DeliveryRouteResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Courier;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Rutas', description: 'Planificación, asignación y despacho')]
class DeliveryRouteController extends Controller
{
    #[OA\Get(path: '/api/v1/routes', operationId: 'listRoutes', tags: ['Rutas'], summary: 'Listar rutas', responses: [new OA\Response(response: 200, description: 'Rutas obtenidas')])]
    public function index(Request $request): JsonResponse
    {
        $routes = $this->visibleQuery($request->user())->with(['campaign', 'branch', 'courier'])
            ->withCount(['orders as active_orders_count' => fn ($query) => $query->where('route_order.is_active', true)])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($subQuery) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $subQuery->where('code', 'like', $value)->orWhere('name', 'like', $value)->orWhere('description', 'like', $value);
            }))
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('courier_id'), fn ($query) => $query->where('courier_id', $request->integer('courier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('dispatched_from'), fn ($query) => $query->whereDate('dispatched_at', '>=', $request->date('dispatched_from')))
            ->when($request->filled('dispatched_to'), fn ($query) => $query->whereDate('dispatched_at', '<=', $request->date('dispatched_to')))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['id' => 'id', 'code' => 'code', 'name' => 'name', 'status' => 'status', 'dispatched_at' => 'dispatched_at', 'created_at' => 'created_at'], 'id', 'desc'))
            ->paginate(min($request->integer('per_page', 20), 100));
        return $this->paginatedResponse('Rutas obtenidas.', $routes, DeliveryRouteResource::class);
    }

    #[OA\Post(path: '/api/v1/routes', operationId: 'createRoute', tags: ['Rutas'], summary: 'Crear ruta', responses: [new OA\Response(response: 201, description: 'Ruta creada')])]
    public function store(StoreRouteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $campaign = Campaign::findOrFail($data['campaign_id']);
        $branch = Branch::findOrFail($data['branch_id']);
        $this->ensureScope($request->user(), $campaign, $branch);
        abort_if($campaign->status !== 'open', 422, 'La campaña debe estar abierta.');

        $route = DeliveryRoute::create($data);
        return response()->json(['codigo' => 201, 'mensaje' => 'Ruta creada.', 'datos' => new DeliveryRouteResource($route->load(['campaign', 'branch']))], 201);
    }

    #[OA\Get(path: '/api/v1/routes/{route}', operationId: 'showRoute', tags: ['Rutas'], summary: 'Consultar ruta', responses: [new OA\Response(response: 200, description: 'Ruta obtenida')])]
    public function show(Request $request, int $route): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->with(['campaign', 'branch', 'courier', 'orders'])->findOrFail($route);
        return response()->json(['codigo' => 200, 'mensaje' => 'Ruta obtenida.', 'datos' => new DeliveryRouteResource($model)]);
    }

    #[OA\Patch(path: '/api/v1/routes/{route}', operationId: 'updateRoute', tags: ['Rutas'], summary: 'Actualizar ruta', responses: [new OA\Response(response: 200, description: 'Ruta actualizada')])]
    public function update(UpdateRouteRequest $request, int $route): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($route);
        abort_if(in_array($model->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite cambios.');
        $model->update($request->validated());
        return response()->json(['codigo' => 200, 'mensaje' => 'Ruta actualizada.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier']))]);
    }

    #[OA\Patch(path: '/api/v1/routes/{route}/courier', operationId: 'assignCourier', tags: ['Rutas'], summary: 'Asignar motorizado', responses: [new OA\Response(response: 200, description: 'Motorizado asignado')])]
    public function assignCourier(AssignCourierRequest $request, int $route): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($route);
        abort_if(in_array($model->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite asignaciones.');
        $courier = Courier::where('is_active', true)->where('is_available', true)->findOrFail($request->integer('courier_id'));
        abort_unless($courier->branches()->whereKey($model->branch_id)->exists() || $courier->branches()->count() === 0, 422, 'El motorizado no está habilitado para la sucursal.');
        $model->update(['courier_id' => $courier->id, 'status' => 'assigned']);
        return response()->json(['codigo' => 200, 'mensaje' => 'Motorizado asignado.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier']))]);
    }

    #[OA\Post(path: '/api/v1/routes/{route}/orders', operationId: 'attachOrders', tags: ['Rutas'], summary: 'Agregar pedidos a una ruta', responses: [new OA\Response(response: 200, description: 'Pedidos agregados')])]
    public function attachOrders(AttachOrdersRequest $request, int $route): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->findOrFail($route);
        abort_if(in_array($model->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite pedidos.');
        $orderIds = $request->input('order_ids');

        DB::transaction(function () use ($model, $orderIds): void {
            $orders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();
            abort_if($orders->count() !== count($orderIds), 404, 'Uno de los pedidos no existe.');
            foreach ($orders as $order) {
                abort_unless($order->campaign_id === $model->campaign_id && $order->branch_id === $model->branch_id, 422, 'Todos los pedidos deben pertenecer a la campaña y sucursal de la ruta.');
                abort_if($order->status === 'cancelled' || ! $order->is_active, 422, 'No se puede agregar un pedido inactivo o cancelado.');
                abort_if($order->routes()->wherePivot('is_active', true)->exists(), 422, 'Un pedido ya pertenece a otra ruta activa.');
                $model->orders()->syncWithoutDetaching([$order->id => ['is_active' => true, 'assigned_at' => now(), 'removed_at' => null]]);
                $order->update(['status' => 'planned']);
            }
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Pedidos agregados a la ruta.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier', 'orders']))]);
    }

    #[OA\Patch(path: '/api/v1/routes/{route}/status', operationId: 'changeRouteStatus', tags: ['Rutas'], summary: 'Cambiar estado de ruta', responses: [new OA\Response(response: 200, description: 'Estado actualizado')])]
    public function changeStatus(ChangeRouteStatusRequest $request, int $route): JsonResponse
    {
        $model = $this->visibleQuery($request->user())->withCount(['orders as active_orders_count' => fn ($query) => $query->where('route_order.is_active', true)])->findOrFail($route);
        $next = $request->string('status')->toString();
        $allowed = ['draft' => ['planned', 'cancelled'], 'planned' => ['assigned', 'cancelled'], 'assigned' => ['dispatched', 'cancelled'], 'dispatched' => ['completed'], 'completed' => [], 'cancelled' => []];
        abort_unless(in_array($next, $allowed[$model->status] ?? [], true), 422, 'La transición de estado no está permitida.');
        if ($next === 'dispatched') abort_if(! $model->courier_id || $model->active_orders_count < 1, 422, 'La ruta necesita motorizado y al menos un pedido.');
        $model->update(['status' => $next, 'dispatched_at' => $next === 'dispatched' ? now() : $model->dispatched_at]);
        return response()->json(['codigo' => 200, 'mensaje' => 'Estado de ruta actualizado.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier']))]);
    }

    private function visibleQuery(User $actor): Builder
    {
        $query = DeliveryRoute::query();
        if (! $actor->hasRole('super_admin')) {
            $query->whereHas('campaign.users', fn ($relation) => $relation->whereKey($actor->id))->whereHas('branch.users', fn ($relation) => $relation->whereKey($actor->id));
        }
        return $query;
    }

    private function ensureScope(User $actor, Campaign $campaign, Branch $branch): void
    {
        abort_unless($campaign->branches()->whereKey($branch->id)->exists(), 422, 'La sucursal no pertenece a la campaña.');
        if (! $actor->hasRole('super_admin')) abort_unless($campaign->users()->whereKey($actor->id)->exists() && $branch->users()->whereKey($actor->id)->exists(), 403, 'La ruta está fuera de tu ámbito.');
    }
}
