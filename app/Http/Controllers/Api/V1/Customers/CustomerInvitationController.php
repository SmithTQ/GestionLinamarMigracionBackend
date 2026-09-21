<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\CreateInvitationRequest;
use App\Http\Resources\Api\V1\CustomerInvitationResource;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Campaign;
use App\Models\CampaignForm;
use App\Models\Customer;
use App\Models\FormInvitation;
use App\Models\User;
use App\Services\Authorization\OperationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Clientes e invitaciones', description: 'Clientes e invitaciones gratuitas por WhatsApp Web')]
class CustomerInvitationController extends Controller
{
    #[OA\Get(path: '/api/v1/customers', operationId: 'listCustomers', tags: ['Clientes e invitaciones'], summary: 'Listar clientes', responses: [new OA\Response(response: 200, description: 'Clientes obtenidos')])]
    public function customers(Request $request): JsonResponse
    {
        $items = Customer::query()->where('is_active', true)->whereHas('orders', fn ($orders) => $orders->whereIn('campaign_id', app(OperationalScopeService::class)->visibleCampaigns($request->user())->select('campaigns.id')))->when($request->filled('search'), fn ($q) => $q->where(fn ($sub) => $sub->where('full_name', 'like', '%'.$request->string('search').'%')->orWhere('whatsapp_number', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%')))->tap(fn ($q) => $this->applySorting($q, $request, ['full_name' => 'full_name', 'whatsapp_number' => 'whatsapp_number', 'email' => 'email', 'created_at' => 'created_at'], 'full_name'))->paginate(min($request->integer('per_page', 20), 100));

        return $this->paginatedResponse('Clientes obtenidos.', $items, CustomerResource::class);
    }

    #[OA\Get(path: '/api/v1/customers/{customer}', operationId: 'showCustomer', tags: ['Clientes e invitaciones'], summary: 'Consultar cliente e historial', responses: [new OA\Response(response: 200, description: 'Cliente obtenido')])]
    public function showCustomer(Request $request, int $customer): JsonResponse
    {
        $item = Customer::whereHas('orders', fn ($orders) => $orders->whereIn('campaign_id', app(OperationalScopeService::class)->visibleCampaigns($request->user())->select('campaigns.id')))
            ->with(['orders' => fn ($q) => $q->whereIn('campaign_id', app(OperationalScopeService::class)->visibleCampaigns($request->user())->select('campaigns.id'))->latest()])
            ->findOrFail($customer);

        return response()->json(['codigo' => 200, 'mensaje' => 'Cliente obtenido.', 'datos' => new CustomerResource($item)]);
    }

    #[OA\Post(path: '/api/v1/customer-invitations', operationId: 'createCustomerInvitation', tags: ['Clientes e invitaciones'], summary: 'Generar invitación de WhatsApp', responses: [new OA\Response(response: 201, description: 'Invitación generada')])]
    public function store(CreateInvitationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $number = $this->normalize($data['whatsapp_number']);
        abort_if(strlen($number) < 8 || strlen($number) > 15, 422, 'El número de WhatsApp no es válido.');
        $form = CampaignForm::with('campaign')->findOrFail($data['form_id']);
        /** @var Campaign $campaign */
        $campaign = $form->campaign;
        $this->ensureCampaignAccess($request->user(), $campaign);
        abort_unless($form->status === 'published' && $form->campaign->status === 'open', 422, 'El formulario no está disponible.');
        $customer = Customer::firstOrNew(['whatsapp_number' => $number]);
        if (! $customer->exists) {
            $customer->full_name = '';
        }
        if (filled($data['full_name'] ?? null)) {
            $customer->full_name = trim($data['full_name']);
        }
        if (array_key_exists('email', $data)) {
            $customer->email = $data['email'];
        }
        $customer->is_active = true;
        $customer->save();
        $invitation = FormInvitation::where('campaign_form_id', $form->id)->where('customer_id', $customer->id)->first();
        abort_if($invitation?->status === 'used', 409, 'El cliente ya utilizó este formulario.');
        if (! $invitation) {
            $invitation = FormInvitation::create(['campaign_form_id' => $form->id, 'customer_id' => $customer->id, 'created_by_user_id' => $request->user()->id, 'token' => Str::random(80), 'status' => 'pending', 'expires_at' => $data['expires_at'] ?? now()->addDays(7)]);
        } else {
            $invitation->update(['status' => 'pending', 'token' => Str::random(80), 'expires_at' => $data['expires_at'] ?? now()->addDays(7), 'revoked_at' => null, 'revoked_by_user_id' => null]);
        }

        return response()->json(['codigo' => 201, 'mensaje' => 'Invitación generada. Abre el enlace para enviarla por WhatsApp Web.', 'datos' => $this->invitationPayload($invitation->fresh(), $customer)], 201);
    }

    #[OA\Get(path: '/api/v1/customer-invitations', operationId: 'listCustomerInvitations', tags: ['Clientes e invitaciones'], summary: 'Listar invitaciones', responses: [new OA\Response(response: 200, description: 'Invitaciones obtenidas')])]
    public function invitations(Request $request): JsonResponse
    {
        $items = FormInvitation::with(['customer', 'form'])->whereHas('form', fn ($form) => $form->whereIn('campaign_id', app(OperationalScopeService::class)->visibleCampaigns($request->user())->select('campaigns.id')))->when($request->filled('form_id'), fn ($q) => $q->where('campaign_form_id', $request->integer('form_id')))->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))->when($request->filled('search'), fn ($q) => $q->whereHas('customer', fn ($customer) => $customer->where('full_name', 'like', '%'.$request->string('search')->toString().'%')->orWhere('whatsapp_number', 'like', '%'.$request->string('search')->toString().'%')))->tap(fn ($q) => $this->applySorting($q, $request, ['id' => 'id', 'status' => 'status', 'expires_at' => 'expires_at', 'created_at' => 'created_at'], 'id', 'desc'))->paginate(min($request->integer('per_page', 20), 100));

        return $this->paginatedResponse('Invitaciones obtenidas.', $items, CustomerInvitationResource::class);
    }

    #[OA\Post(path: '/api/v1/customer-invitations/{invitation}/revoke', operationId: 'revokeCustomerInvitation', tags: ['Clientes e invitaciones'], summary: 'Revocar invitación', responses: [new OA\Response(response: 200, description: 'Invitación revocada')])]
    public function revoke(Request $request, int $invitation): JsonResponse
    {
        $item = FormInvitation::with('form.campaign')->findOrFail($invitation);
        $form = $item->form;
        /** @var Campaign $campaign */
        $campaign = $form->getRelation('campaign');
        $this->ensureCampaignAccess($request->user(), $campaign);
        abort_if($item->status === 'used', 409, 'Una invitación usada no puede revocarse.');
        $item->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_id' => $request->user()->id]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Invitación revocada.', 'datos' => null]);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function ensureCampaignAccess(?User $actor, Campaign $campaign): void
    {
        abort_unless($actor instanceof User && app(OperationalScopeService::class)->canAccessCampaign($actor, $campaign), 403, 'No tienes acceso a esta campaña.');
    }

    private function invitationPayload(FormInvitation $invitation, Customer $customer): array
    {
        $url = rtrim((string) config('forms.frontend_url'), '/').'/formulario/invitacion/'.$invitation->token;
        $greeting = $customer->full_name !== '' ? 'Hola '.$customer->full_name.'. ' : 'Hola. ';

        return ['customer' => new CustomerResource($customer), 'invitation_token' => $invitation->token, 'form_url' => $url, 'whatsapp_url' => 'https://wa.me/'.$customer->whatsapp_number.'?text='.rawurlencode($greeting.'Completa tu pedido aquí: '.$url), 'status' => $invitation->status, 'expires_at' => $invitation->expires_at?->format('d/m/Y H:i'), 'expires_at_iso' => $invitation->expires_at?->toISOString()];
    }
}
