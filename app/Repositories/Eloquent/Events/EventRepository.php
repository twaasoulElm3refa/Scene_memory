<?php

namespace App\Repositories\Eloquent\Events;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\EventViews;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EventRepository implements EventRepositoryInterface
{
    public function create(array $data)
    {
        return Events::create($data);
    }

    public function trendingEvents()
    {
        return Events::select([
            'id',
            'user_id',
            'city_id',
            'title',
            'start_date',
            'slug',
        ])
            ->with(['user:id,name', 'firstImage:id,event_id,full_url', 'translation:id,event_id,title,locale,description'])
            ->withCount(['likes', 'views'])
            ->where('is_active', 1)
            ->where('is_trending', 1)
            ->orderByDesc('views_count')
            ->limit(4)
            ->get();
    }

    public function show($slug)
    {
        return Events::with('city', 'sub_categorey', 'user', 'images', 'comments', 'firstImage', 'likes', 'translation', 'views')->where('slug', $slug)->first();
    }

    public function findBySlug(string $slug)
    {
        return Events::where('slug', $slug)->first();
    }

    public function findBySlugOrFail(string $slug)
    {
        return Events::where('slug', $slug)->firstOrFail();
    }

    public function findById(int $id)
    {
        return Events::find($id);
    }

    public function findByIdOrFail(int $id)
    {
        return Events::findOrFail($id);
    }

    public function firstOrCreateView(int $eventId, string $ipAddress): void
    {
        EventViews::firstOrCreate(['event_id' => $eventId, 'ip_address' => $ipAddress]);
    }

    public function count(): int
    {
        return Events::count();
    }

    public function countByUserId(int $userId): int
    {
        return Events::where('user_id', $userId)->count();
    }

    public function countByCityIds($cityIds): int
    {
        return Events::whereIn('city_id', $cityIds)->count();
    }

    public function whereInCityIds($cityIds)
    {
        return Events::whereIn('city_id', $cityIds);
    }

    public function allActivePaginated(int $perPage, ?bool $isReal = null, array $filters = [])
    {
        $query = Events::with(['city.translation', 'sub_categorey.translation', 'translation', 'firstImage'])
            ->where('is_active', 1)
            ->select('id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id', 'is_real', 'is_historical', 'created_at');

        if ($isReal !== null) {
            $query->where('is_real', $isReal ? 1 : 0);
        }

        return $this->applyDirectoryFilters($query, $filters)->paginate($perPage);
    }

    public function historicalActivePaginated(int $perPage, array $filters = [])
    {
        $query = Events::with(['city.translation', 'sub_categorey.translation', 'translation', 'firstImage'])
            ->where('is_active', 1)
            ->where('is_historical', 1)
            ->select('id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id', 'is_real', 'is_historical', 'created_at');

        return $this->applyDirectoryFilters($query, $filters)->paginate($perPage);
    }

    private function applyDirectoryFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $countryId = $filters['country_id'] ?? null;
        $cityId = $filters['city_id'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $subCategoryId = $filters['sub_category_id'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($search !== '') {
            $likeSearch = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

            $query->where(function ($eventQuery) use ($likeSearch) {
                $eventQuery->where('title', 'like', $likeSearch)
                    ->orWhereHas('translation', function ($translationQuery) use ($likeSearch) {
                        $translationQuery->where('title', 'like', $likeSearch);
                    });
            });
        }

        if ($countryId) {
            $query->whereHas('city', function ($cityQuery) use ($countryId) {
                $cityQuery->where('country_id', (int) $countryId);
            });
        }

        if ($cityId) {
            $query->where('city_id', (int) $cityId);
        }

        if ($categoryId) {
            $query->whereHas('sub_categorey', function ($categoryQuery) use ($categoryId) {
                $categoryQuery->where('category_id', (int) $categoryId);
            });
        }

        if ($subCategoryId) {
            $query->where('sub_categorey_id', (int) $subCategoryId);
        }

        if ($from) {
            $query->whereDate('start_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('start_date', '<=', $to);
        }

        return match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderByRaw('start_date IS NULL ASC')->orderBy('start_date')->orderBy('id'),
            'title' => $query->orderByRaw('title IS NULL ASC')->orderBy('title')->orderBy('id'),
            default => $query->orderByRaw('start_date IS NULL ASC')->orderByDesc('start_date')->orderByDesc('id'),
        };
    }

    public function filteredActive(array $filters, int $perPage = 20, int $page = 1)
    {
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        $parsedFilters = [];

        foreach ($filters as $key => $filter) {
            if (! is_numeric($key)) {
                $parsedFilters[$key] = $filter;

                continue;
            }

            if (! is_string($filter)) {
                continue;
            }

            if (str_contains($filter, ':>=')) {
                [$field, $value] = explode(':>=', $filter, 2);
                $parsedFilters[trim($field).'_from'] = trim($value);

                continue;
            }

            if (str_contains($filter, ':<=')) {
                [$field, $value] = explode(':<=', $filter, 2);
                $parsedFilters[trim($field).'_to'] = trim($value);

                continue;
            }

            if (! str_contains($filter, ':=')) {
                continue;
            }

            [$field, $value] = explode(':=', $filter, 2);

            $field = trim($field);
            $value = trim($value);

            if ($value === 'true') {
                $value = 1;
            } elseif ($value === 'false') {
                $value = 0;
            } elseif (is_numeric($value)) {
                $value = (int) $value;
            }

            $parsedFilters[$field] = $value;
        }

        $tagsArray = [];

        if (! empty($parsedFilters['tags_id'])) {
            $tagsValue = $parsedFilters['tags_id'];

            if (is_array($tagsValue)) {
                $tagsArray = $tagsValue;
            } else {
                $tagsArray = json_decode($tagsValue, true);

                if (! is_array($tagsArray)) {
                    $tagsArray = explode(',', trim($tagsValue, '[]'));
                }
            }

            $tagsArray = collect($tagsArray)
                ->map(fn ($tag) => (int) $tag)
                ->filter(fn ($tag) => $tag > 0)
                ->unique()
                ->values()
                ->all();
        }

        $cityId = $parsedFilters['city_id'] ?? $parsedFilters['cityId'] ?? null;
        $countryId = $parsedFilters['country_id'] ?? $parsedFilters['countryId'] ?? null;

        $subCategoryId = $parsedFilters['sub_category_id']
            ?? $parsedFilters['subCategoryId']
            ?? null;

        $categoryId = $parsedFilters['category_id']
            ?? $parsedFilters['categoryId']
            ?? null;

        $from = $parsedFilters['from']
            ?? $parsedFilters['fromDate']
            ?? $parsedFilters['start_date_from']
            ?? null;

        $to = $parsedFilters['to']
            ?? $parsedFilters['toDate']
            ?? $parsedFilters['start_date_to']
            ?? $parsedFilters['end_date_to']
            ?? null;

        $searchQuery = trim((string) (
            $parsedFilters['search_query']
            ?? $parsedFilters['searchQuery']
            ?? $parsedFilters['q']
            ?? $parsedFilters['search']
            ?? ''
        ));

        $active = $parsedFilters['is_active'] ?? 1;

        $normalizeDate = function ($value) {
            if (! $value) {
                return null;
            }

            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        };

        $query = Events::query()
            ->select([
                'id',
                'user_id',
                'city_id',
                'title',
                'description',
                'start_date',
                'langitude',
                'lattitude',
                'slug',
                'photography_type',
                'sub_categorey_id',
                'is_active',
            ])
            ->with([
                'city' => function ($q) {
                    $q->select([
                        'id',
                        'country_id',
                        'name',
                    ]);
                },

                'city.translation' => function ($q) {
                    $q->select([
                        'id',
                        'city_id',
                        'locale',
                        'name',
                    ]);
                },

                'sub_categorey' => function ($q) {
                    $q->select([
                        'id',
                        'category_id',
                        'name',
                    ]);
                },

                'sub_categorey.translation' => function ($q) {
                    $q->select([
                        'id',
                        'category_id',
                        'locale',
                        'name',
                    ]);
                },

                'translation' => function ($q) {
                    $q->select([
                        'id',
                        'event_id',
                        'locale',
                        'title',
                        'description',
                    ]);
                },

                'firstImage' => function ($q) {
                    $q->select([
                        'id',
                        'event_id',
                        'full_url',
                    ]);
                },
            ])
            ->where('is_active', $active);

        if ($countryId) {
            $query->whereHas('city', function ($q) use ($countryId) {
                $q->where('country_id', (int) $countryId);
            });
        }

        if ($cityId) {
            $query->where('city_id', (int) $cityId);
        }

        if ($categoryId) {
            $query->whereHas('sub_categorey', function ($q) use ($categoryId) {
                $q->where('category_id', (int) $categoryId);
            });
        }

        if ($subCategoryId) {
            $query->where('sub_categorey_id', (int) $subCategoryId);
        }

        if (! empty($tagsArray)) {
            $query->where(function ($tagQuery) use ($tagsArray) {
                $tagQuery->whereExists(function ($subQuery) use ($tagsArray) {
                    $subQuery->selectRaw('1')
                        ->from('event__tags')
                        ->whereColumn('event__tags.event_id', 'events.id')
                        ->whereIn('event__tags.tag_id', $tagsArray);
                });

                $tagQuery->orWhereExists(function ($subQuery) use ($tagsArray) {
                    $subQuery->selectRaw('1')
                        ->from('events_images')
                        ->join(
                            'images_tags',
                            'images_tags.events_imges_id',
                            '=',
                            'events_images.id'
                        )
                        ->whereColumn('events_images.event_id', 'events.id')
                        ->whereIn('images_tags.tags_id', $tagsArray);
                });
            });
        }

        if ($from) {
            $query->where('start_date', '>=', $normalizeDate($from));
        }

        if ($to) {
            $query->where('start_date', '<=', $normalizeDate($to));
        }

        if ($searchQuery !== '') {
            $likeSearch = '%'.str_replace(['%', '_'], ['\%', '\_'], $searchQuery).'%';

            $query->where(function ($q) use ($likeSearch) {
                $q->where('title', 'like', $likeSearch)
                    ->orWhere('description', 'like', $likeSearch)
                    ->orWhereHas('translation', function ($translationQuery) use ($likeSearch) {
                        $translationQuery
                            ->where('title', 'like', $likeSearch)
                            ->orWhere('description', 'like', $likeSearch);
                    });
            });
        }

        return $query
            ->orderByRaw("
                CASE
                    WHEN photography_type = 'professional' THEN 0
                    WHEN photography_type = 'normal' THEN 1
                    ELSE 2
                END
            ")
            ->orderByRaw('start_date IS NULL ASC')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function searchDiscovery(
        array $filters,
        string $type = 'all',
        int $perPage = 20,
        int $page = 1,
        int $seed = 1
    ): LengthAwarePaginator {
        $type = in_array($type, ['all', 'event', 'image', 'video'], true) ? $type : 'all';
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        $seed = max(1, min($seed, 2147483646));

        $queries = match ($type) {
            'event' => [$this->discoveryEventIdentityQuery($filters, $seed)],
            'image' => [$this->discoveryMediaIdentityQuery($filters, 'image', $seed)],
            'video' => [$this->discoveryMediaIdentityQuery($filters, 'video', $seed)],
            default => [
                $this->discoveryEventIdentityQuery($filters, $seed),
                $this->discoveryMediaIdentityQuery($filters, 'image', $seed),
                $this->discoveryMediaIdentityQuery($filters, 'video', $seed),
            ],
        };

        $identityQuery = array_shift($queries);

        foreach ($queries as $query) {
            $identityQuery->unionAll($query);
        }

        $query = DB::query()->fromSub($identityQuery, 'discovery_results');

        if ($type === 'all') {
            $rotation = $seed % 3;
            $typeOrder = [
                'event' => (0 - $rotation + 3) % 3,
                'image' => (1 - $rotation + 3) % 3,
                'video' => (2 - $rotation + 3) % 3,
            ];

            $query->orderBy('mix_position')
                ->orderByRaw(
                    "CASE result_type WHEN 'event' THEN {$typeOrder['event']} WHEN 'image' THEN {$typeOrder['image']} ELSE {$typeOrder['video']} END"
                )
                ->orderBy('result_id');
        } else {
            $query->orderByRaw('sort_date IS NULL ASC')
                ->orderByDesc('sort_date')
                ->orderByDesc('result_id');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->hydrateDiscoveryResults($paginator);
    }

    private function discoveryEventIdentityQuery(array $filters, int $seed): QueryBuilder
    {
        $query = DB::table('events')
            ->selectRaw(
                "'event' AS result_type, events.id AS result_id, events.id AS event_id, events.start_date AS sort_date, ROW_NUMBER() OVER (ORDER BY ABS(((events.id * 1103515245) + ?) % 2147483647), events.id) AS mix_position",
                [$seed]
            )
            ->where('events.is_active', 1);

        $this->applyDiscoveryStructuralFilters($query, $filters);
        $this->applyDiscoverySearchFilter($query, $filters);
        $this->applyDiscoveryTagFilter($query, $filters);

        return $query;
    }

    private function discoveryMediaIdentityQuery(array $filters, string $type, int $seed): QueryBuilder
    {
        $query = DB::table('events_images')
            ->join('events', 'events.id', '=', 'events_images.event_id')
            ->selectRaw(
                '? AS result_type, events_images.id AS result_id, events.id AS event_id, events.start_date AS sort_date, ROW_NUMBER() OVER (ORDER BY ABS(((events_images.id * 1103515245) + ?) % 2147483647), events_images.id) AS mix_position',
                [$type, $seed]
            )
            ->where('events.is_active', 1)
            ->where('events_images.is_active', 1)
            ->where('events_images.type', $type);

        $this->applyDiscoveryStructuralFilters($query, $filters);
        $this->applyDiscoverySearchFilter($query, $filters, 'events_images');
        $this->applyDiscoveryTagFilter($query, $filters, 'events_images');

        return $query;
    }

    private function applyDiscoveryStructuralFilters(QueryBuilder $query, array $filters): void
    {
        if ($filters['country_id'] ?? null) {
            $query->whereExists(function ($cityQuery) use ($filters) {
                $cityQuery->selectRaw('1')
                    ->from('cities')
                    ->whereColumn('cities.id', 'events.city_id')
                    ->where('cities.country_id', (int) $filters['country_id']);
            });
        }

        if ($filters['city_id'] ?? null) {
            $query->where('events.city_id', (int) $filters['city_id']);
        }

        if ($filters['category_id'] ?? null) {
            $query->whereExists(function ($categoryQuery) use ($filters) {
                $categoryQuery->selectRaw('1')
                    ->from('sub_categoreys')
                    ->whereColumn('sub_categoreys.id', 'events.sub_categorey_id')
                    ->where('sub_categoreys.category_id', (int) $filters['category_id']);
            });
        }

        if ($filters['sub_category_id'] ?? null) {
            $query->where('events.sub_categorey_id', (int) $filters['sub_category_id']);
        }

        if ($filters['from'] ?? null) {
            $query->where('events.start_date', '>=', Carbon::parse($filters['from'])->toDateString());
        }

        if ($filters['to'] ?? null) {
            $query->where('events.start_date', '<=', Carbon::parse($filters['to'])->toDateString());
        }
    }

    private function applyDiscoverySearchFilter(
        QueryBuilder $query,
        array $filters,
        ?string $mediaTable = null
    ): void {
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search === '') {
            return;
        }

        $likeSearch = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        $query->where(function ($searchQuery) use ($likeSearch, $mediaTable) {
            $searchQuery->where('events.title', 'like', $likeSearch)
                ->orWhere('events.description', 'like', $likeSearch)
                ->orWhereExists(function ($translationQuery) use ($likeSearch) {
                    $translationQuery->selectRaw('1')
                        ->from('event_translations')
                        ->whereColumn('event_translations.event_id', 'events.id')
                        ->where(function ($translated) use ($likeSearch) {
                            $translated->where('event_translations.title', 'like', $likeSearch)
                                ->orWhere('event_translations.description', 'like', $likeSearch);
                        });
                });

            if ($mediaTable) {
                $searchQuery->orWhere("{$mediaTable}.description", 'like', $likeSearch)
                    ->orWhereExists(function ($translationQuery) use ($likeSearch, $mediaTable) {
                        $translationQuery->selectRaw('1')
                            ->from('image_translations')
                            ->whereColumn('image_translations.image_id', "{$mediaTable}.id")
                            ->where('image_translations.description', 'like', $likeSearch);
                    });
            }
        });
    }

    private function applyDiscoveryTagFilter(
        QueryBuilder $query,
        array $filters,
        ?string $mediaTable = null
    ): void {
        $tags = collect($filters['tags_id'] ?? [])
            ->map(fn ($tag) => (int) $tag)
            ->filter(fn ($tag) => $tag > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($tags)) {
            return;
        }

        $query->where(function ($tagQuery) use ($tags, $mediaTable) {
            $tagQuery->whereExists(function ($eventTagsQuery) use ($tags) {
                $eventTagsQuery->selectRaw('1')
                    ->from('event__tags')
                    ->whereColumn('event__tags.event_id', 'events.id')
                    ->whereNull('event__tags.deleted_at')
                    ->whereIn('event__tags.tag_id', $tags);
            });

            if ($mediaTable) {
                $tagQuery->orWhereExists(function ($mediaTagsQuery) use ($tags, $mediaTable) {
                    $mediaTagsQuery->selectRaw('1')
                        ->from('images_tags')
                        ->whereColumn('images_tags.events_imges_id', "{$mediaTable}.id")
                        ->whereIn('images_tags.tags_id', $tags);
                });
            } else {
                $tagQuery->orWhereExists(function ($mediaTagsQuery) use ($tags) {
                    $mediaTagsQuery->selectRaw('1')
                        ->from('events_images')
                        ->join('images_tags', 'images_tags.events_imges_id', '=', 'events_images.id')
                        ->whereColumn('events_images.event_id', 'events.id')
                        ->whereIn('images_tags.tags_id', $tags);
                });
            }
        });
    }

    private function hydrateDiscoveryResults(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $rows = $paginator->getCollection();
        $eventIds = $rows->pluck('event_id')->map(fn ($id) => (int) $id)->unique()->values();
        $mediaIds = $rows
            ->whereIn('result_type', ['image', 'video'])
            ->pluck('result_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $events = Events::with([
            'translation',
            'city.translation',
            'sub_categorey.translation',
            'sub_categorey.category.translation',
            'firstImage',
        ])->whereIn('id', $eventIds)->get()->keyBy('id');

        $media = EventsImges::with('translation')
            ->whereIn('id', $mediaIds)
            ->get()
            ->keyBy('id');

        $paginator->setCollection($rows->map(function ($row) use ($events, $media) {
            $event = $events->get((int) $row->event_id);

            if (! $event) {
                return null;
            }

            $mediaItem = $row->result_type === 'event'
                ? $event->firstImage
                : $media->get((int) $row->result_id);

            return $this->formatDiscoveryResult($row->result_type, $event, $mediaItem);
        })->filter()->values());

        return $paginator;
    }

    private function formatDiscoveryResult(string $resultType, Events $event, ?EventsImges $media): array
    {
        $eventTranslation = $event->translation;
        $mediaTranslation = $resultType === 'event' ? null : $media?->translation;
        $city = $event->city;
        $subCategory = $event->sub_categorey;
        $category = $subCategory?->category;
        $title = $eventTranslation?->title ?: $event->title;
        $eventDescription = $eventTranslation?->description ?: $event->description;
        $description = $mediaTranslation?->description ?: ($media?->description ?: $eventDescription);
        $mediaUrl = $media?->full_url;
        $thumbnailUrl = $media?->preview_url ?: $mediaUrl;

        $cityPayload = $city ? [
            'id' => $city->id,
            'country_id' => $city->country_id,
            'name' => $city->translation?->name ?: $city->name,
            'translation' => $city->translation ? [
                'name' => $city->translation->name,
                'locale' => $city->translation->locale,
            ] : null,
        ] : null;

        $categoryPayload = $category ? [
            'id' => $category->id,
            'name' => $category->translation?->name ?: $category->name,
            'translation' => $category->translation ? [
                'name' => $category->translation->name,
                'locale' => $category->translation->locale,
            ] : null,
        ] : null;

        $subCategoryPayload = $subCategory ? [
            'id' => $subCategory->id,
            'category_id' => $subCategory->category_id,
            'name' => $subCategory->translation?->name ?: $subCategory->name,
            'translation' => $subCategory->translation ? [
                'name' => $subCategory->translation->name,
                'locale' => $subCategory->translation->locale,
            ] : null,
        ] : null;

        return [
            'result_type' => $resultType,
            'id' => $resultType === 'event' ? $event->id : $media?->id,
            'event_id' => $event->id,
            'event_slug' => $event->slug,
            'slug' => $event->slug,
            'title' => $title,
            'description' => $description,
            'media_url' => $mediaUrl,
            'thumbnail_url' => $thumbnailUrl,
            'start_date' => $event->start_date,
            'city' => $cityPayload,
            'category' => $categoryPayload,
            'sub_category' => $subCategoryPayload,
            'sub_categorey' => $subCategoryPayload,
            'lattitude' => $event->lattitude,
            'langitude' => $event->langitude,
            'translation' => [
                'title' => $title,
                'description' => $eventDescription,
            ],
            'first_image' => $event->firstImage ? [
                'id' => $event->firstImage->id,
                'full_url' => $event->firstImage->full_url,
                'preview_url' => $event->firstImage->preview_url,
                'type' => $event->firstImage->type,
            ] : null,
        ];
    }

    public function randomActive(int $take = 8)
    {
        return Events::with('translation', 'city.translation', 'sub_categorey.translation', 'firstImage:id,full_url,event_id')
            ->inRandomOrder()
            ->where('is_active', 1)
            ->take($take)
            ->get();
    }

    public function gateEventsByCityIds($cityIds)
    {
        return Events::select('id', 'city_id', 'sub_categorey_id', 'start_date', 'slug', 'langitude', 'lattitude')
            ->with([
                'translation:id,event_id,title,description,locale',
                'city:id,country_id',
                'city.translation:id,city_id,name,locale',
                'sub_categorey:id',
                'sub_categorey.translation:id,category_id,name,locale',
                'firstImage:id,full_url,event_id',
            ])
            ->whereIn('city_id', $cityIds)
            ->get();
    }

    public function findSingleDetailedBySlug(string $slug)
    {
        return Events::with([
            'city.translation',
            'sub_categorey.translation',
            'user:id,name',
            'images' => fn ($q) => $q->where('is_active', 1),
            'translation',
            'comments' => fn ($q) => $q->latest('created_at')
                ->take(5)->withCount([
                    'interactions as support_count' => fn ($q) => $q->where('type', 'support'),
                    'interactions as exhibitions_count' => fn ($q) => $q->where('type', 'Exhibitions'),
                    'interactions as neutral_count' => fn ($q) => $q->where('type', 'neutral'),
                ])
                ->with('user:id,name', 'translation', 'images', 'replies', 'replies.user:id,name'),
        ])
            ->withCount('comments')
            ->withCount('likes')
            ->withCount('views')
            ->where('slug', $slug)
            ->first();
    }

    public function findWithAdminRelationsById(int $id)
    {
        return Events::with([
            'city:id,name',
            'tags:id,name,mode',
            'sub_categorey:id,name',
            'user:id,name',
            'firstImage',
            'adminTranslation',
            'images',
            'images.tags',
        ])->find($id);
    }

    public function wishlistEventsPaginated($eventIds, int $perPage = 5)
    {
        return Events::with([
            'city.translation',
            'sub_categorey.translation',
            'translation',
            'firstImage:id,event_id,preview_url',
        ])
            ->whereIn('id', $eventIds)
            ->select([
                'id',
                'user_id',
                'city_id',
                'title',
                'description',
                'start_date',
                'end_date',
                'time',
                'sub_categorey_id',
                'image',
                'slug',
                'created_at',
            ])
            ->paginate($perPage);
    }

    public function daily($today)
    {
        return Events::with('translation:event_id,title,id')
            ->where('is_active', 1)
            ->where(function ($query) use ($today) {
                $query->whereDate('start_date', $today)->orWhereDate('created_at', $today);
            })
            ->select('id', 'slug', 'title', 'start_date', 'end_date', 'langitude', 'lattitude')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function memoriesQuery()
    {
        return EventsImges::count();
    }

    public function creatorEvents(int $userId)
    {
        return Events::with('sub_categorey.translation', 'firstImage', 'translation', 'views', 'likes', 'city.translation')
            ->where('user_id', $userId)
            ->select(['id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id'])
            ->get();
    }

    public function dashboardEvents(int $userId)
    {
        return Events::with('city:id,name', 'sub_categorey:id,name', 'firstImage')
            ->where('is_active', 1)
            ->where('user_id', $userId)
            ->select(['id', 'user_id', 'title', 'slug', 'start_date', 'city_id', 'sub_categorey_id'])
            ->withCount('images')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
