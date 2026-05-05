<?php

namespace App\Repositories\Contracts\EventImages;

interface EventImageRepositoryInterface
{
    public function findById(int $id);
    public function findOrFail(int $id);
    public function findByEventId(int $eventId);
    public function findActiveByEventIdPaginated(int $eventId, int $perPage = 10);
    public function create(array $data);
    public function count(): int;
    public function whereInIds($ids);
}
