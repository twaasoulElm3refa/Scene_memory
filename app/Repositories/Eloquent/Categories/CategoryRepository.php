<?php

namespace App\Repositories\Eloquent\Categories;

use App\Models\Categories;
use App\Repositories\Contracts\Categories\CategoryRepositoryInterface;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function allWithTranslations()
    {
        return Categories::with('translation')->orderBy('created_at', 'desc')->get();
    }

    public function paginatedWithSubCategoriesCount(int $perPage)
    {
        return Categories::query()->latest()->select('id', 'name', 'image', 'created_at')->withCount('subCategories')->paginate($perPage);
    }

    public function findWithSubCategories(int $id)
    {
        return Categories::with('subCategories.translation')->find($id);
    }

    public function create(array $data)
    {
        return Categories::create($data);
    }

    public function find(int $id)
    {
        return Categories::find($id);
    }

    public function findOrFail(int $id)
    {
        return Categories::findOrFail($id);
    }
}
