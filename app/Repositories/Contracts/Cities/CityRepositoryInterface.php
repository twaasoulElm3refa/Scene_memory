<?php

namespace App\Repositories\Contracts\Cities;

interface CityRepositoryInterface
{
    public function paginatedWithTranslation(int $perPage);
    public function paginatedWithRelations(int $perPage);
    public function findWithEventsAndTranslation(int $id);
    public function findOrFail(int $id);
    public function create(array $data);
    public function count(): int;
    public function byCountryId(int $countryId);
    public function firstByNameLike(string $cityName);
}
