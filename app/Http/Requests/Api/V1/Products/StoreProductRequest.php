<?php

namespace App\Http\Requests\Api\V1\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['subcategory_id' => ['nullable', 'integer', 'exists:product_subcategories,id'], 'sku' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:products,sku'], 'name' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'string', 'max:200', 'alpha_dash', 'unique:products,slug'], 'description' => ['nullable', 'string', 'max:5000'], 'unit' => ['sometimes', 'string', 'max:40'], 'base_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'], 'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535']];
    }
}
