<?php

namespace App\Http\Requests;

class AdminEventsRequest extends EventsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_trending' => ['sometimes', 'boolean'],
        ];
    }
}
