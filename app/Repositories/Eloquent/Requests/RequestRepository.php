<?php

namespace App\Repositories\Eloquent\Requests;

use App\Models\EventRequestCreate;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;

class RequestRepository implements RequestRepositoryInterface
{
    public function paginatedWithEvent(int $perPage, ?bool $aiFlagged = null)
    {
        return EventRequestCreate::query()
            ->with('events:id,title')
            ->when($aiFlagged !== null, fn ($query) => $query->where('ai_flagged', $aiFlagged))
            ->latest()
            ->paginate($perPage);
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
        return EventRequestCreate::select([
            'id',
            'event_id',
            'status',
            'ai_flagged',
            'ai_decision',
            'ai_confidence',
            'ai_reason',
            'ai_reviewed_at',
            'ai_review_status',
            'ai_attempts',
            'ai_workflow_execution_id',
        ])->find($id);
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
