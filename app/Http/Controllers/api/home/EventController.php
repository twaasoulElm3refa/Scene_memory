<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CityNomination;
use App\Models\Events;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EventController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 3600;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CityRepositoryInterface $cityRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {
    }

    public function all()
    {
        $page = request()->get('page', 1);
        $perPage = 8;
        $isRealValue = request()->get('is_real');
        $normalizedIsReal = null;

        if ($isRealValue !== null && $isRealValue !== '' && $isRealValue !== 'all') {
            if (is_bool($isRealValue)) {
                $normalizedIsReal = $isRealValue;
            } else {
                $isRealValue = strtolower(trim((string) $isRealValue));

                if (in_array($isRealValue, ['1', 'true', 'real'], true)) {
                    $normalizedIsReal = true;
                } elseif (in_array($isRealValue, ['0', 'false', 'general'], true)) {
                    $normalizedIsReal = false;
                }
            }
        }

        $isRealCacheValue = $normalizedIsReal === null ? 'all' : ($normalizedIsReal ? '1' : '0');
        $cacheKey = "events_page_{$page}_per_{$perPage}_is_real_{$isRealCacheValue}_".app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($perPage, $normalizedIsReal) {
            return $this->eventRepository->allActivePaginated($perPage, $normalizedIsReal);
        });

        return $this->success($events, 'All events');
    }

    public function trending()
    {
        $cacheKey = "events_trending_".app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, 60, function () {
            return $this->eventRepository->trendingEvents();
        });

        return $this->success($events, 'Trending events');
    }

    public function historical()
    {
        $page = request()->get('page', 1);
        $perPage = 8;

        $cacheKey = "events_historical_page_{$page}_per_{$perPage}_".app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return $this->eventRepository->historicalActivePaginated($perPage);
        });

        return $this->success($events, 'All events');
    }

    public function index(Request $request)
    {
        $filterIdRule = static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '' || $value === 'all') {
                return;
            }

            if (!filter_var($value, FILTER_VALIDATE_INT) || (int) $value < 1) {
                $fail("The {$attribute} field must be a valid id.");
            }
        };

        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'searchQuery' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', $filterIdRule],
            'countryId' => ['nullable', $filterIdRule],
            'city_id' => ['nullable', $filterIdRule],
            'cityId' => ['nullable', $filterIdRule],
            'category_id' => ['nullable', $filterIdRule],
            'categoryId' => ['nullable', $filterIdRule],
            'sub_category_id' => ['nullable', $filterIdRule],
            'subCategoryId' => ['nullable', $filterIdRule],
            'tags' => ['nullable'],
            'tags_id' => ['nullable'],
            'tags_id.*' => ['integer', 'min:1'],
            'tagsIds' => ['nullable'],
            'tagsIds.*' => ['integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'from_date' => ['nullable', 'date'],
            'fromDate' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'toDate' => ['nullable', 'date', 'after_or_equal:fromDate'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $normalizeFilterId = static function ($value): ?int {
            if ($value === null || $value === '' || $value === 'all') {
                return null;
            }

            $id = (int) $value;

            return $id > 0 ? $id : null;
        };

        $cityId = $normalizeFilterId(
            $request->query('city_id', $request->query('cityId', $request->route('city_id')))
        );
        $subCategoryId = $normalizeFilterId(
            $request->query('sub_category_id', $request->query('subCategoryId', $request->route('sub_category_id')))
        );
        $categoryId = $normalizeFilterId(
            $request->query('category_id', $request->query('categoryId'))
        );
        $countryId = $normalizeFilterId(
            $request->query('country_id', $request->query('countryId'))
        );

        $tagsIds = $request->query(
            'tags_id',
            $request->query('tagsIds', $request->query('tags', []))
        );

        if (!is_array($tagsIds)) {
            $tagsIds = explode(',', (string) $tagsIds);
        }

        $tagsIds = collect($tagsIds)
            ->flatMap(fn ($id) => is_string($id) ? explode(',', $id) : [$id])
            ->filter(fn ($id) => $id !== null && $id !== '' && $id !== 'all')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $from = $request->query('from', $request->query('from_date', $request->query('fromDate')));
        $to = $request->query('to', $request->query('to_date', $request->query('toDate')));

        $searchQuery = trim((string) $request->query('q', $request->query('searchQuery', $request->query('search', ''))));

        $perPage = (int) $request->query('per_page', $request->query('perPage', 20));
        $page = (int) $request->query('page', 1);

        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $filters = [
            'is_active:=true',
        ];

        if ($countryId) {
            $filters[] = 'country_id:=' . $countryId;
        }

        if (!empty($tagsIds)) {
            $filters[] = 'tags_id:=[' . implode(',', $tagsIds) . ']';
        }

        if ($cityId) {
            $filters[] = 'city_id:=' . $cityId;
        }

        if ($categoryId) {
            $filters[] = 'category_id:=' . $categoryId;
        }

        if ($subCategoryId) {
            $filters[] = 'sub_category_id:=' . $subCategoryId;
        }

        if ($from) {
            $filters[] = 'start_date:>=' . Carbon::parse($from)->toDateString();
        }

        if ($to) {
            $filters[] = 'start_date:<=' . Carbon::parse($to)->toDateString();
        }

        if ($searchQuery !== '') {
            $filters['search_query'] = $searchQuery;
        }

        $events = $this->eventRepository->filteredActive(
            filters: $filters,
            perPage: $perPage,
            page: $page
        );

        return $this->success($events, 'Events');
    }

    public function MarkerSearch()
    {
        $city = request('city');

        if (! $city) {
            return $this->error('city not found', 404);
        }
        $city = str_replace(['منطقة', 'مدينة', 'محافظة'], '', $city);
        $city = trim($city);
        $cacheKey = 'city_events_'.strtolower($city).'_'.app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, now()->addHours(6), function () use ($city) {
            $DBCITY = $this->cityRepository->firstByNameLike($city);

            if (! $DBCITY) {
                return null;
            }

            return $DBCITY->events()
                ->with('city.translation', 'sub_categorey.translation', 'translation','firstImage:id,event_id,full_url')
                ->select('id', 'slug', 'title', 'image', 'start_date', 'sub_categorey_id', 'city_id','langitude','lattitude')
                ->where('is_active', 1)
                ->latest()
                ->get();
        });

        if (! $events) {
            return $this->error('City not found in DB', 404);
        }

        return $this->success($events, 'City is found');
    }

    public function searchMarkerByPlace(Request $request)
    {
        $place = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'osm_id' => [
                'nullable',
                function (string $attribute, mixed $value, $fail) {
                    if (! is_string($value) && ! is_int($value)) {
                        $fail("The {$attribute} field must be a string or integer.");
                    }
                },
            ],
            'osm_type' => ['nullable', 'string'],
            'boundingbox' => ['nullable', 'array', 'size:4'],
            'boundingbox.*' => ['nullable', 'numeric'],
            'display_name' => ['nullable', 'string'],
        ]);

        $place['osm_id'] = isset($place['osm_id']) ? (string) $place['osm_id'] : null;
        $place['osm_type'] = $this->normalizeOsmType($place['osm_type'] ?? null);
        $place['country_code'] = isset($place['country_code'])
            ? strtolower($place['country_code'])
            : null;

        Log::info('Marker search by place request', $place);

        $nomination = $this->resolveCityNomination($place);
        $method = $nomination && $this->nominationHasBoundingBox($nomination)
            ? 'bbox'
            : 'radius';

        $events = $method === 'bbox'
            ? $this->eventsInsideNomination($nomination)
            : $this->eventsWithinRadius((float) $place['lat'], (float) $place['lng']);

        Log::info('City nomination resolved', [
            'nomination_id' => $nomination?->id,
            'osm_id' => $nomination?->osm_id,
            'osm_type' => $nomination?->osm_type,
            'method' => $method,
        ]);

        return response()->json([
            'success' => true,
            'data' => $events,
            'meta' => [
                'source' => $nomination ? 'city_nominations' : 'fallback',
                'method' => $method,
                'nomination_id' => $nomination?->id,
                'osm_id' => $nomination?->osm_id,
                'osm_type' => $nomination?->osm_type,
            ],
        ]);
    }

    public function single()
    {
        $slug = request('slug');
        if (! $slug || ! is_string($slug)) {
            return $this->error('Invalid slug', 400);
        }

        $cacheKey = $this->eventCacheKey($slug);

            $event = Cache::tags(['events'])->remember($cacheKey, now()->addHours(6), function () use ($slug) {
            return $this->eventRepository->findSingleDetailedBySlug($slug);
        });
        if (! $event) {
            return $this->error('Event not found', 404);
        }
        $this->eventRepository->firstOrCreateView($event->id, (string) request()->getClientIp());
        $this->normalizeEventMedia($event);

        return $this->success($event, 'Event data');
    }

    public function count()
    {
        $cacheKey = 'events_count';

        $count = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->eventRepository->count();
        });

        return $this->success($count, 'Events count');
    }

    public function memories()
    {
        $cacheKey = 'memories';

        $memories = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->eventImageRepository->count();
        });

        return $this->success($memories, 'Memories');

    }

    public function getImageAttribute($value)
    {
        return $value ?: 'https://via.placeholder.com/300x200?text=Event+Image';
    }

    public function daily()
    {
        $cacheKey = 'daily_events_'.app()->getLocale();
        $today = Carbon::today();

        $daily = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($today) {
            return $this->eventRepository->daily($today);
        });

        return $this->success($daily, 'Daily events');
    }

    private function eventCacheKey(string $slug): string
    {
        return 'event_' . strtolower(trim($slug)) . '_' . app()->getLocale();
    }

    private function normalizeEventMedia($event): void
    {
        if ($event->images && $event->images->isNotEmpty()) {
            foreach ($event->images as $image) {
                $this->normalizeMedia($image);
            }
        }

        if ($event->firstImage) {
            $this->normalizeMedia($event->firstImage);
        }
    }

    private function normalizeMedia($media): void
    {
        $rawUrl = $media->url ?? $media->preview_url ?? $media->full_url ?? $media->video ?? null;

        $media->url = $this->storageUrl($rawUrl);
        $media->preview_url = $this->storageUrl($media->preview_url ?? $rawUrl);
        $media->full_url = $this->storageUrl($media->full_url ?? $rawUrl);
        $media->type = $media->type ?: ($this->isVideoPath($rawUrl) ? 'video' : 'image');
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::url($path);
    }

    private function isVideoPath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'mov', 'webm', 'avi', 'mkv'], true);
    }

    private function resolveCityNomination(array $place): ?CityNomination
    {
        $osmId = $place['osm_id'] ?? null;
        $osmType = $place['osm_type'] ?? null;

        if ($osmId && $osmType) {
            $nomination = CityNomination::firstOrCreate(
                [
                    'osm_id' => $osmId,
                    'osm_type' => $osmType,
                ],
                [
                    'center_lat' => $place['lat'],
                    'center_lng' => $place['lng'],
                ]
            );

            if ($this->nominationNeedsBoundary($nomination)) {
                $result = $this->lookupNominatimPlace($osmId, $osmType)
                    ?? $this->searchNominatimPlace($place);

                if ($result) {
                    $this->hydrateNomination($nomination, $result);
                }
            }

            return $nomination->refresh();
        }

        $nomination = $this->findNominationContainingPoint(
            (float) $place['lat'],
            (float) $place['lng']
        );

        if ($nomination) {
            return $nomination;
        }

        $result = $this->searchNominatimPlace($place);
        if (! $result) {
            return null;
        }

        $resultOsmId = isset($result['osm_id']) ? (string) $result['osm_id'] : null;
        $resultOsmType = $this->normalizeOsmType($result['osm_type'] ?? null);

        if (! $resultOsmId || ! $resultOsmType) {
            return null;
        }

        $nomination = CityNomination::firstOrCreate(
            [
                'osm_id' => $resultOsmId,
                'osm_type' => $resultOsmType,
            ],
            [
                'center_lat' => $result['lat'] ?? $place['lat'],
                'center_lng' => $result['lon'] ?? $place['lng'],
            ]
        );

        if ($this->nominationNeedsBoundary($nomination)) {
            $this->hydrateNomination($nomination, $result);
        }

        return $nomination->refresh();
    }

    private function findNominationContainingPoint(float $lat, float $lng): ?CityNomination
    {
        return CityNomination::query()
            ->whereNotNull('bbox_min_lat')
            ->whereNotNull('bbox_max_lat')
            ->whereNotNull('bbox_min_lng')
            ->whereNotNull('bbox_max_lng')
            ->where('bbox_min_lat', '<=', $lat)
            ->where('bbox_max_lat', '>=', $lat)
            ->where('bbox_min_lng', '<=', $lng)
            ->where('bbox_max_lng', '>=', $lng)
            ->orderByRaw(
                '(bbox_max_lat - bbox_min_lat) * (bbox_max_lng - bbox_min_lng) ASC'
            )
            ->first();
    }

    private function lookupNominatimPlace(string $osmId, string $osmType): ?array
    {
        $response = $this->nominatimGet('/lookup', [
            'format' => 'jsonv2',
            'osm_ids' => $osmType.$osmId,
            'addressdetails' => 1,
            'polygon_geojson' => 1,
        ]);

        return $response[0] ?? null;
    }

    private function searchNominatimPlace(array $place): ?array
    {
        $query = trim(collect([
            $place['city'] ?? null,
            $place['state'] ?? null,
            $place['country_code'] ?? null,
        ])->filter()->implode(', '));

        if ($query === '') {
            return null;
        }

        $response = $this->nominatimGet('/search', [
            'format' => 'jsonv2',
            'q' => $query,
            'addressdetails' => 1,
            'polygon_geojson' => 1,
            'limit' => 1,
        ]);

        return $response[0] ?? null;
    }

    private function nominatimGet(string $path, array $query): array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('services.nominatim.user_agent'))
                ->timeout(10)
                ->get(rtrim(config('services.nominatim.url'), '/').$path, $query);

            if (! $response->successful()) {
                Log::warning('Nominatim request failed', [
                    'path' => $path,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();

            return is_array($data) ? $data : [];
        } catch (Throwable $exception) {
            Log::warning('Nominatim request failed', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function hydrateNomination(CityNomination $nomination, array $result): void
    {
        $bbox = $result['boundingbox'] ?? null;
        $hasBoundingBox = is_array($bbox)
            && count($bbox) === 4
            && collect($bbox)->every(fn ($value) => is_numeric($value));

        $nomination->forceFill([
            'center_lat' => is_numeric($result['lat'] ?? null)
                ? (float) $result['lat']
                : $nomination->center_lat,
            'center_lng' => is_numeric($result['lon'] ?? null)
                ? (float) $result['lon']
                : $nomination->center_lng,
            'bbox_min_lat' => $hasBoundingBox ? (float) $bbox[0] : $nomination->bbox_min_lat,
            'bbox_max_lat' => $hasBoundingBox ? (float) $bbox[1] : $nomination->bbox_max_lat,
            'bbox_min_lng' => $hasBoundingBox ? (float) $bbox[2] : $nomination->bbox_min_lng,
            'bbox_max_lng' => $hasBoundingBox ? (float) $bbox[3] : $nomination->bbox_max_lng,
            'polygon_geojson' => isset($result['geojson'])
                ? json_encode($result['geojson'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $nomination->polygon_geojson,
        ])->save();
    }

    private function nominationNeedsBoundary(CityNomination $nomination): bool
    {
        return ! $nomination->polygon_geojson || ! $this->nominationHasBoundingBox($nomination);
    }

    private function nominationHasBoundingBox(CityNomination $nomination): bool
    {
        return $nomination->bbox_min_lat !== null
            && $nomination->bbox_max_lat !== null
            && $nomination->bbox_min_lng !== null
            && $nomination->bbox_max_lng !== null;
    }

    private function eventsInsideNomination(CityNomination $nomination): Collection
    {
        return $this->markerEventQuery()
            ->whereRaw(
                'CAST(lattitude AS DECIMAL(10, 7)) BETWEEN ? AND ?',
                [$nomination->bbox_min_lat, $nomination->bbox_max_lat]
            )
            ->whereRaw(
                'CAST(langitude AS DECIMAL(10, 7)) BETWEEN ? AND ?',
                [$nomination->bbox_min_lng, $nomination->bbox_max_lng]
            )
            ->latest()
            ->get();
    }

    private function eventsWithinRadius(float $lat, float $lng, float $radiusKm = 25): Collection
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $radiusMeters = $radiusKm * 1000;

            return $this->markerEventQuery()
                ->selectRaw(
                    'ST_Distance_Sphere(POINT(langitude, lattitude), POINT(?, ?)) AS distance_meters',
                    [$lng, $lat]
                )
                ->whereRaw(
                    'ST_Distance_Sphere(POINT(langitude, lattitude), POINT(?, ?)) <= ?',
                    [$lng, $lat, $radiusMeters]
                )
                ->orderBy('distance_meters')
                ->get();
        }

        $latDelta = $radiusKm / 111.32;
        $cosLatitude = max(abs(cos(deg2rad($lat))), 0.01);
        $lngDelta = $radiusKm / (111.32 * $cosLatitude);

        return $this->markerEventQuery()
            ->whereRaw(
                'CAST(lattitude AS DECIMAL(10, 7)) BETWEEN ? AND ?',
                [$lat - $latDelta, $lat + $latDelta]
            )
            ->whereRaw(
                'CAST(langitude AS DECIMAL(10, 7)) BETWEEN ? AND ?',
                [$lng - $lngDelta, $lng + $lngDelta]
            )
            ->get()
            ->map(function (Events $event) use ($lat, $lng) {
                $event->distance_meters = $this->distanceInMeters(
                    $lat,
                    $lng,
                    (float) $event->lattitude,
                    (float) $event->langitude
                );

                return $event;
            })
            ->filter(fn (Events $event) => $event->distance_meters <= $radiusKm * 1000)
            ->sortBy('distance_meters')
            ->values();
    }

    private function markerEventQuery(): Builder
    {
        return Events::query()
            ->with(
                'city.translation',
                'sub_categorey.translation',
                'translation',
                'firstImage:id,event_id,full_url'
            )
            ->select(
                'id',
                'slug',
                'title',
                'image',
                'start_date',
                'sub_categorey_id',
                'city_id',
                'langitude',
                'lattitude'
            )
            ->where('is_active', 1);
    }

    private function normalizeOsmType(?string $osmType): ?string
    {
        return match (strtolower(trim((string) $osmType))) {
            'n', 'node' => 'N',
            'w', 'way' => 'W',
            'r', 'relation' => 'R',
            default => null,
        };
    }

    private function distanceInMeters(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): float {
        $earthRadius = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat))
            * cos(deg2rad($toLat))
            * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
