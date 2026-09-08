<?php
namespace App\Http\Requests\Api\V1\Products;
use Illuminate\Foundation\Http\FormRequest;
class AssignCampaignProductsRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['products'=>['required','array'],'products.*.product_id'=>['required','integer','distinct','exists:products,id'],'products.*.price'=>['nullable','numeric','min:0','max:9999999999.99'],'products.*.is_available'=>['sometimes','boolean'],'products.*.sort_order'=>['sometimes','integer','min:0','max:65535'],'products.*.max_quantity'=>['nullable','integer','min:1']]; } }
