<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyRequest;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class CommentReplyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {
    }

    public function create(ReplyRequest $request)
    {
        $comment = $this->commentRepository->findOrFail((int) request('id'));
        $event = $this->eventRepository->findByIdOrFail((int) $comment->event_id);

        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['comment_id'] = $comment->id;

        $reply = $this->commentRepository->createReply($data);

        $this->clearCache($event->slug);

        return $this->success($reply, 'Reply Created Successfully');
    }

    private function clearCache($slug = '')
    {
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        foreach ($locales as $locale) {
            Cache::tags(['events'])->forget("events_single_{$slug}_".$locale);
        }

        Cache::tags(['comments'])->flush();
    }
}
