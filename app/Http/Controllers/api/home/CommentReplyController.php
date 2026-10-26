<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyRequest;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Support\CommentCache;
use App\Support\SecurityMessage;

class CommentReplyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {}

    public function create(ReplyRequest $request)
    {
        $comment = $this->commentRepository->findOrFail((int) request('id'));
        $event = $this->eventRepository->findByIdOrFail((int) $comment->event_id);
        abort_unless($event->is_active, 404, SecurityMessage::get('notFound'));

        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['comment_id'] = $comment->id;

        $reply = $this->commentRepository->createReply($data);

        CommentCache::forgetEvent((int) $event->id, $event->slug);

        return $this->success($reply, 'Reply Created Successfully');
    }
}
