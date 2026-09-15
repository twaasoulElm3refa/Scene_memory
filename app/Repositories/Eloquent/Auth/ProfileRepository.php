<?php

namespace App\Repositories\Eloquent\Auth;

use App\Http\Resources\ProfileTimeline\TimelineCommentImageResource;
use App\Http\Resources\ProfileTimeline\TimelineCommentInteractionResource;
use App\Http\Resources\ProfileTimeline\TimelineCommentResource;
use App\Http\Resources\ProfileTimeline\TimelineEventResource;
use App\Http\Resources\ProfileTimeline\TimelineLikeResource;
use App\Http\Resources\ProfileTimeline\TimelineReplyResource;
use App\Http\Resources\ProfileTimeline\TimelineWishlistResource;
use App\Models\CommentImage;
use App\Models\CommentInteractions;
use App\Models\CommentReplies;
use App\Models\Comments;
use App\Models\Events;
use App\Models\Likes;
use App\Models\Wishlist;
use App\Repositories\Contracts\Auth\ProfileRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class ProfileRepository implements ProfileRepositoryInterface
{
    private const PREVIEW_LIMIT = 4;

    private const RESOURCE_MAP = [
        'events' => TimelineEventResource::class,
        'likes' => TimelineLikeResource::class,
        'comments' => TimelineCommentResource::class,
        'comment_images' => TimelineCommentImageResource::class,
        'replies' => TimelineReplyResource::class,
        'comment_interactions' => TimelineCommentInteractionResource::class,
        'wishlists' => TimelineWishlistResource::class,
    ];

    public function getProfileActivity(int $userId, array $filters = []): array
    {
        $section = $filters['section'] ?? 'all';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 12)));
        [$from, $to] = $this->dateRange($filters);
        $queries = $this->queries($userId);

        $response = [
            'filters' => [
                'section' => $section,
                'period' => $filters['period'] ?? 'all',
                'from' => $from?->toISOString(),
                'to' => $to?->toISOString(),
                'timezone' => $filters['timezone'] ?? config('app.timezone', 'UTC'),
            ],
        ];

        if ($section !== 'all') {
            $payload = $this->sectionPayload(
                $this->applyDateRange($queries[$section](), $from, $to),
                self::RESOURCE_MAP[$section],
                false,
                $page,
                $perPage
            );

            $response['summary'] = [
                'total' => $payload['total'],
                'latest_activity_at' => $payload['latest_activity_at'],
                'counts' => [$section => $payload['total']],
            ];
            $response[$section] = $payload;

            return $response;
        }

        $counts = [];
        $latestDates = [];

        foreach (self::RESOURCE_MAP as $name => $resource) {
            $payload = $this->sectionPayload(
                $this->applyDateRange($queries[$name](), $from, $to),
                $resource,
                true,
                1,
                self::PREVIEW_LIMIT
            );

            $response[$name] = $payload;
            $counts[$name] = $payload['total'];
            $latestDates[] = $payload['latest_activity_at'];
        }

        $response['summary'] = [
            'total' => array_sum($counts),
            'latest_activity_at' => collect($latestDates)->filter()->max(),
            'counts' => $counts,
        ];

        return $response;
    }

    /** @return array<string, callable(): Builder> */
    private function queries(int $userId): array
    {
        return [
            'events' => fn () => Events::query()
                ->where('user_id', $userId)
                ->with($this->fullEventRelations()),
            'likes' => fn () => Likes::query()
                ->where('user_id', $userId)
                ->with(['event' => fn ($query) => $query->with($this->eventSummaryRelations())]),
            'comments' => fn () => Comments::query()
                ->where('user_id', $userId)
                ->with([
                    'images' => fn ($query) => $query->orderBy('sort_order'),
                    'event' => fn ($query) => $query->with($this->eventSummaryRelations()),
                ]),
            'comment_images' => fn () => CommentImage::query()
                ->whereHas('comment', fn ($query) => $query->where('user_id', $userId))
                ->with([
                    'comment' => fn ($query) => $query->with([
                        'event' => fn ($eventQuery) => $eventQuery->with($this->eventSummaryRelations()),
                    ]),
                ]),
            'replies' => fn () => CommentReplies::query()
                ->where('user_id', $userId)
                ->with([
                    'commentRelation' => fn ($query) => $query->with([
                        'event' => fn ($eventQuery) => $eventQuery->with($this->eventSummaryRelations()),
                    ]),
                ]),
            'comment_interactions' => fn () => CommentInteractions::query()
                ->where('user_id', $userId)
                ->with([
                    'comment' => fn ($query) => $query->with([
                        'event' => fn ($eventQuery) => $eventQuery->with($this->eventSummaryRelations()),
                    ]),
                ]),
            'wishlists' => fn () => Wishlist::query()
                ->where('user_id', $userId)
                ->with(['event' => fn ($query) => $query->with($this->eventSummaryRelations())]),
        ];
    }

    private function fullEventRelations(): array
    {
        return [
            ...$this->eventSummaryRelations(),
            'sub_categorey:id,category_id,name',
            'sub_categorey.translation:id,category_id,name,locale',
            'sub_categorey.category:id,name',
            'sub_categorey.category.translation:id,category_id,name,locale',
            'city:id,country_id,name',
            'city.translation:id,city_id,name,locale',
            'city.countries:id,name,code',
            'city.countries.translation:id,country_id,name,locale',
        ];
    }

    private function eventSummaryRelations(): array
    {
        return [
            'translation:id,event_id,title,description,locale',
            'coverImage' => fn ($query) => $query->select([
                'events_images.id',
                'events_images.event_id',
                'events_images.preview_url',
                'events_images.full_url',
                'events_images.type',
                'events_images.is_active',
            ]),
            'requests:id,event_id,status',
        ];
    }

    /** @param class-string<JsonResource> $resource */
    private function sectionPayload(
        Builder $query,
        string $resource,
        bool $preview,
        int $page,
        int $perPage
    ): array {
        $model = $query->getModel();
        $createdAt = $model->qualifyColumn($model->getCreatedAtColumn());
        $id = $model->qualifyColumn($model->getKeyName());
        $total = (clone $query)->count();
        $latest = (clone $query)->max($createdAt);
        $ordered = $query->orderByDesc($createdAt)->orderByDesc($id);

        if ($preview) {
            $items = $ordered->limit($perPage)->get();

            return [
                'data' => $this->resolveResources($items, $resource),
                'total' => $total,
                'latest_activity_at' => $this->isoDate($latest),
                'has_more' => $total > $perPage,
            ];
        }

        $paginator = $ordered->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $this->resolveResources($paginator->getCollection(), $resource),
            'total' => $paginator->total(),
            'latest_activity_at' => $this->isoDate($latest),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    private function resolveResources(iterable $items, string $resource): array
    {
        return collect($items)
            ->map(fn ($item) => (new $resource($item))->resolve(request()))
            ->values()
            ->all();
    }

    private function applyDateRange(
        Builder $query,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to
    ): Builder {
        if (! $from || ! $to) {
            return $query;
        }

        return $query->whereBetween(
            $query->getModel()->qualifyColumn($query->getModel()->getCreatedAtColumn()),
            [$from, $to]
        );
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    private function dateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'all';

        if ($period === 'all') {
            return [null, null];
        }

        $timezone = $filters['timezone'] ?? config('app.timezone', 'UTC');
        $databaseTimezone = config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($timezone);

        [$from, $to] = match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'this_week' => [$now->startOfWeek(), $now->endOfWeek()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'custom' => [
                CarbonImmutable::parse($filters['from'], $timezone)->startOfDay(),
                CarbonImmutable::parse($filters['to'], $timezone)->endOfDay(),
            ],
            default => [null, null],
        };

        return [
            $from?->setTimezone($databaseTimezone),
            $to?->setTimezone($databaseTimezone),
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->toISOString() : null;
    }

    public function clearUserProfileCache(int $userId): void
    {
        Cache::tags(['profile', "user:$userId"])->flush();
    }
}
