<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['type' => $this->input('type', 'cart')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['single_media', 'collection', 'multiple_media', 'cart'])],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'media_id' => ['required_if:type,single_media', 'integer', 'min:1'],
            'collection_id' => ['required_if:type,collection', 'integer', 'min:1'],
            'media_ids' => ['required_if:type,multiple_media', 'array', 'min:1', 'max:100'],
            'media_ids.*' => ['integer', 'min:1'],
        ];
    }
}
