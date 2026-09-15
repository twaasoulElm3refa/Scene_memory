<?php

namespace App\Http\Requests;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'section' => $this->input('section', 'all'),
            'period' => $this->input('period', 'all'),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 12),
            'timezone' => $this->input('timezone', config('app.timezone', 'UTC')),
        ]);
    }

    public function rules(): array
    {
        return [
            'section' => [
                'required',
                Rule::in([
                    'all',
                    'events',
                    'likes',
                    'comments',
                    'comment_images',
                    'replies',
                    'comment_interactions',
                    'wishlists',
                ]),
            ],
            'period' => ['required', Rule::in(['all', 'today', 'this_week', 'this_month', 'custom'])],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }
}
