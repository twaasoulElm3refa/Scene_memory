<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventsMediaRequest extends FormRequest
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
            'url' => 'required|array|min:1',
            'url.*' => 'required|mimes:jpeg,jpg,png,webp,gif,mp4|max:5120',
        ];
    }
}
