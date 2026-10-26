<?php

namespace App\Support;

use App\Models\Events;
use App\Models\EventsImges;

/**
 * Serializing a media item reads its event (published state and owner). Loaded
 * lazily that is one query per item, also on cache hits. These helpers give the
 * items their event up front: the parent that is already in memory when it has the
 * columns that are read, otherwise one query for all missing events.
 */
final class MediaParents
{
    private const MEDIA_RELATIONS = ['images', 'firstImage', 'coverImage'];

    /** Hands an event to the media it has loaded, before that media is serialized. */
    public static function adopt(Events $event): void
    {
        $attributes = $event->getAttributes();
        $complete = array_key_exists('is_active', $attributes) && array_key_exists('user_id', $attributes);
        $orphans = [];
        foreach (self::MEDIA_RELATIONS as $relation) {
            if (! $event->relationLoaded($relation)) {
                continue;
            }
            $loaded = $event->getRelation($relation);
            foreach ($loaded instanceof EventsImges ? [$loaded] : ($loaded ?? []) as $media) {
                if (! $media instanceof EventsImges || $media->relationLoaded('events') || (int) $media->event_id !== (int) $event->getKey()) {
                    continue;
                }
                if ($complete) {
                    $media->setRelation('events', $event);
                } else {
                    $orphans[] = $media;
                }
            }
        }
        self::prime($orphans);
    }

    /** Loads the events of every item that does not have one yet, in one query. */
    public static function prime(iterable $media): void
    {
        $missing = [];
        foreach ($media as $item) {
            if ($item instanceof EventsImges && ! $item->relationLoaded('events') && $item->event_id) {
                $missing[(int) $item->event_id][] = $item;
            }
        }
        if ($missing === []) {
            return;
        }

        // Full rows, like the lazy load this replaces.
        $events = Events::query()->whereIn('id', array_keys($missing))->get()->keyBy('id');
        foreach ($missing as $eventId => $items) {
            foreach ($items as $item) {
                $item->setRelation('events', $events->get($eventId));
            }
        }
    }
}
