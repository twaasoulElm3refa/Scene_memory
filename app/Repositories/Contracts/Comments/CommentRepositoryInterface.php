<?php

namespace App\Repositories\Contracts\Comments;

interface CommentRepositoryInterface
{
    public function create(array $data);

    public function findOrFail(int $id);

    public function paginatedByEventId(int $eventId, int $perPage = 5);

    public function createReply(array $data);

    public function updateOrCreateInteraction(array $identity, array $values);

    public function reactionSummary(int $commentId, int $userId): array;

    public function attachCurrentUserReactions(iterable $comments, ?int $userId): void;

    public function firstOrCreateReport(array $data);

    public function reportsPaginated(int $perPage = 5);

    public function findReportOrFail(int $id);
}
