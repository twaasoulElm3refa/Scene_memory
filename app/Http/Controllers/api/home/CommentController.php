<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommentRequest;
use App\Jobs\TranslateCommentJob;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CommentController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    private const EVENT_CACHE_LOCALES = [
        'ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr',
    ];

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {
    }

    public function create(CommentRequest $request)
    {
        $data = $request->validated();
        $storedPaths = [];
        $transactionStarted = false;

        try {
            $eventId = (int) $request->route('id');
            $commentData = [
                'comment' => $data['comment'],
                'event_id' => $eventId,
                'user_id' => $request->user()->id,
            ];

            DB::beginTransaction();
            $transactionStarted = true;

            $comment = $this->commentRepository->create($commentData);

            foreach ($request->file('images', []) as $sortOrder => $image) {
                $disk = 'public';
                $path = $image->store("comments/{$comment->id}", $disk);

                if (! $path) {
                    throw new RuntimeException('Unable to store the comment image.');
                }

                $storedPaths[] = ['disk' => $disk, 'path' => $path];

                $comment->images()->create([
                    'path' => $path,
                    'disk' => $disk,
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $image->getMimeType(),
                    'size' => $image->getSize() ?: null,
                    'sort_order' => $sortOrder,
                ]);
            }

            DB::commit();
            $transactionStarted = false;

            $event = $this->eventRepository->findById($eventId);
            $this->clearCommentCaches($event?->slug);

            TranslateCommentJob::dispatch($comment->id, $commentData['comment']);

            $comment->load([
                'images',
                'translation:id,comment_id,locale,comment,created_at',
                'user:id,name',
            ])->loadCount([
                'interactions as support_count' => fn ($query) => $query->where('type', 'support'),
                'interactions as exhibitions_count' => fn ($query) => $query->where('type', 'Exhibitions'),
                'interactions as neutral_count' => fn ($query) => $query->where('type', 'neutral'),
            ]);

            return $this->success($comment, 'Comment Created Successfully');
        } catch (Throwable $th) {
            if ($transactionStarted) {
                DB::rollBack();

                foreach ($storedPaths as $storedPath) {
                    Storage::disk($storedPath['disk'])->delete($storedPath['path']);
                }
            }

            return $this->error($th->getMessage());
        }
    }

    public function destroy(Request $request)
    {
        try {
            $comment = $this->commentRepository->findOrFail((int) request('id'));
            $user = $request->user();

            if ($user->role !== 'admin' && $user->id !== $comment->user_id) {
                return $this->unauthorized('You are not the owner of this comment');
            }

            $eventSlug = $comment->event()->value('slug');
            $images = $comment->images()->get(['disk', 'path']);
            $comment->delete();

            foreach ($images as $image) {
                if (! Storage::disk($image->disk)->delete($image->path)) {
                    Log::warning('Unable to delete a comment image from storage.', [
                        'comment_id' => $comment->id,
                        'disk' => $image->disk,
                        'path' => $image->path,
                    ]);
                }
            }

            $this->clearCommentCaches($eventSlug);

            return $this->success([], 'Comment deleted successfully');
        } catch (Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function allPaginated(Request $request)
    {
        try {
            $event = $this->eventRepository->findBySlugOrFail((string) request('slug'));
            $page = request('page', 1);
            $cacheKey = "comments_page_{$page}_event_{$event->id}_".app()->getLocale();

            $comments = Cache::tags(['comments'])->remember($cacheKey, $this->cacheTime, function () use ($event) {
                return $this->commentRepository->paginatedByEventId((int) $event->id, 5);
            });
            $comments = clone $comments;
            $comments->setCollection(
                $comments->getCollection()->map(fn ($comment) => clone $comment)
            );
            $this->commentRepository->attachCurrentUserReactions(
                $comments->getCollection(),
                $request->user('sanctum')?->id
            );

            return $this->success($comments, 'All comments');
        } catch (Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    private function clearCommentCaches(?string $eventSlug): void
    {
        if ($eventSlug) {
            foreach (self::EVENT_CACHE_LOCALES as $locale) {
                $key = 'event_'.strtolower(trim($eventSlug)).'_'.$locale;
                Cache::tags(['events'])->forget($key);
            }
        }

        Cache::tags(['comments'])->flush();
    }
}
