<?php

namespace App\Http\Requests;

use App\Rules\SafeMedia;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => 'required|array|min:1|max:8',
            'url.*' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif,mp4', 'max:5120', new SafeMedia],
        ];
    }
}
