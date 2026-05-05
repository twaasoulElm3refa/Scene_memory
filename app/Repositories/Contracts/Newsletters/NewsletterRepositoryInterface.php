<?php

namespace App\Repositories\Contracts\Newsletters;

interface NewsletterRepositoryInterface
{
    public function paginatedWithResponses(int $perPage);
    public function create(array $data);
    public function createResponse(array $data);
}
