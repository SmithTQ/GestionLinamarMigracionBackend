<?php

namespace App\Http\Controllers\Api\V1\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Routes\AssignCourierRequest;
use App\Http\Requests\Api\V1\Routes\AttachOrdersRequest;
use App\Http\Requests\Api\V1\Routes\ChangeRouteStatusRequest;
use App\Http\Requests\Api\V1\Routes\CreateCourierInvitationRequest;
use App\Http\Requests\Api\V1\Routes\CreateRouteAccessTokenRequest;
use App\Http\Requests\Api\V1\Routes\EligibleOrdersRequest;
use App\Http\Requests\Api\V1\Routes\GenerateRouteRequest;
use App\Http\Requests\Api\V1\Routes\MapOrdersRequest;
use App\Http\Requests\Api\V1\Routes\StoreRouteRequest;
use App\Http\Requests\Api\V1\Routes\UpdateRouteRequest;
use App\Http\Requests\Api\V1\Routes\UpdateRouteStopsRequest;
use App\Http\Resources\Api\V1\Dispatch\DeliveryRouteResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Courier;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\RouteAccessToken;
use App\Models\User;
use App\Services\Authorization\OperationalScopeService;
use App\Services\Authorization\RouteCancellationService;
use App\Services\Dispatch\RouteCourierInvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Rutas', description: 'Planificación, asignación y despacho')]
class DeliveryRouteController extends Controller
{
    #[OA\Post(path: '/api/v1/routes/{route}/access-token', operationId: 'createRouteAccessToken', tags: ['Rutas'], summary: 'Crear enlace público seguro para una ruta', responses: [new OA\Response(response: 201, description: 'Enlace creado')])]
    public function createAccessToken(CreateRouteAccessTokenRequest $request, int $route): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($route);
        $plainToken = Str::random(64);
        $expiresAt = $request->date('expires_at') ?? now()->addDays(7);
        $model->accessTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        /** @var RouteAccessToken $accessToken */
        $accessToken = $model->accessTokens()->create(['token_hash' => hash('sha256', $plainToken), 'expires_at' => $expiresAt]);

        return response()->json(['codigo' => 201, 'mensaje' => 'Enlace seguro de ruta creado.', 'datos' => ['token' => $plainToken, 'public_url' => url('/api/v1/public/routes/'.$plainToken), 'expires_at' => $expiresAt->format('d/m/Y H:i'), 'expires_at_iso' => $expiresAt->toISOString()]], 201);
    }

    #[OA\Delete(path: '/api/v1/routes/{route}/access-token', operationId: 'revokeRouteAccessToken', tags: ['Rutas'], summary: 'Revocar enlace público de una ruta', responses: [new OA\Response(response: 200, description: 'Enlace revocado')])]
    public function revokeAccessToken(Request $request, int $route): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($route);
        $model->accessTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Enlaces públicos de la ruta revocados.', 'datos' => null]);
    }

    #[OA\Post(path: '/api/v1/routes/{route}/courier-invitations', operationId: 'createCourierRouteInvitation', tags: ['Rutas'], summary: 'Asignar motorizado y generar invitación pública de ruta', responses: [new OA\Response(response: 201, description: 'Motorizado asignado e invitación generada')])]
    public function createCourierInvitation(CreateCourierInvitationRequest $request, int $route, RouteCourierInvitationService $service): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($route);
        $data = $request->validated();
        $result = $service->execute($model->id, $data['name'], $data['whatsapp_number'], $data['expires_at'] ?? null);

        return response()->json([
            'codigo' => 201,
            'mensaje' => 'Motorizado asignado. Abre WhatsApp para compartir el detalle de la ruta.',
            'datos' => [
                'courier' => [
                    'id' => $result['courier']->id,
                    'name' => $result['courier']->name,
                    'phone' => $result['courier']->phone,
                ],
                'route' => [
                    'id' => $result['route']->id,
                    'code' => $result['route']->code,
                    'name' => $result['route']->name,
                    'status' => $result['route']->status,
                ],
                'public_url' => $result['public_url'],
                'whatsapp_url' => $result['whatsapp_url'],
                'expires_at' => $result['expires_at']->format('d/m/Y H:i'),
                'expires_at_iso' => $result['expires_at']->toISOString(),
            ],
        ], 201);
    }

    #[OA\Get(path: '/api/v1/routes/eligible-orders', operationId: 'listEligibleRouteOrders', tags: ['Rutas'], summary: 'Listar pedidos elegibles para enrutar', responses: [new OA\Response(response: 200, description: 'Pedidos elegibles obtenidos')])]
    public function eligibleOrders(EligibleOrdersRequest $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($request->integer('campaign_id'));
        /** @var Branch|null $branch */
        $branch = $campaign->branch;
        abort_unless($branch !== null, 422, 'La campaÃ±a no tiene una sucursal configurada.');
        $this->ensureScope($request->user(), $campaign, $branch, app(OperationalScopeService::class));

        $orders = Order::query()
            ->with(['campaign', 'branch', 'product', 'districtCatalog'])
            ->where('campaign_id', $campaign->id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereIn('status', ['pending', 'validated'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereDoesntHave('routes', function (Builder $query): void {
                $query->where('route_order.is_active', true);
            })
            ->when($request->filled('search'), fn ($query) => $query->where(function ($sub) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $sub->where('order_number', 'like', $value)->orWhere('recipient_name', 'like', $value)->orWhere('recipient_phone', 'like', $value)->orWhere('address', 'like', $value);
            }))
            ->when($request->filled('delivery_date'), fn ($query) => $query->whereDate('delivery_date', $request->date('delivery_date')))
            ->when($request->filled('district_id'), fn ($query) => $query->where('district_id', $request->integer('district_id')))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['id' => 'id', 'order_number' => 'order_number', 'recipient_name' => 'recipient_name', 'delivery_date' => 'delivery_date', 'created_at' => 'created_at'], 'delivery_date', 'asc'))
            ->paginate(min($request->integer('per_page', 20), 100));

        return $this->paginatedResponse('Pedidos elegibles obtenidos.', $orders, OrderResource::class);
    }

    #[OA\Get(path: '/api/v1/routes/map-orders', operationId: 'listMapRouteOrders', tags: ['Rutas'], summary: 'Listar pedidos ubicables para el mapa de rutas', parameters: [
        new OA\Parameter(name: 'campaign_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)),
        new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Maria')),
        new OA\Parameter(name: 'delivery_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-09-20')),
        new OA\Parameter(name: 'district_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
    ], responses: [new OA\Response(response: 200, description: 'Pedidos ubicables y resumen del mapa obtenidos')])]
    public function mapOrders(MapOrdersRequest $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($request->integer('campaign_id'));
        /** @var Branch|null $branch */
        $branch = $campaign->branch;
        abort_unless($branch !== null, 422, 'La campaña no tiene una sucursal configurada.');
        $this->ensureScope($request->user(), $campaign, $branch, app(OperationalScopeService::class));

        $query = $this->mapOrdersQuery($campaign, $request);
        $total = (clone $query)->count();
        $assigned = (clone $query)->whereHas('routes', function (Builder $routeQuery): void {
            $routeQuery->where('route_order.is_active', true);
        })->count();
        $pending = $total - $assigned;
        $orders = $query->paginate(min($request->integer('per_page', 20), 100));
        $serialized = OrderResource::collection($orders)->response()->getData(true);

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Pedidos del mapa obtenidos.',
            'datos' => $serialized['data'] ?? [],
            'resumen' => [
                'total' => $total,
                'pending' => $pending,
                'assigned' => $assigned,
            ],
            'paginacion' => $serialized['meta'] ?? null,
            'enlaces' => $serialized['links'] ?? null,
        ]);
    }

    #[OA\Post(path: '/api/v1/routes/generate', operationId: 'generateRoute', tags: ['Rutas'], summary: 'Generar ruta desde pedidos seleccionados', responses: [new OA\Response(response: 201, description: 'Ruta generada'), new OA\Response(response: 200, description: 'Solicitud ya procesada')])]
    public function generate(GenerateRouteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $campaign = Campaign::findOrFail($data['campaign_id']);
        /** @var Branch|null $branch */
        $branch = $campaign->branch;
        abort_unless($branch !== null, 422, 'La campaÃ±a no tiene una sucursal configurada.');
        $this->ensureScope($request->user(), $campaign, $branch, app(OperationalScopeService::class));
        abort_if($campaign->status !== 'open', 422, 'La campaña debe estar abierta.');
        abort_unless($branch->latitude !== null && $branch->longitude !== null, 422, 'La sucursal necesita coordenadas para generar la ruta.');

        $existing = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->where('request_key', $data['request_key'])->with(['campaign', 'branch', 'courier', 'orders'])->first();
        if ($existing) {
            return response()->json(['codigo' => 200, 'mensaje' => 'La ruta ya fue generada.', 'datos' => new DeliveryRouteResource($existing)], 200);
        }

        $sortOrders = collect($data['stops'])->pluck('sort_order')->sort()->values()->all();
        abort_unless($sortOrders === range(1, count($sortOrders)), 422, 'El orden de las paradas debe ser consecutivo y único.');

        $route = DB::transaction(function () use ($data, $campaign, $branch): DeliveryRoute {
            $orders = Order::whereIn('id', collect($data['stops'])->pluck('order_id'))->lockForUpdate()->get()->keyBy('id');
            $this->validateOrdersForRoute($orders, $data['stops'], $campaign, $branch);
            $route = DeliveryRoute::create([
                'campaign_id' => $campaign->id,
                'branch_id' => $branch->id,
                'code' => $this->nextRouteCode(),
                'request_key' => $data['request_key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'total_distance_km' => $data['estimated_distance_km'] ?? null,
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'status' => 'planned',
            ]);
            $this->syncRouteStops($route, $data['stops'], $orders);
            $route->update(['navigation_url' => $this->navigationUrl($branch, $orders, $data['stops'])]);

            return $route->fresh(['campaign', 'branch', 'courier', 'orders' => fn ($query) => $query->orderBy('route_order.sort_order')->orderBy('route_order.position')]);
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Ruta generada.', 'datos' => new DeliveryRouteResource($route)], 201);
    }

    #[OA\Put(path: '/api/v1/routes/{route}/stops', operationId: 'updateRouteStops', tags: ['Rutas'], summary: 'Modificar paradas de una ruta', responses: [new OA\Response(response: 200, description: 'Paradas actualizadas')])]
    public function updateStops(UpdateRouteStopsRequest $request, int $route): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->with(['branch', 'orders'])->findOrFail($route);
        /** @var Branch $modelBranch */
        $modelBranch = $model->getRelation('branch');
        abort_if(in_array($model->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite cambios de paradas.');
        abort_unless($modelBranch->latitude !== null && $modelBranch->longitude !== null, 422, 'La sucursal necesita coordenadas para modificar la ruta.');
        $data = $request->validated();
        $sortOrders = collect($data['stops'])->pluck('sort_order')->sort()->values()->all();
        abort_unless($sortOrders === range(1, count($sortOrders)), 422, 'El orden de las paradas debe ser consecutivo y único.');

        $updated = DB::transaction(function () use ($model, $data): DeliveryRoute {
            $locked = DeliveryRoute::with(['campaign', 'branch'])->lockForUpdate()->findOrFail($model->id);
            $orders = Order::whereIn('id', collect($data['stops'])->pluck('order_id'))->lockForUpdate()->get()->keyBy('id');
            /** @var Campaign $lockedCampaign */
            $lockedCampaign = $locked->getRelation('campaign');
            /** @var Branch $lockedBranch */
            $lockedBranch = $locked->getRelation('branch');
            $this->validateOrdersForRoute($orders, $data['stops'], $lockedCampaign, $lockedBranch, $locked->id);
            DB::table('route_order')->where('route_id', $locked->id)->where('is_active', true)->update(['is_active' => false, 'removed_at' => now(), 'updated_at' => now()]);
            $this->syncRouteStops($locked, $data['stops'], $orders);
            $locked->update(['total_distance_km' => $data['estimated_distance_km'] ?? $locked->total_distance_km, 'estimated_minutes' => $data['estimated_minutes'] ?? $locked->estimated_minutes, 'navigation_url' => $this->navigationUrl($lockedBranch, $orders, $data['stops'])]);

            return $locked->fresh(['campaign', 'branch', 'courier', 'orders' => fn ($query) => $query->orderBy('route_order.sort_order')->orderBy('route_order.position')]);
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Paradas actualizadas.', 'datos' => new DeliveryRouteResource($updated)]);
    }

    #[OA\Get(path: '/api/v1/routes', operationId: 'listRoutes', tags: ['Rutas'], summary: 'Listar rutas', responses: [new OA\Response(response: 200, description: 'Rutas obtenidas')])]
    public function index(Request $request): JsonResponse
    {
        $routes = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->with(['campaign', 'branch', 'courier'])
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
        $this->ensureScope($request->user(), $campaign, $branch, app(OperationalScopeService::class));
        abort_if($campaign->status !== 'open', 422, 'La campaña debe estar abierta.');

        $route = DeliveryRoute::create($data);

        return response()->json(['codigo' => 201, 'mensaje' => 'Ruta creada.', 'datos' => new DeliveryRouteResource($route->load(['campaign', 'branch']))], 201);
    }

    #[OA\Get(path: '/api/v1/routes/{route}', operationId: 'showRoute', tags: ['Rutas'], summary: 'Consultar ruta', responses: [new OA\Response(response: 200, description: 'Ruta obtenida')])]
    public function show(Request $request, int $route): JsonResponse
    {
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->with(['campaign', 'branch', 'courier', 'orders' => fn ($query) => $query->orderBy('route_order.sort_order')->orderBy('route_order.position')])->findOrFail($route);

        return response()->json(['codigo' => 200, 'mensaje' => 'Ruta obtenida.', 'datos' => new DeliveryRouteResource($model)]);
    }

    #[OA\Patch(path: '/api/v1/routes/{route}', operationId: 'updateRoute', tags: ['Rutas'], summary: 'Actualizar ruta', responses: [new OA\Response(response: 200, description: 'Ruta actualizada')])]
    public function update(UpdateRouteRequest $request, int $route): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($route);
        abort_if(in_array($model->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite cambios.');
        $model->update($request->validated());

        return response()->json(['codigo' => 200, 'mensaje' => 'Ruta actualizada.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier']))]);
    }

    #[OA\Patch(path: '/api/v1/routes/{route}/courier', operationId: 'assignCourier', tags: ['Rutas'], summary: 'Asignar motorizado', responses: [new OA\Response(response: 200, description: 'Motorizado asignado')])]
    public function assignCourier(AssignCourierRequest $request, int $route): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($route);
        abort_if(in_array($model->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite asignaciones.');
        $courier = Courier::where('is_active', true)->where('is_available', true)->findOrFail($request->integer('courier_id'));
        abort_unless($courier->branches()->whereKey($model->branch_id)->exists() || $courier->branches()->count() === 0, 422, 'El motorizado no está habilitado para la sucursal.');
        $model->update(['courier_id' => $courier->id, 'status' => 'assigned']);

        return response()->json(['codigo' => 200, 'mensaje' => 'Motorizado asignado.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier']))]);
    }

    #[OA\Post(path: '/api/v1/routes/{route}/orders', operationId: 'attachOrders', tags: ['Rutas'], summary: 'Agregar pedidos a una ruta', responses: [new OA\Response(response: 200, description: 'Pedidos agregados')])]
    public function attachOrders(AttachOrdersRequest $request, int $route): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->findOrFail($route);
        abort_if(in_array($model->status, ['dispatched', 'completed', 'cancelled'], true), 422, 'La ruta ya no admite pedidos.');
        $orderIds = $request->input('order_ids');

        DB::transaction(function () use ($model, $orderIds): void {
            $orders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();
            abort_if($orders->count() !== count($orderIds), 404, 'Uno de los pedidos no existe.');
            foreach ($orders as $order) {
                abort_unless($order->campaign_id === $model->campaign_id && $order->branch_id === $model->branch_id, 422, 'Todos los pedidos deben pertenecer a la campaña y sucursal de la ruta.');
                abort_if($order->status === 'cancelled' || ! $order->is_active, 422, 'No se puede agregar un pedido inactivo o cancelado.');
                abort_if($order->routes()->wherePivot('is_active', true)->exists(), 422, 'Un pedido ya pertenece a otra ruta activa.');
                $position = $model->orders()->whereKey($order->id)->value('route_order.position') ?? ($model->orders()->count() + 1);
                $model->orders()->syncWithoutDetaching([$order->id => ['position' => $position, 'sort_order' => $position, 'is_active' => true, 'assigned_at' => now(), 'removed_at' => null]]);
                $order->update(['status' => 'planned']);
            }
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Pedidos agregados a la ruta.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier', 'orders' => fn ($query) => $query->orderBy('route_order.sort_order')->orderBy('route_order.position')]))]);
    }

    #[OA\Patch(path: '/api/v1/routes/{route}/status', operationId: 'changeRouteStatus', tags: ['Rutas'], summary: 'Cambiar estado de ruta', responses: [new OA\Response(response: 200, description: 'Estado actualizado')])]
    public function changeStatus(ChangeRouteStatusRequest $request, int $route, RouteCancellationService $cancellationService): JsonResponse
    {
        /** @var DeliveryRoute $model */
        $model = $this->visibleQuery($request->user(), app(OperationalScopeService::class))->withCount(['orders as active_orders_count' => fn ($query) => $query->where('route_order.is_active', true)])->findOrFail($route);
        $next = $request->string('status')->toString();
        $allowed = ['draft' => ['planned', 'cancelled'], 'planned' => ['assigned', 'cancelled'], 'assigned' => ['dispatched', 'cancelled'], 'dispatched' => ['completed'], 'completed' => [], 'cancelled' => []];
        abort_unless(in_array($next, $allowed[$model->status] ?? [], true), 422, 'La transición de estado no está permitida.');
        if ($next === 'cancelled') {
            $cancelled = $cancellationService->cancel($model);

            return response()->json(['codigo' => 200, 'mensaje' => 'Ruta cancelada y pedidos liberados.', 'datos' => new DeliveryRouteResource($cancelled)]);
        }
        if ($next === 'dispatched') {
            abort_if(! $model->courier_id || $model->active_orders_count < 1, 422, 'La ruta necesita motorizado y al menos un pedido.');
        }
        $model->update(['status' => $next, 'dispatched_at' => $next === 'dispatched' ? now() : $model->dispatched_at]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Estado de ruta actualizado.', 'datos' => new DeliveryRouteResource($model->fresh(['campaign', 'branch', 'courier']))]);
    }

    private function validateOrdersForRoute($orders, array $stops, Campaign $campaign, Branch $branch, ?int $currentRouteId = null): void
    {
        abort_unless($orders->count() === count($stops), 422, 'Todos los pedidos seleccionados deben existir.');
        foreach ($stops as $stop) {
            $order = $orders->get($stop['order_id']);
            abort_unless($order && $order->campaign_id === $campaign->id && $order->branch_id === $branch->id, 422, 'Todos los pedidos deben pertenecer a la misma campaña y sucursal.');
            abort_if(! $order->is_active || in_array($order->status, ['cancelled', 'delivered'], true), 422, 'No se puede enrutar un pedido inactivo, cancelado o entregado.');
            abort_unless(in_array($order->status, ['pending', 'validated', 'planned'], true), 422, 'El estado del pedido no permite planificarlo.');
            abort_unless($order->latitude !== null && $order->longitude !== null, 422, 'Todos los pedidos deben tener coordenadas válidas.');
            $activeRoute = $order->routes()->wherePivot('is_active', true)->when($currentRouteId, fn ($query) => $query->where('delivery_routes.id', '!=', $currentRouteId))->exists();
            abort_if($activeRoute, 409, 'Uno de los pedidos ya pertenece a otra ruta activa.');
        }
    }

    private function syncRouteStops(DeliveryRoute $route, array $stops, $orders): void
    {
        foreach ($stops as $stop) {
            $route->orders()->syncWithoutDetaching([$stop['order_id'] => ['position' => $stop['sort_order'], 'sort_order' => $stop['sort_order'], 'is_active' => true, 'assigned_at' => now(), 'removed_at' => null]]);
            $orders->get($stop['order_id'])->update(['status' => 'planned']);
        }
    }

    private function nextRouteCode(): string
    {
        $next = ((int) DeliveryRoute::withTrashed()->max('id')) + 1;

        return 'RUTA-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function navigationUrl(Branch $branch, $orders, array $stops): string
    {
        $ordered = collect($stops)->sortBy('sort_order')->map(fn ($stop) => $orders->get($stop['order_id']))->values();
        $origin = $branch->latitude.','.$branch->longitude;
        $destinationOrder = $ordered->last();
        $destination = $destinationOrder->latitude.','.$destinationOrder->longitude;
        $waypoints = $ordered->slice(0, -1)->map(fn ($order) => $order->latitude.','.$order->longitude)->implode('|');
        $url = 'https://www.google.com/maps/dir/?'.http_build_query(array_filter(['api' => 1, 'origin' => $origin, 'destination' => $destination, 'waypoints' => $waypoints]));
        abort_unless(parse_url($url, PHP_URL_HOST) === 'www.google.com', 422, 'No se pudo generar un enlace de navegación válido.');

        return $url;
    }

    private function visibleQuery(User $actor, OperationalScopeService $scope): Builder
    {
        return DeliveryRoute::query()->whereIn('campaign_id', $scope->visibleCampaigns($actor)->select('campaigns.id'));
    }

    private function mapOrdersQuery(Campaign $campaign, MapOrdersRequest $request): Builder
    {
        return Order::query()
            ->with([
                'campaign',
                'branch',
                'product',
                'districtCatalog',
                'activeRoutes' => fn ($query) => $query
                    ->select(['delivery_routes.id', 'delivery_routes.code', 'delivery_routes.name', 'delivery_routes.status', 'delivery_routes.courier_id']),
            ])
            ->where('campaign_id', $campaign->id)
            ->where('branch_id', $campaign->branch_id)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->filled('search'), fn ($query) => $query->where(function ($sub) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $sub->where('order_number', 'like', $value)
                    ->orWhere('recipient_name', 'like', $value)
                    ->orWhere('recipient_phone', 'like', $value)
                    ->orWhere('address', 'like', $value);
            }))
            ->when($request->filled('delivery_date'), fn ($query) => $query->whereDate('delivery_date', $request->date('delivery_date')))
            ->when($request->filled('district_id'), fn ($query) => $query->where('district_id', $request->integer('district_id')))
            ->orderBy('delivery_date')
            ->orderBy('id');
    }

    private function ensureScope(User $actor, Campaign $campaign, Branch $branch, OperationalScopeService $scope): void
    {
        abort_unless($campaign->branch_id === $branch->id, 422, 'La sucursal no pertenece a la campaña.');
        abort_unless($scope->canAccessCampaign($actor, $campaign) && $scope->canAccessBranch($actor, $branch), 403, 'La ruta está fuera de tu ámbito.');
    }
}
