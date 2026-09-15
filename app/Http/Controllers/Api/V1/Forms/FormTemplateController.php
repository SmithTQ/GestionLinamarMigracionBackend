<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Forms\StoreFormFieldRequest;
use App\Http\Requests\Api\V1\Forms\StoreFormTemplateRequest;
use App\Http\Requests\Api\V1\Forms\UpdateFormFieldRequest;
use App\Http\Requests\Api\V1\Forms\UpdateFormTemplateRequest;
use App\Http\Resources\Api\V1\FormFieldResource;
use App\Http\Resources\Api\V1\FormTemplateResource;
use App\Models\CampaignForm;
use App\Models\FormField;
use App\Models\FormTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Formularios', description: 'Administración de plantillas y campos')]
class FormTemplateController extends Controller
{
    private const RESERVED_FIELD_KEYS = ['product', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone', 'district', 'location', 'address', 'delivery_date', 'delivery_time', 'dedication', 'photo', 'adicional'];

    #[OA\Get(path: '/api/v1/form-templates', operationId: 'listFormTemplates', tags: ['Formularios'], summary: 'Listar plantillas', responses: [new OA\Response(response: 200, description: 'Plantillas obtenidas')])]
    public function index(Request $request): JsonResponse
    {
        $items = FormTemplate::query()
            ->with('fields')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $value = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($sub) => $sub->where('code', 'like', $value)->orWhere('name', 'like', $value)->orWhere('description', 'like', $value));
            })
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->tap(fn ($query) => $this->applySorting($query, $request, ['id' => 'id', 'code' => 'code', 'name' => 'name', 'created_at' => 'created_at'], 'name'))
            ->paginate(min($request->integer('per_page', 15), 100));

        return $this->paginatedResponse('Plantillas obtenidas.', $items, FormTemplateResource::class);
    }

    #[OA\Post(path: '/api/v1/form-templates', operationId: 'createFormTemplate', tags: ['Formularios'], summary: 'Crear plantilla', responses: [new OA\Response(response: 201, description: 'Plantilla creada')])]
    public function store(StoreFormTemplateRequest $request): JsonResponse
    {
        $template = FormTemplate::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return response()->json(['codigo' => 201, 'mensaje' => 'Plantilla creada.', 'datos' => new FormTemplateResource($template->load('fields'))], 201);
    }

    #[OA\Get(path: '/api/v1/form-templates/{template}', operationId: 'showFormTemplate', tags: ['Formularios'], summary: 'Consultar plantilla', responses: [new OA\Response(response: 200, description: 'Plantilla obtenida')])]
    public function show(int $template): JsonResponse
    {
        $item = FormTemplate::with('fields')->findOrFail($template);

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla obtenida.', 'datos' => new FormTemplateResource($item)]);
    }

    #[OA\Patch(path: '/api/v1/form-templates/{template}', operationId: 'updateFormTemplate', tags: ['Formularios'], summary: 'Actualizar plantilla', responses: [new OA\Response(response: 200, description: 'Plantilla actualizada')])]
    public function update(UpdateFormTemplateRequest $request, int $template): JsonResponse
    {
        $item = FormTemplate::findOrFail($template);
        $item->update($request->validated());

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla actualizada.', 'datos' => new FormTemplateResource($item->fresh('fields'))]);
    }

    #[OA\Delete(path: '/api/v1/form-templates/{template}', operationId: 'deactivateFormTemplate', tags: ['Formularios'], summary: 'Desactivar plantilla', responses: [new OA\Response(response: 200, description: 'Plantilla desactivada')])]
    public function destroy(int $template): JsonResponse
    {
        $item = FormTemplate::findOrFail($template);
        $item->update(['is_active' => false]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Plantilla desactivada.', 'datos' => new FormTemplateResource($item->fresh('fields'))]);
    }

    #[OA\Get(path: '/api/v1/form-templates/{template}/fields', operationId: 'listFormTemplateFields', tags: ['Formularios'], summary: 'Listar campos de plantilla', responses: [new OA\Response(response: 200, description: 'Campos obtenidos')])]
    public function fields(int $template): JsonResponse
    {
        $item = FormTemplate::findOrFail($template);
        $fields = $item->fields()->get();

        return response()->json(['codigo' => 200, 'mensaje' => 'Campos obtenidos.', 'datos' => FormFieldResource::collection($fields)]);
    }

    #[OA\Post(path: '/api/v1/form-templates/{template}/fields', operationId: 'createFormTemplateField', tags: ['Formularios'], summary: 'Crear campo de plantilla', responses: [new OA\Response(response: 201, description: 'Campo creado')])]
    public function storeField(StoreFormFieldRequest $request, int $template): JsonResponse
    {
        $item = FormTemplate::where('is_active', true)->findOrFail($template);
        $data = $request->validated();
        abort_if(in_array($data['key'], self::RESERVED_FIELD_KEYS, true), 422, 'La clave pertenece a un campo base del sistema.');
        abort_if($item->fields()->where('key', $data['key'])->exists(), 422, 'La clave ya existe en la plantilla.');
        $data['is_system'] = false;
        $data['is_active'] = true;
        $data['template_id'] = $item->id;
        $field = FormField::create($data);

        return response()->json(['codigo' => 201, 'mensaje' => 'Campo creado.', 'datos' => new FormFieldResource($field)], 201);
    }

    #[OA\Patch(path: '/api/v1/form-templates/{template}/fields/{field}', operationId: 'updateFormTemplateField', tags: ['Formularios'], summary: 'Actualizar campo de plantilla', responses: [new OA\Response(response: 200, description: 'Campo actualizado')])]
    public function updateField(UpdateFormFieldRequest $request, int $template, int $field): JsonResponse
    {
        $item = FormTemplate::findOrFail($template);
        $model = $item->fields()->findOrFail($field);
        $data = $request->validated();
        $publishedUse = $this->fieldIsUsedByPublishedForm($item, $model);

        if ($model->is_system && (($data['key'] ?? $model->key) !== $model->key || ($data['type'] ?? $model->type) !== $model->type)) {
            abort(422, 'Los campos del sistema no pueden cambiar de clave ni de tipo.');
        }
        if ($publishedUse && (($data['key'] ?? $model->key) !== $model->key || ($data['type'] ?? $model->type) !== $model->type)) {
            abort(422, 'No se puede cambiar estructuralmente un campo usado por un formulario publicado.');
        }
        if (isset($data['key']) && $data['key'] !== $model->key && $item->fields()->where('key', $data['key'])->exists()) {
            abort(422, 'La clave ya existe en la plantilla.');
        }
        unset($data['is_system']);
        $model->update($data);

        return response()->json(['codigo' => 200, 'mensaje' => 'Campo actualizado.', 'datos' => new FormFieldResource($model->fresh())]);
    }

    #[OA\Delete(path: '/api/v1/form-templates/{template}/fields/{field}', operationId: 'deactivateFormTemplateField', tags: ['Formularios'], summary: 'Desactivar campo de plantilla', responses: [new OA\Response(response: 200, description: 'Campo desactivado')])]
    public function destroyField(int $template, int $field): JsonResponse
    {
        $item = FormTemplate::findOrFail($template);
        $model = $item->fields()->findOrFail($field);
        abort_if($model->is_system, 422, 'Los campos del sistema no pueden eliminarse.');
        $model->update(['is_active' => false]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Campo desactivado.', 'datos' => new FormFieldResource($model->fresh())]);
    }

    private function fieldIsUsedByPublishedForm(FormTemplate $template, FormField $field): bool
    {
        return CampaignForm::where('template_id', $template->id)
            ->where('status', 'published')
            ->whereHas('fields', fn ($query) => $query->whereKey($field->id))
            ->exists();
    }
}
