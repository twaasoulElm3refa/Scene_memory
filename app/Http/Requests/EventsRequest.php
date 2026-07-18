<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'photography_type' => ['nullable', 'in:normal,professional'],
            'urls' => 'required_without:photos|array|min:1',
            'urls.*' => 'required|file|mimes:jpeg,jpg,png,webp,gif,bmp,avif,heic,heif,tiff,tif,mp4,webm,ogg|max:20460',
            'photos' => 'nullable|array|min:1',
            'photos.*' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif,bmp,avif,heic,heif,tiff,tif,mp4,webm,ogg|max:20460',
            'start_date' => 'required',
            'lattitude' => 'nullable',
            'langitude' => 'nullable',
            'end_date' => 'nullable',
            'time' => 'nullable',
            'tags_id' => ['nullable', 'array'],
            'tags_id.*' => ['nullable', 'integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*' => ['nullable', 'string', 'max:50'],
            'photo_descriptions' => ['nullable', 'array'],
            'photo_descriptions.*' => ['nullable', 'string', 'max:2000'],
            'photo_tags_json' => ['nullable', 'array'],
            'photo_tags_json.*' => ['nullable', 'json'],
            'photo_widths' => ['nullable', 'array'],
            'photo_widths.*' => ['nullable', 'numeric'],
            'photo_heights' => ['nullable', 'array'],
            'photo_heights.*' => ['nullable', 'numeric'],
            'photo_quality_scores' => ['nullable', 'array'],
            'photo_quality_scores.*' => ['nullable', 'numeric'],
            'photo_sharpness_scores' => ['nullable', 'array'],
            'photo_sharpness_scores.*' => ['nullable', 'numeric'],
            'photo_blur_scores' => ['nullable', 'array'],
            'photo_blur_scores.*' => ['nullable', 'numeric'],
            'photo_validation_statuses' => ['nullable', 'array'],
            'photo_validation_statuses.*' => ['nullable', 'string', 'max:50'],
            'photo_validation_messages' => ['nullable', 'array'],
            'photo_validation_messages.*' => ['nullable', 'string', 'max:2000'],
            'media_prices' => ['nullable', 'array'],
            'media_prices.*' => ['nullable', 'numeric', 'min:0'],
            'media_widths' => ['nullable', 'array'],
            'media_widths.*' => ['nullable', 'numeric'],
            'media_heights' => ['nullable', 'array'],
            'media_heights.*' => ['nullable', 'numeric'],
            'media_quality_scores' => ['nullable', 'array'],
            'media_quality_scores.*' => ['nullable', 'numeric'],
            'media_sharpness_scores' => ['nullable', 'array'],
            'media_sharpness_scores.*' => ['nullable', 'numeric'],
            'media_contrast_scores' => ['nullable', 'array'],
            'media_contrast_scores.*' => ['nullable', 'numeric'],
            'media_brightness_scores' => ['nullable', 'array'],
            'media_brightness_scores.*' => ['nullable', 'numeric'],
            'media_file_sizes_mb' => ['nullable', 'array'],
            'media_file_sizes_mb.*' => ['nullable', 'numeric'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
