<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'categories_id' => 'nullable|exists:categories,id',
            'brands_id' => 'nullable|exists:brands,id',
            'image' => 'nullable',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'return_policy' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'sku' => 'nullable|string',
            'tax' => 'nullable|numeric',
        ];
    }
}