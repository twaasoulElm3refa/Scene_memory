# Tags Storage Audit

Last reviewed against the current workspace code.

This document audits where and how event tags, photo tags, and AI-generated tags are stored. It separates preparation/normalization steps from actual database writes.

## 1. Main Files In The Flow

### Frontend senders

- `resources/js/views/home/create_event.vue`
  - Sends per-photo `photo_tags_json[]` as an object with `tags_id` and `new_tags`: lines 808-815.
  - Sends event-level `new_tags[]`: line 857.
  - Sends event-level `tags_id[]`: line 859.

- `resources/js/views/home/create_historical.vue`
  - Sends per-photo `photo_tags_json[]` as `JSON.stringify(item.tags || [])`: line 690.
  - Sends event-level `tags_id[]`: line 731.

### Routes

- `routes/api.php`
  - `POST /api/v1/events/create/user`: line 169.
  - `POST /api/v1/events/historic/user`: line 170.
  - `POST /api/v1/tools/image-tags`: lines 432-434.
  - `GET /api/v1/tags/search`: line 122.
  - `GET /api/v1/tags`: line 123.

### Validation

- `app/Http/Requests/EventsRequest.php`
  - `tags_id`: lines 42-43.
  - `new_tags`: lines 44-45.
  - `photo_tags_json`: lines 48-49.

- `app/Http/Requests/GenerateImageTagsRequest.php`
  - Validates the standalone image-tags tool request: lines 42-77.

### Backend orchestration

- `app/Http/Controllers/api/home/EventUserCreateController.php`
  - Normal event creation starts at line 39.
  - Historic event creation starts at line 169.
  - Photo payload validation starts at line 315.
  - Photo metadata preparation starts at line 458.
  - Photo tag normalization starts at line 536.
  - Event tag synchronization starts at line 619.
  - Post-commit job dispatch starts at line 693.

- `app/Jobs/ProcessEventImageJob.php`
  - Creates `events_imges` rows and stores `tags_json`: lines 119-139.
  - Reads photo `tags_json` and writes image pivot tags: lines 141-168.

- `app/Jobs/GenerateEventAiTagsJob.php`
  - Loads event/images: lines 57-89.
  - Runs content moderation: lines 91-131.
  - Runs AI tag generation: lines 133-145.
  - Persists AI tags: lines 153-164.

- `app/Services/GenerateImageTagsService.php`
  - Moderation request flow: lines 27-70.
  - AI image/event tags request flow: lines 88-130.

- `app/Services/EventAiTagsPersistenceService.php`
  - Persists AI event and image tags inside a transaction: lines 21-66.

- `app/Services/TagResolverService.php`
  - Normalizes tag names, creates/restores `tags`, and returns ids: lines 10-86.

## 2. Manual Event Tags

### Input

Event-level tags arrive as:

- Existing tags: `tags_id[]`
- New user-written tags: `new_tags[]`

Normal create flow:

- `EventUserCreateController::create()` creates the event inside `DB::transaction(...)`: lines 48-145.
- The event is created at line 57.
- The event request row is created at line 58.
- Event tags are synced at line 65.

Historic create flow:

- `EventUserCreateController::historic()` creates the event inside `DB::transaction(...)`: lines 178-291.
- The event is created at line 190.
- The event request row is created at line 192.
- Event tags are synced at line 200.

### Normalization And Validation

`EventUserCreateController::syncEventTags()` starts at line 619.

- Reads `tags_id`: line 621.
- Reads `new_tags`: line 622.
- Normalizes existing ids: lines 632-637.
- Normalizes new tag names: lines 639-643.
- Enforces the current max-count check: lines 645-647.
  - Current code checks `> 10`.
  - Current exception text says `You can select up to 4 tags only`.
- Validates existing ids against `tags`: lines 649-651.

### Actual Database Writes

New tag creation/restoration is delegated to `TagResolverService`.

`app/Services/TagResolverService.php`:

```php
$tag = Tags::withTrashed()->firstOrCreate(
    ['slug' => $slug],
    ['name' => $name]
);
```

Actual lines: 19-22.

Soft-deleted tags are restored:

```php
if ($tag->trashed()) {
    $tag->restore();
}
```

Actual lines: 24-26.

If the tag exists with a blank name, the name is repaired:

```php
if (blank($tag->name)) {
    $tag->forceFill(['name' => $name])->save();
}
```

Actual lines: 28-30.

The event/tag pivot write happens in `EventUserCreateController::syncEventTags()`:

```php
$eventTag = Event_Tags::withTrashed()
    ->where('event_id', $eventId)
    ->where('tag_id', $tagId)
    ->first();
```

Actual lines: 668-672.

If the pivot exists and is soft-deleted, it is restored:

```php
$eventTag->restore();
```

Actual line: 676.

If there is no pivot, it is created:

```php
Event_Tags::create([
    'event_id' => $eventId,
    'tag_id' => $tagId,
]);
```

Actual lines: 682-685.

### Storage Result

- Tag records: `tags`
- Event relationship: `event__tags`
- Behavior: merge/restore only, not replace.
- Transaction: yes, inside the event creation transaction.

## 3. Manual Photo Tags

### Input

Photo-level tags arrive as `photo_tags_json[]`.

`create_event.vue` sends object shape:

```js
{
    tags_id: [...],
    new_tags: [...]
}
```

Actual lines: `resources/js/views/home/create_event.vue:808-815`.

`create_historical.vue` sends `JSON.stringify(item.tags || [])` at line 690.

### Validation

`EventUserCreateController::validateUserPhotoPayload()`:

- Requires `photo_tags_json` as an array: line 325.
- Requires each item to be JSON: line 326.
- Checks every uploaded photo has tags: lines 353-354 and 370-373.
- Checks each photo has max 10 tags: lines 376-377.

### Preparation Versus Actual Writes

`photoMetadataForIndex()` prepares metadata but does not write the image row itself:

- Normalizes photo tag payload: line 461.
- Resolves photo tag ids: line 462.
- Prepares `tags_json`: lines 474-477.
- Includes `tag_ids`: line 478.

Important: `tag_ids` is prepared in metadata, but the current `ProcessEventImageJob` does not use it directly. The job reads `tags_json` instead.

### Existing And New Photo Tags

`resolvePhotoTagIds()`:

- Normalizes existing `tags_id`: lines 496-501.
- Validates existing ids against `tags`: lines 503-505.
- Creates/restores new tags through `TagResolverService`: lines 509-515.
- Merges and de-duplicates ids: lines 519-523.

This creates new rows in `tags` before the image processing job is dispatched. It does not create `images_tags` rows in the controller.

### Actual Database Writes

The image row and its JSON copy are written in `ProcessEventImageJob`:

```php
$eventImage = EventsImges::create([
    ...
    'tags_json' => $this->metadata['tags_json'] ?? null,
    ...
]);
```

Actual lines: 119-139, with `tags_json` at line 131.

The image tag pivot rows are written after the image row exists:

```php
$eventImage->tags()->syncWithoutDetaching($tagIds);
```

Actual line: 167.

### Storage Result

- Tag records: `tags`
- Photo JSON copy: `events_imges.tags_json`
- Photo relationship: `images_tags`
- Behavior: merge only via `syncWithoutDetaching`.
- Transaction: image processing job is separate from the event creation transaction.
- Failure behavior: if `ProcessEventImageJob` fails before creating the image row, the image row and `images_tags` pivot are not created. Tags that were already created during controller metadata preparation can remain in `tags`.

## 4. AI Event And Image Tags

### Queue Flow

`EventUserCreateController::dispatchPostCommitJobs()`:

- If there are image jobs, dispatches a batch and runs `GenerateEventAiTagsJob::dispatch($eventId)` in the batch `finally`: lines 697-702.
- If no image jobs exist, dispatches `GenerateEventAiTagsJob` directly: lines 704-705.

`GenerateEventAiTagsJob`:

- Uses queue from `config('ai_tags.queue', 'default')`: line 30.
- Uses `WithoutOverlapping`: lines 44-50.
- Loads event: line 57.
- Loads stored images: lines 67-72.
- Creates image index mapping: lines 74-81.

### Moderation Versus Tag Generation

Moderation and tag generation use the same orchestrator service but separate request methods and payloads.

Moderation:

- `GenerateEventAiTagsJob` calls `flagEventContent(...)`: lines 101-106.
- `GenerateImageTagsService::flagEventContent()` builds moderation payload: line 40.
- Sends moderation request: line 41.
- Parses moderation response: lines 65-69.
- Saves `ai_flagged` directly on the event request row: lines 108-110.

Tag generation:

- `GenerateEventAiTagsJob` calls `handleStoredImages(...)`: lines 139-145.
- `GenerateImageTagsService::handleStoredImages()` starts at line 88.
- `requestTags()` sends tag request: lines 114-122.
- Parses tag response: lines 126-130.

### OpenRouter Request

`OpenRouterClient::sendTagsRequest()`:

```php
$response = $this->http
    ->withToken($this->apiKey())
    ->acceptJson()
    ->asJson()
    ->timeout(60)
    ->connectTimeout(10)
    ->post($this->endpoint(), $payload);
```

Actual lines: 23-29.

`OpenRouterPayloadBuilder::buildTagsPayload()` builds the AI tags payload:

- Model: line 41.
- Messages: lines 42-47.
- Temperature: line 48.
- Max tokens: line 49.

`PromptBuilder::buildTagsPrompt()` requests:

- `event_tags` from title/description only: line 34.
- `image_tags` from visible image content: line 35.
- Exact JSON structure: lines 45-54.

### AI Response Parsing

`TagsResponseParser::parse()`:

- Extracts content from `choices.0.message.content`: line 21.
- Throws on blank content: lines 23-28.
- Decodes JSON with `JSON_THROW_ON_ERROR`: line 38.
- Normalizes event tags: lines 58-61.
- Normalizes image tags: lines 63-88.
- Returns normalized structure: lines 90-93.

`TagNormalizer::normalize()`:

- Skips non-string tags: lines 20-23.
- Trims and collapses whitespace: line 25.
- De-duplicates case-insensitively: lines 31-38.
- Applies limit: lines 40-42.

### Actual Database Writes

`EventAiTagsPersistenceService::persist()` wraps persistence in `DB::transaction(...)`:

```php
DB::transaction(function () use ($event, $result, $imagesByIndex): void {
```

Actual line: 23.

AI event tags:

```php
$eventTagIds = $this->tagResolver->resolveIds($result['event_tags'] ?? []);
```

Actual line: 24.

Event pivot:

```php
$pivot = Event_Tags::withTrashed()->firstOrCreate([
    'event_id' => $event->getKey(),
    'tag_id' => $tagId,
]);
```

Actual lines: 27-30.

Soft-deleted pivot restore:

```php
if ($pivot->trashed()) {
    $pivot->restore();
}
```

Actual lines: 32-34.

AI image tags:

```php
$tagIds = $this->tagResolver->resolveIds($imageResult['tags'] ?? []);
```

Actual line: 57.

Image pivot:

```php
$imagesByIndex[$imageIndex]
    ->tags()
    ->syncWithoutDetaching($tagIds);
```

Actual lines: 60-62.

### Storage Result

- AI event tags: `tags` + `event__tags`
- AI image tags: `tags` + `images_tags`
- Behavior: merge/restore only, not replace.
- AI-generated tags are not marked as AI in the schema.
- AI image tags do not update `events_imges.tags_json`; that JSON is currently a copy of user/photo metadata.

## 5. Models, Tables, Migrations, And Indexes

### `tags`

Model: `app/Models/Tags.php`

- Table: line 15.
- Uses `SoftDeletes`: line 13.
- Guarded: line 17.
- Event relationship through `event__tags`: lines 24-32.
- Image relationship through `images_tags`: lines 34-37.
- Translations: lines 39-48.

Migration: `database/migrations/2026_02_08_135131_create_tags_table.php`

- `id`: line 15.
- `name`: line 16.
- `slug`: line 17.
- timestamps: line 18.
- soft deletes: line 19.

Indexes:

- `tags_slug_unique`: `database/migrations/2026_07_27_000001_add_unique_ai_tag_indexes.php`, lines 17-20.
- `tags_name_index` and `tags_slug_index`: `database/migrations/2026_07_11_092226_add_search_indexes_to_events_tables.php`, lines 45-48.

### `event__tags`

Model: `app/Models/Event_Tags.php`

- Table: line 14.
- Uses `SoftDeletes`: line 12.
- Guarded: line 15.
- Belongs to event: lines 17-20.
- Belongs to tag: lines 22-25.

Migration: `database/migrations/2026_02_08_135252_create_event__tags_table.php`

- `event_id`: line 18.
- `tag_id`: line 19.
- timestamps: line 20.
- soft deletes: line 21.

Indexes:

- Unique `event_id/tag_id`: `database/migrations/2026_07_27_000001_add_unique_ai_tag_indexes.php`, lines 23-29.
- Search indexes: `database/migrations/2026_07_11_092226_add_search_indexes_to_events_tables.php`, lines 27-30.

### `images_tags`

Model: `app/Models/ImagesTags.php`

- Table: line 9.
- Guarded: line 10.
- Belongs to `EventsImges`: lines 11-14.
- Belongs to `Tags`: lines 16-19.

Migration: `database/migrations/2026_06_29_075434_create_images_tags_table.php`

- `events_imges_id`: line 18.
- `tags_id`: line 19.
- timestamps: line 20.

Indexes:

- Unique `events_imges_id/tags_id`: `database/migrations/2026_07_27_000001_add_unique_ai_tag_indexes.php`, lines 32-38.
- Search indexes: `database/migrations/2026_07_11_092226_add_search_indexes_to_events_tables.php`, lines 32-35.

### `events_imges.tags_json`

Model: `app/Models/EventsImges.php`

- Table: line 9.
- Tags relationship: lines 28-31.

Migration: `database/migrations/2026_07_09_000002_add_photo_metadata_to_events_imges_table.php`

- Adds `tags_json`: lines 16-18.

### `tags_translations`

Model: `app/Models/TagsTranslations.php`

- Table: line 9.
- Belongs to tag: lines 12-15.

Migration: `database/migrations/2026_07_29_071810_create_tags_translations_table.php`

- `name`: line 17.
- `locale`: line 18.
- `tag_id`: line 19.
- Unique `tag_id/locale`: line 21.

I did not find this table being written in the event creation or AI tag generation flow.

### `image_tags`

Model: `app/Models/ImageTags.php`

- Empty scaffold model only.

Migration: `database/migrations/2026_07_23_113329_create_image_tags_table.php`

- Creates only `id` and timestamps.

I did not find this table being used for event/photo/AI tag storage.

## Summary Table

| Path | Source | Creates `tags`? | Relationship table | JSON storage | Queue? | Transaction? | Merge/Replace | Failure effect |
|---|---|---:|---|---|---:|---:|---|---|
| Manual event tags | `tags_id[]`, `new_tags[]` | Yes for `new_tags` | `event__tags` | No | No | Yes, event create transaction | Merge/restore | Exception rolls back event creation transaction |
| Manual photo tags | `photo_tags_json[]` | Yes for `new_tags` during metadata preparation | `images_tags` in image job | `events_imges.tags_json` | Yes, `ProcessEventImageJob` | Separate job, not original transaction | Merge via `syncWithoutDetaching` | Image job failure can prevent image row/pivot; pre-resolved `tags` may already exist |
| AI event tags | OpenRouter `event_tags` | Yes | `event__tags` | No | Yes, `GenerateEventAiTagsJob` | Yes, AI persistence transaction | Merge/restore | Parser/provider failure prevents AI persistence; event remains |
| AI image tags | OpenRouter `images[].tags` | Yes | `images_tags` | No | Yes, `GenerateEventAiTagsJob` | Yes, AI persistence transaction | Merge via `syncWithoutDetaching` | Parser/provider failure prevents AI persistence; event/images remain |
| Standalone image tag tool | `/tools/image-tags` | No | No | No | No | No | Response only | Returns generated tags to caller, no DB write |

## Direct Answers

- Manual event tags are stored in `tags` and `event__tags`.
- New manual event tags are created by `TagResolverService`.
- Existing manual event tags are linked after verifying ids exist in `tags`.
- Manual photo tags are stored in both `events_imges.tags_json` and `images_tags`.
- New manual photo tags are created before the image processing job is dispatched.
- AI event tags and AI image tags use the same final tag tables as manual tags.
- AI tags do not replace manual tags.
- There is no database-level source marker distinguishing user-created tags from AI-created tags.
- `event__tags` supports soft delete and restore.
- `tags` supports soft delete and restore.
- `images_tags` does not use soft deletes.
- Duplicate prevention exists in code and through unique indexes.
- The AI tags request is separate from the AI moderation request.
- The standalone `/tools/image-tags` endpoint returns generated tags but does not store them.
