<?php

namespace App\Http\Requests;

use App\Rules\SafeMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CountriesRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'name' => 'required|string|unique:countries,name',
            'code' => 'required|string',
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048', new SafeMedia],
        ];
    }
}
