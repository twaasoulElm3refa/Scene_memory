<?php

namespace App\Repositories\Contracts\Contacts;

interface ContactRepositoryInterface
{
    public function paginatedWithResponses(int $perPage);
    public function contactsStats();
    public function findWithResponses(int $id);
    public function create(array $data);
    public function createResponse(array $data);
    public function findOrFail(int $id);
}
