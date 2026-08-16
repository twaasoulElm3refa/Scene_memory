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

    public function firstOrCreateInteraction(array $data)
    {
        return CommentInteractions::firstOrCreate($data);
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
}
