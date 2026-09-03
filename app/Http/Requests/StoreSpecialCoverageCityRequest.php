<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecialCoverageCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country_id' => [
                'required',
                'integer',
                Rule::exists('countries', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = trim((string) $this->input('name'));
        $name = preg_replace('/\s+/u', ' ', $input) ?? $input;

        $this->merge(['name' => $name]);
    }
}
