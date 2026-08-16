<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
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
            'comment' => ['required', 'string'],
            'images' => ['sometimes', 'array', 'max:2'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'A comment can have a maximum of 2 images.',
            'images.*.image' => 'Each comment attachment must be a valid image.',
            'images.*.mimes' => 'Comment images must be JPG, JPEG, PNG, or WEBP files.',
            'images.*.max' => 'Each comment image must not exceed 5 MB.',
        ];
    }
}
