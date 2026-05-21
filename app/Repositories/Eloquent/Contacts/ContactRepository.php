<?php

namespace App\Repositories\Eloquent\Contacts;

use App\Models\ContactResponds;
use App\Models\Contacts;
use App\Repositories\Contracts\Contacts\ContactRepositoryInterface;

class ContactRepository implements ContactRepositoryInterface
{
    public function paginatedWithResponses(int $perPage)
    {
        return Contacts::with('contactResponds')->latest()->paginate($perPage);
    }

    public function contactsStats()
    {
        return Contacts::query()
            ->select('id', 'created_at')
            ->withCount('contactResponds')
            ->with(['contactResponds' => function ($q) {
                $q->select('contact_id', 'created_at')->orderBy('created_at')->limit(1);
            }])
            ->get();
    }

    public function findWithResponses(int $id)
    {
        return Contacts::with('contactResponds')->find($id);
    }

    public function create(array $data)
    {
        return Contacts::create($data);
    }

    public function createResponse(array $data)
    {
        return ContactResponds::create($data);
    }

    public function findOrFail(int $id)
    {
        return Contacts::findOrFail($id);
    }
}
