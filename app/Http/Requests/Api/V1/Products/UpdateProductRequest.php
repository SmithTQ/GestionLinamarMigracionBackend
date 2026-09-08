<?php
namespace App\Http\Requests\Api\V1\Products;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateProductRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['subcategory_id'=>['sometimes','nullable','integer','exists:product_subcategories,id'],'sku'=>['sometimes','required','string','max:60','alpha_dash',Rule::unique('products','sku')->ignore($this->route('product'))],'name'=>['sometimes','required','string','max:180'],'slug'=>['sometimes','required','string','max:200','alpha_dash',Rule::unique('products','slug')->ignore($this->route('product'))],'description'=>['nullable','string','max:5000'],'unit'=>['sometimes','string','max:40'],'base_price'=>['nullable','numeric','min:0','max:9999999999.99'],'image_url'=>['nullable','url','max:500'],'sort_order'=>['sometimes','integer','min:0','max:65535'],'is_active'=>['sometimes','boolean']]; } }
