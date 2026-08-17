<?php

namespace App\Repositories\Eloquent\Comments;

use App\Models\CommentInteractions;
use App\Models\CommentReplies;
use App\Models\CommentReport;
use App\Models\Comments;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;

class CommentRepository implements CommentRepositoryInterface
{
    public function create(array $data)
    {
        return Comments::create($data);
    }

    public function findOrFail(int $id)
    {
        return Comments::findOrFail($id);
    }

    public function paginatedByEventId(int $eventId, int $perPage = 5)
    {
        return Comments::with([
            'translation:id,comment_id,locale,comment,created_at',
            'user:id,name',
            'images',
        ])
            ->withCount([
                'interactions as support_count' => fn ($q) => $q->where('type', 'support'),
                'interactions as exhibitions_count' => fn ($q) => $q->where('type', 'Exhibitions'),
                'interactions as neutral_count' => fn ($q) => $q->where('type', 'neutral'),
            ])
            ->where('event_id', $eventId)
            ->latest()
            ->paginate($perPage);
    }

    public function createReply(array $data)
    {
        return CommentReplies::create($data);
    }

    public function updateOrCreateInteraction(array $identity, array $values)
    {
        return CommentInteractions::withTrashed()->updateOrCreate(
            $identity,
            [...$values, 'deleted_at' => null]
        );
    }

    public function reactionSummary(int $commentId, int $userId): array
    {
        $counts = CommentInteractions::query()
            ->where('comment_id', $commentId)
            ->selectRaw("SUM(CASE WHEN type = 'support' THEN 1 ELSE 0 END) as support_count")
            ->selectRaw("SUM(CASE WHEN type = 'neutral' THEN 1 ELSE 0 END) as neutral_count")
            ->selectRaw("SUM(CASE WHEN type = 'Exhibitions' THEN 1 ELSE 0 END) as exhibitions_count")
            ->first();

        $currentReaction = CommentInteractions::query()
            ->where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->value('type');

        return [
            'current_user_reaction' => $this->normalizeReactionType($currentReaction),
            'support_count' => (int) ($counts?->support_count ?? 0),
            'neutral_count' => (int) ($counts?->neutral_count ?? 0),
            'exhibitions_count' => (int) ($counts?->exhibitions_count ?? 0),
        ];
    }

    public function attachCurrentUserReactions(iterable $comments, ?int $userId): void
    {
        $commentCollection = collect($comments);
        $commentIds = $commentCollection
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $userReactions = $userId && $commentIds->isNotEmpty()
            ? CommentInteractions::query()
                ->where('user_id', $userId)
                ->whereIn('comment_id', $commentIds)
                ->pluck('type', 'comment_id')
            : collect();

        $commentCollection->each(function ($comment) use ($userReactions): void {
            $comment->setAttribute(
                'current_user_reaction',
                $this->normalizeReactionType($userReactions->get($comment->id))
            );
        });
    }

    public function firstOrCreateReport(array $data)
    {
        return CommentReport::firstOrCreate($data);
    }

    public function reportsPaginated(int $perPage = 5)
    {
        return CommentReport::with('user', 'comment')->paginate($perPage);
    }

    public function findReportOrFail(int $id)
    {
        return CommentReport::findOrFail($id);
    }

    private function normalizeReactionType(?string $type): ?string
    {
        return $type === 'Exhibitions' ? 'exhibitions' : $type;
    }
}
