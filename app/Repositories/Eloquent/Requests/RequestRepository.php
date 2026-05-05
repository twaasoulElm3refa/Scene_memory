<?php

namespace App\Repositories\Eloquent\Requests;

use App\Models\EventRequestCreate;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;

class RequestRepository implements RequestRepositoryInterface
{
    public function paginatedWithEvent(int $perPage)
    {
        return EventRequestCreate::with('events:id,title')->latest()->paginate($perPage);
    }

    public function counts(): array
    {
        return [
            'pending' => EventRequestCreate::where('status', 'pending')->count(),
            'approved' => EventRequestCreate::where('status', 'approved')->count(),
            'rejected' => EventRequestCreate::where('status', 'rejected')->count(),
        ];
    }

    public function find(int $id)
    {
        return EventRequestCreate::select('id', 'event_id', 'status')->find($id);
    }

    public function findOrFail(int $id)
    {
        return EventRequestCreate::findOrFail($id);
    }

    public function findEventRequestByIdOrFail(int $id)
    {
        return EventRequestCreate::findOrFail($id);
    }

    public function createEventRequest(array $data)
    {
        return EventRequestCreate::create($data);
    }
}
