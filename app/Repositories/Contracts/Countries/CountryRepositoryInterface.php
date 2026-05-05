<?php

namespace App\Repositories\Contracts\Countries;

interface CountryRepositoryInterface
{
    public function allWithTranslation();
    public function allForGate();
    public function allBasic();
    public function paginatedWithTranslation(int $perPage);
    public function findWithCitiesTranslation(int $id);
    public function findOrFail(int $id);
    public function count(): int;
    public function create(array $data);
    public function findByCode(string $code);
}
