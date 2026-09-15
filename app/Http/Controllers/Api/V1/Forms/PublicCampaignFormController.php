<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Forms\PublicFormSubmissionRequest;
use App\Models\CampaignForm;
use App\Models\FormInvitation;
use App\Models\FormSubmission;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Formularios públicos', description: 'Consulta y envío público de pedidos')]
class PublicCampaignFormController extends Controller
{
    #[OA\Get(path: '/api/v1/public/invitations/{token}', operationId: 'showPublicInvitation', tags: ['Formularios públicos'], summary: 'Consultar invitación', responses: [new OA\Response(response: 200, description: 'Invitación obtenida')])]
    public function showInvitation(string $token): JsonResponse
    {
        $invitation = $this->invitation($token);
        $response = $this->show($invitation->form->public_key);
        $payload = $response->getData(true);
        $payload['datos']['prefill'] = [
            'sender_name' => $invitation->customer->full_name,
            'sender_phone' => $invitation->customer->whatsapp_number,
        ];

        return response()->json($payload, $response->getStatusCode());
    }

    #[OA\Post(path: '/api/v1/public/invitations/{token}/submissions', operationId: 'submitPublicInvitation', tags: ['Formularios públicos'], summary: 'Enviar invitación', responses: [new OA\Response(response: 201, description: 'Pedido creado')])]
    public function submitInvitation(PublicFormSubmissionRequest $request, string $token): JsonResponse
    {
        $invitation = $this->invitation($token);
        abort_if($invitation->status === 'used', 409, 'Esta invitación ya fue utilizada.');
        $response = $this->submit($request, $invitation->form->public_key);
        if ($response->getStatusCode() === 201) {
            $orderId = $response->getData(true)['datos']['order_id'] ?? null;
            if ($orderId) {
                Order::whereKey($orderId)->update(['customer_id' => $invitation->customer_id]);
            } $invitation->update(['status' => 'used', 'used_at' => now()]);
        }

        return $response;
    }

    #[OA\Get(path: '/api/v1/public/forms/{publicKey}', operationId: 'showPublicCampaignForm', tags: ['Formularios públicos'], summary: 'Consultar formulario público', responses: [new OA\Response(response: 200, description: 'Formulario público obtenido')])]
    public function show(string $publicKey): JsonResponse
    {
        $form = $this->publishedForm($publicKey);
        $fields = $form->fields->filter(fn ($field) => $field->is_active && (bool) $field->pivot->is_enabled)->sortBy('pivot.sort_order')->values()->map(fn ($field) => ['key' => $field->key, 'label' => $field->pivot->label ?: $field->label, 'description' => $field->description, 'type' => $field->type, 'field_group' => $field->field_group, 'required' => (bool) $field->pivot->is_required, 'config' => $field->pivot->config ? json_decode($field->pivot->config, true) : null]);
        $products = $form->campaign->products()->where('products.is_active', true)->wherePivot('is_available', true)->orderBy('campaign_product.sort_order')->orderBy('products.id')->get()->map(fn ($product) => ['sku' => $product->sku, 'name' => $product->name, 'unit' => $product->unit, 'price' => $product->pivot->price ?? $product->base_price, 'max_quantity' => $product->pivot->max_quantity, 'image_url' => $product->image_url, 'image_thumbnail_url' => $product->image_thumbnail_url]);
        $districts = $form->campaign->districtLists
            ->flatMap(fn ($list) => $list->districts)
            ->where('is_active', true)
            ->unique('id')
            ->sortBy('name')
            ->values();
        $districts = $districts->map(fn ($district) => ['code' => $district->code, 'name' => $district->name, 'province' => $district->province, 'department' => $district->department]);

        return response()->json(['codigo' => 200, 'mensaje' => 'Formulario público obtenido.', 'datos' => ['title' => $form->title, 'description' => $form->description, 'fields' => $fields, 'products' => $products, 'districts' => $districts]]);
    }

    #[OA\Post(path: '/api/v1/public/forms/{publicKey}/submissions', operationId: 'submitPublicCampaignForm', tags: ['Formularios públicos'], summary: 'Enviar formulario público', responses: [new OA\Response(response: 201, description: 'Pedido creado'), new OA\Response(response: 200, description: 'Envío duplicado')])]
    public function submit(PublicFormSubmissionRequest $request, string $publicKey): JsonResponse
    {
        $form = $this->publishedForm($publicKey);
        $data = $request->validated();
        $existing = FormSubmission::where('campaign_form_id', $form->id)->where('submission_key', $data['submission_key'])->with('order')->first();
        if ($existing) {
            return response()->json(['codigo' => 200, 'mensaje' => 'El envío ya fue procesado.', 'datos' => ['submission_key' => $existing->submission_key, 'order_id' => $existing->order_id]]);
        }
        $product = $form->campaign->products()->where('products.sku', $data['product_sku'])->where('products.is_active', true)->wherePivot('is_available', true)->first();
        abort_unless($product, 422, 'El producto no está disponible en este formulario.');
        $district = $form->campaign->districtLists
            ->flatMap(fn ($list) => $list->districts)
            ->where('is_active', true)
            ->unique('id')
            ->firstWhere('code', $data['district_code']);
        abort_unless($district, 422, 'El distrito no está disponible en este formulario.');
        $fileFields = $form->fields->filter(fn ($field) => $field->is_active && (bool) $field->pivot->is_enabled && $field->type === 'file');
        $payload = collect($data)->reject(fn ($value) => $value instanceof UploadedFile)->all();
        $storedPaths = [];
        try {
            [$order,$submission] = DB::transaction(function () use ($form, $data, $payload, $product, $district, $request, $fileFields, &$storedPaths): array {
                $order = Order::create(['campaign_id' => $form->campaign_id, 'branch_id' => $form->branch_id, 'product_id' => $product->id, 'product_name' => $product->name, 'product_price' => $product->pivot->price ?? $product->base_price, 'external_source' => 'public_form', 'external_key' => $data['submission_key'], 'sender_name' => $data['sender_name'], 'sender_phone' => $data['sender_phone'], 'recipient_name' => $data['recipient_name'], 'recipient_phone' => $data['recipient_phone'], 'district_id' => $district->id, 'district' => $district->name, 'address' => $data['address'] ?? '', 'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'location_accuracy' => $data['location_accuracy'] ?? null, 'dedication' => $data['dedication'] ?? null, 'delivery_date' => $data['delivery_date'] ?? null, 'delivery_time' => $data['delivery_time'] ?? null]);
                $submission = FormSubmission::create(['campaign_form_id' => $form->id, 'order_id' => $order->id, 'submission_key' => $data['submission_key'], 'request_hash' => hash('sha256', json_encode($payload)), 'payload' => $payload, 'status' => 'accepted', 'ip_hash' => hash('sha256', (string) $request->ip())]);

                foreach ($fileFields as $field) {
                    $file = $request->file($field->key);
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }
                    $disk = config('forms.file_disk', 'local');
                    $path = $file->store('form-submissions/'.$submission->id, $disk);
                    $storedPaths[] = [$disk, $path];
                    $submission->files()->create(['form_field_id' => $field->id, 'disk' => $disk, 'path' => $path, 'original_name' => Str::of(basename($file->getClientOriginalName()))->replaceMatches('/[^A-Za-z0-9._-]/', '_')->limit(255, '')->toString(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(), 'hash' => hash_file('sha256', $file->getRealPath())]);
                }

                return [$order, $submission];
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }

        return response()->json(['codigo' => 201, 'mensaje' => 'Pedido registrado correctamente.', 'datos' => ['submission_key' => $submission->submission_key, 'order_id' => $order->id]], 201);
    }

    private function publishedForm(string $publicKey): CampaignForm
    {
        $form = CampaignForm::with(['campaign.districtLists.districts', 'branch', 'fields'])->where('public_key', $publicKey)->where('status', 'published')->first();
        abort_if(! $form, 404, 'Formulario no disponible.');
        abort_if($form->campaign->status !== 'open', 404, 'Formulario no disponible.');

        return $form;
    }

    private function invitation(string $token): FormInvitation
    {
        $invitation = FormInvitation::with(['form.campaign.districtLists.districts', 'form.fields', 'customer'])->where('token', $token)->where('status', 'pending')->first();
        abort_if(! $invitation || ($invitation->expires_at && $invitation->expires_at->isPast()), 404, 'La invitación no está disponible.');

        return $invitation;
    }
}
