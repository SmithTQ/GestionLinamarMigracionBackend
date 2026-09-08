<?php
namespace App\Http\Controllers\Api\V1\Forms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Forms\PublicFormSubmissionRequest;
use App\Models\CampaignForm;
use App\Models\FormSubmission;
use App\Models\Order;
use App\Models\Product;
use App\Models\District;
use App\Models\FormInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Formularios públicos', description: 'Consulta y envío público de pedidos')]
class PublicCampaignFormController extends Controller
{
    #[OA\Get(path: '/api/v1/public/invitations/{token}', operationId: 'showPublicInvitation', tags: ['Formularios públicos'], summary: 'Consultar invitación', responses: [new OA\Response(response: 200, description: 'Invitación obtenida')])]
    public function showInvitation(string $token): JsonResponse { $invitation=$this->invitation($token); return $this->show($invitation->form->public_key); }
    #[OA\Post(path: '/api/v1/public/invitations/{token}/submissions', operationId: 'submitPublicInvitation', tags: ['Formularios públicos'], summary: 'Enviar invitación', responses: [new OA\Response(response: 201, description: 'Pedido creado')])]
    public function submitInvitation(PublicFormSubmissionRequest $request, string $token): JsonResponse { $invitation=$this->invitation($token); abort_if($invitation->status==='used',409,'Esta invitación ya fue utilizada.'); $response=$this->submit($request,$invitation->form->public_key); if($response->getStatusCode()===201){$orderId=$response->getData(true)['datos']['order_id']??null; if($orderId){Order::whereKey($orderId)->update(['customer_id'=>$invitation->customer_id]);} $invitation->update(['status'=>'used','used_at'=>now()]);} return $response; }
    #[OA\Get(path: '/api/v1/public/forms/{publicKey}', operationId: 'showPublicCampaignForm', tags: ['Formularios públicos'], summary: 'Consultar formulario público', responses: [new OA\Response(response: 200, description: 'Formulario público obtenido')])]
    public function show(string $publicKey): JsonResponse
    {
        $form=$this->publishedForm($publicKey);
        $fields=$form->fields->where('pivot.is_enabled',true)->sortBy('pivot.sort_order')->values()->map(fn($field)=>['key'=>$field->key,'label'=>$field->pivot->label?:$field->label,'type'=>$field->type,'required'=>(bool)$field->pivot->is_required,'config'=>$field->pivot->config?json_decode($field->pivot->config,true):null]);
        $products=$form->campaign->products()->where('products.is_active',true)->wherePivot('is_available',true)->orderByPivot('sort_order')->get()->map(fn($product)=>['sku'=>$product->sku,'name'=>$product->name,'unit'=>$product->unit,'price'=>$product->pivot->price??$product->base_price,'max_quantity'=>$product->pivot->max_quantity]);
        $districts=$form->campaign->districts()->where('districts.is_active',true)->orderBy('name')->get()->map(fn($district)=>['code'=>$district->code,'name'=>$district->name,'province'=>$district->province,'department'=>$district->department]);
        return response()->json(['codigo'=>200,'mensaje'=>'Formulario público obtenido.','datos'=>['title'=>$form->title,'description'=>$form->description,'fields'=>$fields,'products'=>$products,'districts'=>$districts]]);
    }

    #[OA\Post(path: '/api/v1/public/forms/{publicKey}/submissions', operationId: 'submitPublicCampaignForm', tags: ['Formularios públicos'], summary: 'Enviar formulario público', responses: [new OA\Response(response: 201, description: 'Pedido creado'), new OA\Response(response: 200, description: 'Envío duplicado')])]
    public function submit(PublicFormSubmissionRequest $request, string $publicKey): JsonResponse
    {
        $form=$this->publishedForm($publicKey); $data=$request->validated();
        $existing=FormSubmission::where('campaign_form_id',$form->id)->where('submission_key',$data['submission_key'])->with('order')->first();
        if($existing){ return response()->json(['codigo'=>200,'mensaje'=>'El envío ya fue procesado.','datos'=>['submission_key'=>$existing->submission_key,'order_id'=>$existing->order_id]]); }
        $product=$form->campaign->products()->where('products.sku',$data['product_sku'])->where('products.is_active',true)->wherePivot('is_available',true)->first(); abort_unless($product,422,'El producto no está disponible en este formulario.');
        $district=$form->campaign->districts()->where('districts.code',$data['district_code'])->where('districts.is_active',true)->first(); abort_unless($district,422,'El distrito no está disponible en este formulario.');
        $payload=$data; unset($payload['submission_key']);
        [$order,$submission]=DB::transaction(function() use($form,$data,$payload,$product,$district,$request): array { $order=Order::create(['campaign_id'=>$form->campaign_id,'branch_id'=>$form->branch_id,'product_id'=>$product->id,'product_name'=>$product->name,'product_price'=>$product->pivot->price??$product->base_price,'external_source'=>'public_form','external_key'=>$data['submission_key'],'sender_name'=>$data['sender_name'],'sender_phone'=>$data['sender_phone'],'recipient_name'=>$data['recipient_name'],'recipient_phone'=>$data['recipient_phone'],'district_id'=>$district->id,'district'=>$district->name,'address'=>$data['address'],'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],'location_accuracy'=>$data['location_accuracy']??null,'dedication'=>$data['dedication']??null,'delivery_date'=>$data['delivery_date']??null,'delivery_time'=>$data['delivery_time']??null]); $submission=FormSubmission::create(['campaign_form_id'=>$form->id,'order_id'=>$order->id,'submission_key'=>$data['submission_key'],'request_hash'=>hash('sha256',json_encode($payload)),'payload'=>$payload,'status'=>'accepted','ip_hash'=>hash('sha256',(string)$request->ip())]); return [$order,$submission]; });
        return response()->json(['codigo'=>201,'mensaje'=>'Pedido registrado correctamente.','datos'=>['submission_key'=>$submission->submission_key,'order_id'=>$order->id]],201);
    }

    private function publishedForm(string $publicKey): CampaignForm { $form=CampaignForm::with(['campaign','branch','fields'])->where('public_key',$publicKey)->where('status','published')->first(); abort_if(!$form,404,'Formulario no disponible.'); abort_if($form->campaign->status!=='open',404,'Formulario no disponible.'); return $form; }
    private function invitation(string $token): FormInvitation { $invitation=FormInvitation::with(['form','form.campaign'])->where('token',$token)->where('status','pending')->first(); abort_if(!$invitation||($invitation->expires_at&&$invitation->expires_at->isPast()),404,'La invitación no está disponible.'); return $invitation; }
}
