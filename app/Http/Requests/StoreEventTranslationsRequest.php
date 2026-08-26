<?php

namespace App\Http\Requests;

use App\Models\Events;
use App\Support\EventTranslationLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $event = $this->route('event');
        $eventId = $event instanceof Events ? (int) $event->id : (int) $event;

        return [
            'event_id' => ['required', 'integer', Rule::in([$eventId])],
            'translations' => ['required', 'array', 'size:'.count(EventTranslationLocales::ALL)],
            'translations.*.locale' => [
                'required',
                'string',
                'distinct:strict',
                Rule::in(EventTranslationLocales::ALL),
            ],
            'translations.*.title' => ['required', 'string', 'max:10000'],
            'translations.*.description' => ['required', 'string'],
        ];
    }
}
