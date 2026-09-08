<?php
namespace App\Http\Requests\Api\V1\Products;
use Illuminate\Foundation\Http\FormRequest;
class StoreProductRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['subcategory_id'=>['nullable','integer','exists:product_subcategories,id'],'sku'=>['required','string','max:60','alpha_dash','unique:products,sku'],'name'=>['required','string','max:180'],'slug'=>['required','string','max:200','alpha_dash','unique:products,slug'],'description'=>['nullable','string','max:5000'],'unit'=>['sometimes','string','max:40'],'base_price'=>['nullable','numeric','min:0','max:9999999999.99'],'image_url'=>['nullable','url','max:500'],'sort_order'=>['sometimes','integer','min:0','max:65535']]; } }
