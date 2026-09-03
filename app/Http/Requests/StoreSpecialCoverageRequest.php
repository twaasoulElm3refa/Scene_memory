<?php

namespace App\Http\Requests;

use App\Models\SpecialCoverageRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecialCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_name' => ['required', 'string', 'max:255'],
            'event_description' => ['required', 'string', 'max:5000'],
            'country_id' => [
                'required',
                'integer',
                Rule::exists('countries', 'id')->whereNull('deleted_at'),
            ],
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')
                    ->where(fn ($query) => $query
                        ->where('country_id', $this->integer('country_id'))
                        ->whereNull('deleted_at')),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'event_type' => ['required', Rule::in(SpecialCoverageRequest::EVENT_TYPES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_name' => trim((string) $this->input('event_name')),
            'event_description' => trim((string) $this->input('event_description')),
        ]);
    }
}
