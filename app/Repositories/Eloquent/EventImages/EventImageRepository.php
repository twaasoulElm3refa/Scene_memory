<?php

namespace App\Repositories\Eloquent\EventImages;

use App\Models\EventsImges;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;

class EventImageRepository implements EventImageRepositoryInterface
{
    public function findById(int $id)
    {
        return EventsImges::find($id);
    }

    public function findOrFail(int $id)
    {
        return EventsImges::findOrFail($id);
    }

    public function findByEventId(int $eventId)
    {
        return EventsImges::where('event_id', $eventId)->get();
    }

    public function findActiveByEventIdPaginated(int $eventId, int $perPage = 10)
    {
        return EventsImges::where('event_id', $eventId)->where('is_active', 0)->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data)
    {
        return EventsImges::create($data);
    }

    public function count(): int
    {
        return EventsImges::count();
    }

    public function whereInIds($ids)
    {
        return EventsImges::whereIn('id', $ids);
    }
}
