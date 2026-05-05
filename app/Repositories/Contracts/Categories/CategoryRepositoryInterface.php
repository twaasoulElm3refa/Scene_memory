<?php

namespace App\Repositories\Contracts\Categories;

interface CategoryRepositoryInterface
{
    public function allWithTranslations();
    public function paginatedWithSubCategoriesCount(int $perPage);
    public function findWithSubCategories(int $id);
    public function create(array $data);
    public function find(int $id);
    public function findOrFail(int $id);
}
