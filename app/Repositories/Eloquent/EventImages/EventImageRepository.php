<?php

namespace App\Repositories\Eloquent\EventImages;

use App\Models\eventsImges;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;

class EventImageRepository implements EventImageRepositoryInterface
{
    public function findById(int $id)
    {
        return eventsImges::find($id);
    }

    public function findOrFail(int $id)
    {
        return eventsImges::findOrFail($id);
    }

    public function findByEventId(int $eventId)
    {
        return eventsImges::where('event_id', $eventId)->get();
    }

    public function findActiveByEventIdPaginated(int $eventId, int $perPage = 10)
    {
        return eventsImges::where('event_id', $eventId)->where('is_active', 0)->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data)
    {
        return eventsImges::create($data);
    }

    public function count(): int
    {
        return eventsImges::count();
    }

    public function whereInIds($ids)
    {
        return eventsImges::whereIn('id', $ids);
    }
}
