<?php

namespace App\Services\EventModeration;

use App\Models\EventRequestCreate;
use App\Models\EventsImges;
use Illuminate\Support\Facades\Storage;

class EventModerationPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(EventRequestCreate $request): array
    {
        $request->loadMissing([
            'events.city.translation',
            'events.sub_categorey.category',
            'events.sub_categorey.translation',
            'events.tags.translation',
            'events.images.tags.translation',
            'events.translations',
        ]);

        $event = $request->events;

        if ($event === null) {
            throw new \RuntimeException('The event request has no event.');
        }

        $subCategory = $event->sub_categorey;
        $category = $subCategory?->category;

        return [
            'request_id' => (int) $request->id,
            'event' => [
                'id' => (int) $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'is_real' => (bool) $event->is_real,
                'photography_type' => $event->photography_type,
                'start_date' => $this->dateValue($event->start_date),
                'end_date' => $this->dateValue($event->end_date),
                'time' => $event->time,
                'longitude' => $event->langitude,
                'latitude' => $event->lattitude,
                'is_historical' => (bool) $event->is_historical,
                'city' => $event->city ? [
                    'id' => (int) $event->city->id,
                    'name' => $event->city->name,
                    'translated_name' => $event->city->translation?->name,
                ] : null,
                'category' => $category ? [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                ] : null,
                'sub_category' => $subCategory ? [
                    'id' => (int) $subCategory->id,
                    'name' => $subCategory->name,
                    'translated_name' => $subCategory->translation?->name,
                ] : null,
                'translations' => $event->translations
                    ->map(fn ($translation): array => [
                        'locale' => $translation->locale,
                        'title' => $translation->title,
                        'description' => $translation->description,
                    ])
                    ->values()
                    ->all(),
            ],
            'tags' => $event->tags
                ->map(fn ($tag): array => [
                    'id' => (int) $tag->id,
                    'name' => $tag->name,
                    'translated_name' => $tag->translation?->name,
                    'mode' => $tag->mode,
                ])
                ->values()
                ->all(),
            'media' => $event->images
                ->map(fn (EventsImges $media): array => [
                    'id' => (int) $media->id,
                    'type' => $media->type,
                    'preview_url' => $this->publicMediaUrl($media->preview_url),
                    'full_url' => $this->publicMediaUrl($media->full_url),
                    'width' => $media->width,
                    'height' => $media->height,
                    'size' => $media->size,
                    'description' => $media->description,
                    'validation_status' => $media->validation_status,
                    'validation_message' => $media->validation_message,
                    'tags' => $media->tags
                        ->map(fn ($tag): array => [
                            'id' => (int) $tag->id,
                            'name' => $tag->name,
                            'translated_name' => $tag->translation?->name,
                            'mode' => $tag->mode,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return method_exists($value, 'toIso8601String')
            ? $value->toIso8601String()
            : (string) $value;
    }

    private function publicMediaUrl(mixed $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('/^(https?:)?\/\//i', $path) === 1) {
            return $path;
        }

        return Storage::disk('public')->url(
            preg_replace('#^(?:/?storage/|/?public/)#', '', $path)
        );
    }
}
