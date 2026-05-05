<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommentInteractionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {
    }

    public function support()
    {
        $comment = $this->commentRepository->findOrFail((int) request('id'));
        $event = $this->eventRepository->findByIdOrFail((int) $comment->event_id);

        $interaction = $this->commentRepository->firstOrCreateInteraction([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'type' => 'support',
        ]);

        foreach (['ar', 'en', 'fr', 'es', 'ja', 'zh', 'fa', 'ur', 'ru', 'it', 'de', 'hi'] as $locale) {
            $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
            Cache::tags(['events'])->forget($key);
        }
        Cache::tags(['comments'])->flush();

        return $this->success($interaction, 'Interaction Created Successfully');
    }

    public function exhibitions()
    {
        $comment = $this->commentRepository->findOrFail((int) request('id'));
        $event = $this->eventRepository->findByIdOrFail((int) $comment->event_id);

        $interaction = $this->commentRepository->firstOrCreateInteraction([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'type' => 'Exhibitions',
        ]);

        foreach (['ar', 'en', 'fr'] as $locale) {
            $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
            Cache::tags(['events'])->forget($key);
        }
        Cache::tags(['comments'])->flush();

        return $this->success($interaction, 'Interaction Created Successfully');
    }

    public function neutral()
    {
        $comment = $this->commentRepository->findOrFail((int) request('id'));
        $event = $this->eventRepository->findByIdOrFail((int) $comment->event_id);

        $interaction = $this->commentRepository->firstOrCreateInteraction([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'type' => 'neutral',
        ]);

        foreach (['ar', 'en', 'fr'] as $locale) {
            $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
            Cache::tags(['events'])->forget($key);
        }
        Cache::tags(['comments'])->flush();

        return $this->success($interaction, 'Interaction Created Successfully');
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
