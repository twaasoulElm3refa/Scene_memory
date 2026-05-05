<?php

namespace App\Repositories\Eloquent\Contacts;

use App\Models\contactResponds;
use App\Models\contacts;
use App\Repositories\Contracts\Contacts\ContactRepositoryInterface;

class ContactRepository implements ContactRepositoryInterface
{
    public function paginatedWithResponses(int $perPage)
    {
        return contacts::with('contactResponds')->latest()->paginate($perPage);
    }

    public function contactsStats()
    {
        return contacts::query()
            ->select('id', 'created_at')
            ->withCount('contactResponds')
            ->with(['contactResponds' => function ($q) {
                $q->select('contact_id', 'created_at')->orderBy('created_at')->limit(1);
            }])
            ->get();
    }

    public function findWithResponses(int $id)
    {
        return contacts::with('contactResponds')->find($id);
    }

    public function create(array $data)
    {
        return contacts::create($data);
    }

    public function createResponse(array $data)
    {
        return contactResponds::create($data);
    }

    public function findOrFail(int $id)
    {
        return contacts::findOrFail($id);
    }
}
