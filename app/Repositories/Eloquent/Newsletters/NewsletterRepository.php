<?php

namespace App\Repositories\Eloquent\Newsletters;

use App\Models\contactResponds;
use App\Models\newsletters;
use App\Repositories\Contracts\Newsletters\NewsletterRepositoryInterface;

class NewsletterRepository implements NewsletterRepositoryInterface
{
    public function paginatedWithResponses(int $perPage)
    {
        return newsletters::with('contactResponds')->paginate($perPage);
    }

    public function create(array $data)
    {
        return newsletters::create($data);
    }

    public function createResponse(array $data)
    {
        return contactResponds::create($data);
    }
}
