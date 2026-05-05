<?php

namespace App\Repositories\Contracts\SubCategories;

interface SubCategoryRepositoryInterface
{
    public function allBasic();
    public function paginatedWithEventsCount(int $perPage);
    public function findWithEvents(int $id);
    public function byCategoryWithTranslation(int $categoryId);
    public function create(array $data);
    public function findOrFail(int $id);
}
