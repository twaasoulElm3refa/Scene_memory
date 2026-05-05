<?php

namespace App\Repositories\Contracts\Comments;

interface CommentRepositoryInterface
{
    public function create(array $data);
    public function findOrFail(int $id);
    public function paginatedByEventId(int $eventId, int $perPage = 5);
    public function createReply(array $data);
    public function firstOrCreateInteraction(array $data);
    public function firstOrCreateReport(array $data);
    public function reportsPaginated(int $perPage = 5);
    public function findReportOrFail(int $id);
}
