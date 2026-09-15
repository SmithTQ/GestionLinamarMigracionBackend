<?php

namespace App\Http\Requests\Api\V1\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['subcategory_id' => ['sometimes', 'nullable', 'integer', 'exists:product_subcategories,id'], 'name' => ['sometimes', 'required', 'string', 'max:180'], 'slug' => ['sometimes', 'required', 'string', 'max:200', 'alpha_dash', Rule::unique('products', 'slug')->ignore($this->route('product'))], 'description' => ['nullable', 'string', 'max:5000'], 'unit' => ['sometimes', 'string', 'max:40'], 'base_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'], 'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'], 'is_active' => ['sometimes', 'boolean']];
    }
}
