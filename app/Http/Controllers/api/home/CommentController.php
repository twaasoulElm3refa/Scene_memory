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

class CommentController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {
    }

    public function create(CommentRequest $request)
    {
        $data = $request->validated();
        try {
            $data['event_id'] = request('id');
            if (auth()->check()) {
                $data['user_id'] = auth()->user()->id;
            }

            $comment = $this->commentRepository->create($data);
            $event = $this->eventRepository->findById((int) $data['event_id']);

            if ($event) {
                foreach (['ar', 'en', 'fr'] as $locale) {
                    $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
                    Cache::tags(['events'])->forget($key);
                }
            }

            TranslateCommentJob::dispatch($comment->id, $data['comment']);

            return $this->success($comment, 'Comment Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function destroy(Request $request)
    {
        try {
            $comment = $this->commentRepository->findOrFail((int) request('id'));

            if (auth()->user()->role == 'admin') {
                $comment->delete();
                Cache::tags(['events'])->flush();
                return $this->success([], 'Comment deleted successfully');
            }

            if ($request->user()->id != $comment->user_id) {
                return $this->unauthorized('You are not the owner of this comment');
            }

            $comment->delete();

            return $this->success([], 'Comment deleted successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function allPaginated()
    {
        try {
            $event = $this->eventRepository->findBySlugOrFail((string) request('slug'));
            $page = request('page', 1);
            $cacheKey = "comments_page_{$page}_event_{$event->id}_".app()->getLocale();

            $comments = Cache::tags(['comments'])->remember($cacheKey, $this->cacheTime, function () use ($event) {
                return $this->commentRepository->paginatedByEventId((int) $event->id, 5);
            });

            return $this->success($comments, 'All comments');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
