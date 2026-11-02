<?php

namespace App\Http\Requests;

use App\Rules\SafeMedia;
use App\Rules\MediaUploadLimit;
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
            'url.*' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif,mp4,webm,mov', 'max:'.max(config('cloudinary_media.max_image_mb'), config('cloudinary_media.max_video_mb')) * 1024, new MediaUploadLimit, new SafeMedia],
        ];
    }
}
