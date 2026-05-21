<?php

namespace App\Repositories\Eloquent\Newsletters;

use App\Models\ContactResponds;
use App\Models\NewsLetters;
use App\Repositories\Contracts\Newsletters\NewsletterRepositoryInterface;

class NewsletterRepository implements NewsletterRepositoryInterface
{
    public function paginatedWithResponses(int $perPage)
    {
        return NewsLetters::with('contactResponds')->paginate($perPage);
    }

    public function create(array $data)
    {
        return NewsLetters::create($data);
    }

    public function createResponse(array $data)
    {
        return ContactResponds::create($data);
    }
}
