<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\TagRequest;
use App\Repositories\Contracts\Tags\TagRepositoryInterface as TagsRepository;
use App\Services\EventTagCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AdminTagsController extends Controller
{
    use ApiResponse;

    private const CACHE_TTL_HOURS = 24;

    public function __construct(
        private readonly TagsRepository $tagsRepository,
        private readonly EventTagCacheService $tagCache
    ) {}

    /**
     * Display a paginated list of tags.
     */
    public function paginated(Request $request)
    {
        $perPage = max(
            1,
            min((int) $request->query('per_page', 30), 100)
        );

        $page = max(
            1,
            (int) $request->query('page', 1)
        );

        $cacheVersion = $this->tagCache->tagCacheVersion();

        $cacheKey = sprintf(
            'tags:v%s:paginated:per_page:%d:page:%d',
            $cacheVersion,
            $perPage,
            $page
        );

        $tags = Cache::remember(
            $cacheKey,
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $this->tagsRepository->paginated($perPage)
        );

        return $this->success(
            $tags,
            'Tags retrieved successfully'
        );
    }

    /**
     * Display a single tag.
     */
    public function single($id)
    {
        $cacheVersion = $this->tagCache->tagCacheVersion();
        $cacheKey = "tags:v{$cacheVersion}:single:{$id}";

        $tag = Cache::remember(
            $cacheKey,
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $this->tagsRepository->getTagById($id)
        );

        if (! $tag) {
            return $this->error('Tag not found', 404);
        }

        return $this->success(
            $tag,
            'Tag retrieved successfully'
        );
    }

    /**
     * Create a new tag.
     */
    public function create(TagRequest $request)
    {
        try {
            $tag = $this->tagsRepository->createTag(
                $request->validated()
            );

            return $this->success(
                $tag,
                'Tag created successfully'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->error(
                'Unable to create tag',
                500
            );
        }
    }

    /**
     * Update an existing tag.
     */
    public function update(TagRequest $request, $id)
    {
        try {
            $tag = $this->tagsRepository->updateTag(
                $id,
                $request->validated()
            );

            if (! $tag) {
                return $this->error('Tag not found', 404);
            }

            return $this->success(
                $tag,
                'Tag updated successfully'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->error(
                'Unable to update tag',
                500
            );
        }
    }

    /**
     * Delete a tag.
     */
    public function delete($id)
    {
        try {
            $deleted = $this->tagsRepository->deleteTag($id);

            if (! $deleted) {
                return $this->error('Tag not found', 404);
            }

            return $this->success(
                null,
                'Tag deleted successfully'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->error(
                'Unable to delete tag',
                500
            );
        }
    }

    /**
     * Manually invalidate all tags cache.
     */
    public function clearCache()
    {
        $this->tagCache->invalidate();

        return $this->success(
            null,
            'Tags cache cleared successfully'
        );
    }
}
