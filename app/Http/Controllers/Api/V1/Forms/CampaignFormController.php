<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Forms\StoreCampaignConfigurationRequest;
use App\Http\Requests\Api\V1\Forms\StoreCampaignFormRequest;
use App\Http\Requests\Api\V1\Forms\UpdateCampaignFormRequest;
use App\Http\Resources\Api\V1\CampaignFormResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\FormTemplate;
use App\Models\User;
use App\Services\Authorization\OperationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Formularios', description: 'Formularios configurables por campaña')]
class CampaignFormController extends Controller
{
    private const SYSTEM_REQUIRED_KEYS = [
        'product', 'sender_name', 'sender_phone', 'recipient_name',
        'recipient_phone', 'district', 'location', 'delivery_reference',
    ];

    #[OA\Get(path: '/api/v1/campaign-forms', operationId: 'listCampaignForms', tags: ['Formularios'], summary: 'Listar formularios', responses: [new OA\Response(response: 200, description: 'Formularios obtenidos')])]
    public function index(Request $request): JsonResponse
    {
        $items = CampaignForm::with(['campaign.districtLists.districts', 'branch', 'template', 'fields'])
            ->whereIn('campaign_id', app(OperationalScopeService::class)->visibleCampaigns($request->user())->select('campaigns.id'))
            ->when($request->filled('campaign_id'), fn ($q) => $q->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('template_id'), fn ($q) => $q->where('template_id', $request->integer('template_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $value = '%'.$request->string('search')->toString().'%';
                $sub->where('title', 'like', $value)->orWhere('description', 'like', $value);
            }))
            ->tap(fn ($q) => $this->applySorting($q, $request, ['id' => 'id', 'title' => 'title', 'status' => 'status', 'published_at' => 'published_at', 'created_at' => 'created_at'], 'id', 'desc'))
            ->paginate(min($request->integer('per_page', 15), 100));

        return $this->paginatedResponse('Formularios obtenidos.', $items, CampaignFormResource::class);
    }

    #[OA\Post(path: '/api/v1/campaign-forms', operationId: 'createCampaignForm', tags: ['Formularios'], summary: 'Crear formulario', responses: [new OA\Response(response: 201, description: 'Formulario creado')])]
    public function store(StoreCampaignFormRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fields = $data['fields'] ?? [];
        unset($data['fields']);
        $campaign = Campaign::findOrFail($data['campaign_id']);
        $this->ensureCampaignAccess($request->user(), $campaign);
        $requestedBranchId = $data['branch_id'] ?? null;
        unset($data['branch_id']);
        /** @var Branch|null $branch */
        $branch = $campaign->branch;
        abort_unless($branch !== null, 422, 'No existe una sucursal interna predeterminada activa.');
        if ($requestedBranchId) {
            abort_unless((int) $requestedBranchId === (int) $branch->id, 422, 'La sucursal no pertenece a la campaña.');
        }
        /** @var FormTemplate $template */
        $template = FormTemplate::findOrFail($data['template_id']);
        $form = DB::transaction(function () use ($branch, $data, $fields, $template): CampaignForm {
            $data['branch_id'] = $branch->id;
            $form = CampaignForm::create($data + ['public_key' => Str::random(64), 'status' => 'draft']);
            $this->syncFields($form, $template, $fields);

            return $form;
        });

        return response()->json(['codigo' => 201, 'mensaje' => 'Formulario creado.', 'datos' => new CampaignFormResource($form->load(['campaign.districtLists.districts', 'branch', 'template', 'fields']))], 201);
    }

    #[OA\Put(path: '/api/v1/campaigns/{campaign}/configuration', operationId: 'configureCampaignFormAndProducts', tags: ['Formularios'], summary: 'Guardar formulario y productos de campaña de forma atómica', responses: [new OA\Response(response: 200, description: 'Configuración guardada')])]
    public function configure(StoreCampaignConfigurationRequest $request, int $campaign): JsonResponse
    {
        $campaignModel = Campaign::findOrFail($campaign);
        $this->ensureCampaignAccess($request->user(), $campaignModel);
        $data = $request->validated();
        $formData = $data['form'];
        $fields = $formData['fields'] ?? [];
        $requestedBranchId = $formData['branch_id'] ?? null;
        unset($formData['fields'], $formData['branch_id']);
        /** @var Branch|null $branch */
        $branch = $campaignModel->branch;
        abort_unless($branch !== null, 422, 'No existe una sucursal interna predeterminada activa.');
        if ($requestedBranchId) {
            abort_unless((int) $requestedBranchId === (int) $branch->id, 422, 'La sucursal no pertenece a la campaÃ±a.');
        }
        $template = FormTemplate::findOrFail($formData['template_id']);
        $productSync = [];
        foreach ($data['products'] as $product) {
            $productSync[$product['product_id']] = ['price' => $product['price'] ?? null, 'is_available' => $product['is_available'] ?? true, 'sort_order' => $product['sort_order'] ?? 0, 'max_quantity' => $product['max_quantity'] ?? null];
        }

        $result = DB::transaction(function () use ($campaignModel, $branch, $formData, $fields, $template, $productSync): array {
            $form = CampaignForm::where('campaign_id', $campaignModel->id)->where('branch_id', $branch->id)->first();
            abort_if($form?->status === 'published', 422, 'Un formulario publicado debe cerrarse antes de editarse.');
            if ($form) {
                $form->update($formData);
            } else {
                $form = CampaignForm::create($formData + ['campaign_id' => $campaignModel->id, 'branch_id' => $branch->id, 'public_key' => Str::random(64), 'status' => 'draft']);
            }
            $this->syncFields($form, $template, $fields);
            $campaignModel->products()->sync($productSync);

            return [$form->fresh(['campaign.districtLists.districts', 'branch', 'template', 'fields']), $campaignModel->products()->with('subcategory.category')->orderBy('campaign_product.sort_order')->orderBy('products.id')->get()];
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Formulario y productos configurados correctamente.', 'datos' => ['form' => new CampaignFormResource($result[0]), 'products' => ProductResource::collection($result[1])]]);
    }

    #[OA\Get(path: '/api/v1/campaign-forms/{form}', operationId: 'showCampaignForm', tags: ['Formularios'], summary: 'Consultar formulario', responses: [new OA\Response(response: 200, description: 'Formulario obtenido')])]
    public function show(Request $request, int $form): JsonResponse
    {
        $item = CampaignForm::with(['campaign.districtLists.districts', 'branch', 'template', 'fields'])->findOrFail($form);
        /** @var Campaign $campaign */
        $campaign = $item->getRelation('campaign');
        $this->ensureCampaignAccess($request->user(), $campaign);

        return response()->json(['codigo' => 200, 'mensaje' => 'Formulario obtenido.', 'datos' => new CampaignFormResource($item)]);
    }

    #[OA\Patch(path: '/api/v1/campaign-forms/{form}', operationId: 'updateCampaignForm', tags: ['Formularios'], summary: 'Actualizar formulario', responses: [new OA\Response(response: 200, description: 'Formulario actualizado')])]
    public function update(UpdateCampaignFormRequest $request, int $form): JsonResponse
    {
        $item = CampaignForm::findOrFail($form);
        /** @var Campaign $campaign */
        $campaign = $item->campaign;
        $this->ensureCampaignAccess($request->user(), $campaign);
        abort_if($item->status === 'published', 422, 'Un formulario publicado debe cerrarse antes de editarse.');
        $data = $request->validated();
        $fields = $data['fields'] ?? null;
        unset($data['fields']);
        DB::transaction(function () use ($item, $data, $fields): void {
            $item->update($data);
            if ($fields !== null) {
                $this->syncFields($item, $item->template, $fields);
            }
        });

        return response()->json(['codigo' => 200, 'mensaje' => 'Formulario actualizado.', 'datos' => new CampaignFormResource($item->fresh(['campaign.districtLists.districts', 'branch', 'template', 'fields']))]);
    }

    #[OA\Post(path: '/api/v1/campaign-forms/{form}/publish', operationId: 'publishCampaignForm', tags: ['Formularios'], summary: 'Publicar formulario', responses: [new OA\Response(response: 200, description: 'Formulario publicado')])]
    public function publish(Request $request, int $form): JsonResponse
    {
        $item = CampaignForm::with('campaign')->findOrFail($form);
        /** @var Campaign $campaign */
        $campaign = $item->campaign;
        $this->ensureCampaignAccess($request->user(), $campaign);
        abort_unless($item->campaign->status === 'open', 422, 'La campaña debe estar abierta para publicar el formulario.');
        $item->update(['status' => 'published', 'published_at' => now(), 'closed_at' => null]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Formulario publicado.', 'datos' => new CampaignFormResource($item->fresh(['campaign.districtLists.districts', 'branch', 'template', 'fields']))]);
    }

    #[OA\Post(path: '/api/v1/campaign-forms/{form}/close', operationId: 'closeCampaignForm', tags: ['Formularios'], summary: 'Cerrar formulario', responses: [new OA\Response(response: 200, description: 'Formulario cerrado')])]
    public function close(Request $request, int $form): JsonResponse
    {
        $item = CampaignForm::with('campaign')->findOrFail($form);
        /** @var Campaign $campaign */
        $campaign = $item->campaign;
        $this->ensureCampaignAccess($request->user(), $campaign);
        $item->update(['status' => 'closed', 'closed_at' => now()]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Formulario cerrado.', 'datos' => null]);
    }

    private function ensureCampaignAccess(?User $actor, Campaign $campaign): void
    {
        abort_unless($actor instanceof User && app(OperationalScopeService::class)->canAccessCampaign($actor, $campaign), 403, 'No tienes acceso a esta campaña.');
    }

    private function syncFields(CampaignForm $form, FormTemplate $template, array $fields): void
    {
        $availableFields = $template->fields()->where('is_active', true)->get();
        if ($fields === []) {
            $fields = $availableFields->map(fn ($field) => [
                'field_id' => $field->id,
                'is_enabled' => true,
                'is_required' => in_array($field->key, self::SYSTEM_REQUIRED_KEYS, true),
                'sort_order' => $field->sort_order,
            ])->all();
        }
        $allowed = $availableFields->pluck('id')->all();
        $sync = [];
        foreach ($fields as $field) {
            abort_unless(in_array($field['field_id'], $allowed, true), 422, 'El campo no pertenece a la plantilla.');
            $definition = $availableFields->firstWhere('id', $field['field_id']);
            if ($definition->field_group === 'required_base' || in_array($definition->key, self::SYSTEM_REQUIRED_KEYS, true)) {
                abort_unless(($field['is_enabled'] ?? true) && ($field['is_required'] ?? true), 422, 'Los campos obligatorios del sistema deben estar habilitados y ser requeridos.');
            }
            $sync[$field['field_id']] = ['is_enabled' => $field['is_enabled'] ?? true, 'is_required' => $field['is_required'] ?? false, 'label' => $field['label'] ?? null, 'config' => isset($field['config']) ? json_encode($field['config']) : null, 'sort_order' => $field['sort_order'] ?? 0];
        }
        foreach ($availableFields->where('field_group', 'required_base') as $required) {
            abort_unless(array_key_exists($required->id, $sync), 422, 'Falta un campo obligatorio del sistema.');
        }
        $form->fields()->sync($sync);
    }
}
