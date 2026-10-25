<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'media_tier_id' => ['nullable', 'exists:media_tiers,id'],
            'photo_quota' => ['nullable', 'integer', 'min:0'],
            'video_quota' => ['nullable', 'integer', 'min:0'],
            'tier_limits' => ['nullable', 'array'],
            'tier_limits.*.media_tier_id' => ['required_with:tier_limits', 'integer', 'distinct', 'exists:media_tiers,id'],
            'tier_limits.*.photo_maximum' => ['required_with:tier_limits', 'integer', 'min:0'],
            'tier_limits.*.video_maximum' => ['required_with:tier_limits', 'integer', 'min:0'],
            'recommendation_value_target_bps' => ['nullable', 'integer', 'in:11000'],
            'max_accumulated_photos' => ['nullable', 'integer', 'min:0'],
            'max_accumulated_videos' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'allow_subsidized' => ['prohibited'],
            'bypass_financial_check' => ['prohibited'],
        ];
    }
}
