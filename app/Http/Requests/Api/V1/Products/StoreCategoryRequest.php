<?php
namespace App\Http\Requests\Api\V1\Products;
use Illuminate\Foundation\Http\FormRequest;
class StoreCategoryRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['name'=>['required','string','max:120'],'slug'=>['required','string','max:140','alpha_dash','unique:product_categories,slug'],'description'=>['nullable','string','max:5000'],'sort_order'=>['sometimes','integer','min:0','max:65535']]; } }
