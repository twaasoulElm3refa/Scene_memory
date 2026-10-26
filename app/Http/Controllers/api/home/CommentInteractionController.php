<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Support\CommentCache;
use App\Support\SecurityMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommentInteractionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {}

    public function support(Request $request)
    {
        return $this->react($request, 'support');
    }

    public function exhibitions(Request $request)
    {
        return $this->react($request, 'Exhibitions');
    }

    public function neutral(Request $request)
    {
        return $this->react($request, 'neutral');
    }

    private function react(Request $request, string $type)
    {
        $comment = $this->commentRepository->findOrFail((int) request('id'));
        $event = $this->eventRepository->findByIdOrFail((int) $comment->event_id);
        abort_unless($event->is_active, 404, SecurityMessage::get('notFound'));
        $userId = (int) $request->user()->id;

        $interaction = $this->commentRepository->updateOrCreateInteraction(
            [
                'comment_id' => $comment->id,
                'user_id' => $userId,
            ],
            ['type' => $type]
        );

        CommentCache::forgetEvent((int) $event->id, $event->slug);

        return $this->success([
            'interaction' => $interaction,
            ...$this->commentRepository->reactionSummary($comment->id, $userId),
        ], 'Interaction Saved Successfully');
    }

    public function report(Request $request)
    {
        $comment = $this->commentRepository->findOrFail((int) request('id'));

        $interaction = $this->commentRepository->firstOrCreateReport([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'reason' => $request->reason,
        ]);

        Cache::tags(['reports'])->flush();

        return $this->success($interaction, 'Report Created Successfully');
    }
}
