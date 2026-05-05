<?php

namespace App\Repositories\Contracts\Requests;

interface RequestRepositoryInterface
{
    public function paginatedWithEvent(int $perPage);
    public function counts(): array;
    public function find(int $id);
    public function findOrFail(int $id);
    public function findEventRequestByIdOrFail(int $id);
    public function createEventRequest(array $data);
}
