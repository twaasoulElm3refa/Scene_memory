<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Comment pages are cached per event, so a new comment, reply or reaction only
 * invalidates its own event instead of every event's comments.
 */
final class CommentCache
{
    public const LOCALES = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr'];

    /** @return array<int, string> */
    public static function tags(int $eventId): array
    {
        return ['comments', 'comments_event_'.$eventId];
    }

    /** Forgets the event's comment pages and its cached detail page in every locale. */
    public static function forgetEvent(int $eventId, ?string $eventSlug): void
    {
        $slug = strtolower(trim((string) $eventSlug));
        if ($slug !== '') {
            foreach (self::LOCALES as $locale) {
                Cache::tags(['events'])->forget('event_'.$slug.'_'.$locale);
            }
        }

        Cache::tags(['comments_event_'.$eventId])->flush();
    }
}
