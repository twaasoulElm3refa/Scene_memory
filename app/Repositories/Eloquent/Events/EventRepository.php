<?php

namespace App\Repositories\Eloquent\Events;

use App\Models\Event_Tags;
use App\Models\EventViews;
use App\Models\Events;
use App\Models\EventsImges;
use App\Repositories\Contracts\Events\EventRepositoryInterface;

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
        return Events::with('city', 'sub_categorey','user','images','comments','firstImage','likes','translation','views')->where('slug', $slug)->first();
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

    public function allActivePaginated(int $perPage)
    {
        return Events::with(['city.translation', 'sub_categorey.translation', 'translation', 'firstImage:id,event_id,preview_url'])
            ->where('is_active', 1)
            ->select('id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function historicalActivePaginated(int $perPage)
    {
        return Events::with(['city.translation', 'sub_categorey.translation', 'translation', 'firstImage:id,event_id,full_url'])
            ->where('is_active', 1)
            ->where('is_historical', 1)
            ->select('id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

   public function filteredActive(array $filters)
    {
        $parsedFilters = [];

        foreach ($filters as $key => $filter) {
            if (!is_numeric($key)) {
                $parsedFilters[$key] = $filter;
                continue;
            }

            if (!is_string($filter)) {
                continue;
            }

            if (str_contains($filter, ':>=')) {
                [$field, $value] = explode(':>=', $filter, 2);
                $parsedFilters[trim($field) . '_from'] = trim($value);
                continue;
            }

            if (str_contains($filter, ':<=')) {
                [$field, $value] = explode(':<=', $filter, 2);
                $parsedFilters[trim($field) . '_to'] = trim($value);
                continue;
            }

            if (!str_contains($filter, ':=')) {
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

        if (!empty($parsedFilters['tags_id'])) {
            $tagsValue = $parsedFilters['tags_id'];

            if (is_array($tagsValue)) {
                $tagsArray = $tagsValue;
            } else {
                $tagsArray = json_decode($tagsValue, true);

                if (!is_array($tagsArray)) {
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

        $subCategoryId = $parsedFilters['sub_category_id']
            ?? $parsedFilters['subCategoryId']
            ?? $parsedFilters['categoryId']
            ?? null;

        $from = $parsedFilters['from']
            ?? $parsedFilters['fromDate']
            ?? $parsedFilters['start_date_from']
            ?? null;

        $to = $parsedFilters['to']
            ?? $parsedFilters['toDate']
            ?? $parsedFilters['end_date_to']
            ?? null;

        $hasNormalFilters = !empty($cityId)
            || !empty($subCategoryId)
            || !empty($from)
            || !empty($to);

        $active = $parsedFilters['is_active'] ?? 1;

        $normalizeDate = function ($value) {
            if (!$value) {
                return null;
            }

            if (is_numeric($value)) {
                return \Carbon\Carbon::createFromTimestamp((int) $value)->toDateString();
            }

            return \Carbon\Carbon::parse($value)->toDateString();
        };

        $buildBaseQuery = function () use ($active) {
            return Events::query()
                ->select([
                    'id',
                    'user_id',
                    'city_id',
                    'title',
                    'start_date',
                    'langitude',
                    'lattitude',
                    'slug',
                    'sub_categorey_id',
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
        };

        $filteredEvents = collect();

        if ($hasNormalFilters || empty($tagsArray)) {
            $normalQuery = $buildBaseQuery();

            if ($cityId) {
                $normalQuery->where('city_id', (int) $cityId);
            }

            if ($subCategoryId) {
                $normalQuery->where('sub_categorey_id', (int) $subCategoryId);
            }

            if ($from) {
                $normalQuery->whereDate('start_date', '>=', $normalizeDate($from));
            }

            if ($to) {
                $normalQuery->whereDate('end_date', '<=', $normalizeDate($to));
            }

            $filteredEvents = $normalQuery
                ->orderBy('start_date')
                ->get();
        }

        $tagsEvents = collect();

        if (!empty($tagsArray)) {
            $eventIdsByTags = Event_Tags::query()
                ->whereIn('tag_id', $tagsArray)
                ->pluck('event_id')
                ->unique()
                ->values()
                ->all();

            if (!empty($eventIdsByTags)) {
                $tagsEvents = $buildBaseQuery()
                    ->whereIn('id', $eventIdsByTags)
                    ->orderBy('start_date')
                    ->get();
            }
        }

        return $filteredEvents
            ->merge($tagsEvents)
            ->unique('id')
            ->sortBy('start_date')
            ->values()
            ->toArray();
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
                ->with('user:id,name', 'translation', 'replies', 'replies.user:id,name'),
        ])
        ->withCount('comments')
        ->withCount('likes')
        ->withCount('views')
        ->where('slug', $slug)
        ->first();
    }

    public function findWithAdminRelationsById(int $id)
    {
        return Events::with('city:id,name', 'sub_categorey:id,name', 'user:id,name', 'firstImage', 'adminTranslation','images')->find($id);
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
        return Events::with('sub_categorey.translation', 'firstImage', 'translation', 'views','likes','city.translation')
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
