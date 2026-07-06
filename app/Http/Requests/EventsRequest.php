<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class EventsRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'city_id' => 'required|exists:cities,id',
            'sub_categorey_id' => 'required|exists:sub_categoreys,id',
            'urls' => 'required|array|min:1',
            'urls.*' => 'required|file|mimes:jpeg,jpg,png,webp,gif,bmp,avif,heic,heif,tiff,tif,mp4,webm,ogg|max:20460',
            'start_date' => 'required',
            'lattitude' => 'nullable',
            'langitude' => 'nullable',
            'end_date' => 'required',
            'time' => 'nullable',
            'tags_id' => ['nullable', 'array', 'max:4'],
            'tags_id.*' => ['nullable', 'integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array', 'max:4'],
            'new_tags.*' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        dd($validator->errors()->toArray());
    }
}
