<?php
namespace App\Http\Controllers\Api\V1\Forms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Forms\StoreCampaignFormRequest;
use App\Http\Requests\Api\V1\Forms\UpdateCampaignFormRequest;
use App\Http\Resources\Api\V1\CampaignFormResource;
use App\Http\Resources\Api\V1\FormTemplateResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\FormTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Formularios', description: 'Formularios configurables por campaña')]
class CampaignFormController extends Controller
{
    #[OA\Get(path: '/api/v1/form-templates', operationId: 'listFormTemplates', tags: ['Formularios'], summary: 'Listar plantillas', responses: [new OA\Response(response: 200, description: 'Plantillas obtenidas')])]
    public function templates(Request $request): JsonResponse { $items=FormTemplate::where('is_active',true)->when($request->filled('search'),fn($q)=>$q->where(fn($sub)=>$sub->where('code','like','%'.$request->string('search')->toString().'%')->orWhere('name','like','%'.$request->string('search')->toString().'%')->orWhere('description','like','%'.$request->string('search')->toString().'%')))->with('fields')->tap(fn($q)=>$this->applySorting($q,$request,['name'=>'name','code'=>'code','created_at'=>'created_at'],'name'))->get(); return response()->json(['codigo'=>200,'mensaje'=>'Plantillas obtenidas.','datos'=>FormTemplateResource::collection($items)]); }
    #[OA\Get(path: '/api/v1/campaign-forms', operationId: 'listCampaignForms', tags: ['Formularios'], summary: 'Listar formularios', responses: [new OA\Response(response: 200, description: 'Formularios obtenidos')])]
    public function index(Request $request): JsonResponse { $items=CampaignForm::with(['campaign','branch','template','fields'])->when($request->filled('campaign_id'),fn($q)=>$q->where('campaign_id',$request->integer('campaign_id')))->when($request->filled('branch_id'),fn($q)=>$q->where('branch_id',$request->integer('branch_id')))->when($request->filled('template_id'),fn($q)=>$q->where('template_id',$request->integer('template_id')))->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')->toString()))->when($request->filled('search'),fn($q)=>$q->where(function($sub)use($request){$value='%'.$request->string('search')->toString().'%';$sub->where('title','like',$value)->orWhere('description','like',$value);}))->tap(fn($q)=>$this->applySorting($q,$request,['id'=>'id','title'=>'title','status'=>'status','published_at'=>'published_at','created_at'=>'created_at'],'id','desc'))->paginate(min($request->integer('per_page',15),100)); return $this->paginatedResponse('Formularios obtenidos.', $items, CampaignFormResource::class); }
    #[OA\Post(path: '/api/v1/campaign-forms', operationId: 'createCampaignForm', tags: ['Formularios'], summary: 'Crear formulario', responses: [new OA\Response(response: 201, description: 'Formulario creado')])]
    public function store(StoreCampaignFormRequest $request): JsonResponse { $data=$request->validated(); $fields=$data['fields']??[]; unset($data['fields']); $campaign=Campaign::findOrFail($data['campaign_id']); $branch=Branch::findOrFail($data['branch_id']); abort_unless($campaign->branches()->whereKey($branch->id)->exists(),422,'La sucursal no pertenece a la campaña.'); $template=FormTemplate::findOrFail($data['template_id']); $form=CampaignForm::create($data+['public_key'=>Str::random(64),'status'=>'draft']); $this->syncFields($form,$template,$fields); return response()->json(['codigo'=>201,'mensaje'=>'Formulario creado.','datos'=>new CampaignFormResource($form->load(['campaign','branch','template','fields']))],201); }
    #[OA\Get(path: '/api/v1/campaign-forms/{form}', operationId: 'showCampaignForm', tags: ['Formularios'], summary: 'Consultar formulario', responses: [new OA\Response(response: 200, description: 'Formulario obtenido')])]
    public function show(int $form): JsonResponse { return response()->json(['codigo'=>200,'mensaje'=>'Formulario obtenido.','datos'=>new CampaignFormResource(CampaignForm::with(['campaign','branch','template','fields'])->findOrFail($form))]); }
    #[OA\Patch(path: '/api/v1/campaign-forms/{form}', operationId: 'updateCampaignForm', tags: ['Formularios'], summary: 'Actualizar formulario', responses: [new OA\Response(response: 200, description: 'Formulario actualizado')])]
    public function update(UpdateCampaignFormRequest $request, int $form): JsonResponse { $item=CampaignForm::findOrFail($form); abort_if($item->status==='published',422,'Un formulario publicado debe cerrarse antes de editarse.'); $data=$request->validated(); $fields=$data['fields']??null; unset($data['fields']); $item->update($data); if($fields!==null)$this->syncFields($item,$item->template,$fields); return response()->json(['codigo'=>200,'mensaje'=>'Formulario actualizado.','datos'=>new CampaignFormResource($item->fresh(['campaign','branch','template','fields']))]); }
    #[OA\Post(path: '/api/v1/campaign-forms/{form}/publish', operationId: 'publishCampaignForm', tags: ['Formularios'], summary: 'Publicar formulario', responses: [new OA\Response(response: 200, description: 'Formulario publicado')])]
    public function publish(int $form): JsonResponse { $item=CampaignForm::with('campaign')->findOrFail($form); abort_unless($item->campaign->status==='open',422,'La campaña debe estar abierta para publicar el formulario.'); $item->update(['status'=>'published','published_at'=>now(),'closed_at'=>null]); return response()->json(['codigo'=>200,'mensaje'=>'Formulario publicado.','datos'=>new CampaignFormResource($item->fresh(['campaign','branch','template','fields']))]); }
    #[OA\Post(path: '/api/v1/campaign-forms/{form}/close', operationId: 'closeCampaignForm', tags: ['Formularios'], summary: 'Cerrar formulario', responses: [new OA\Response(response: 200, description: 'Formulario cerrado')])]
    public function close(int $form): JsonResponse { $item=CampaignForm::findOrFail($form); $item->update(['status'=>'closed','closed_at'=>now()]); return response()->json(['codigo'=>200,'mensaje'=>'Formulario cerrado.','datos'=>null]); }
    private function syncFields(CampaignForm $form, FormTemplate $template, array $fields): void { $allowed=$template->fields()->pluck('id')->all(); $sync=[]; foreach($fields as $field){ abort_unless(in_array($field['field_id'],$allowed,true),422,'El campo no pertenece a la plantilla.'); $sync[$field['field_id']]=['is_enabled'=>$field['is_enabled']??true,'is_required'=>$field['is_required']??false,'label'=>$field['label']??null,'config'=>isset($field['config'])?json_encode($field['config']):null,'sort_order'=>$field['sort_order']??0]; } $form->fields()->sync($sync); }
}
