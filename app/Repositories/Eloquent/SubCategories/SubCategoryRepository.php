<?php

namespace App\Repositories\Eloquent\SubCategories;

use App\Models\SubCategorey;
use App\Repositories\Contracts\SubCategories\SubCategoryRepositoryInterface;

class SubCategoryRepository implements SubCategoryRepositoryInterface
{
    public function allBasic()
    {
        return SubCategorey::get(['id', 'name']);
    }

    public function paginatedWithEventsCount(int $perPage)
    {
        return SubCategorey::select('id', 'name', 'image')->withCount('events')->paginate($perPage);
    }

    public function findWithEvents(int $id)
    {
        return SubCategorey::with('events')->find($id);
    }

    public function byCategoryWithTranslation(int $categoryId)
    {
        return SubCategorey::with('translation')->where('category_id', $categoryId)->get(['id', 'name']);
    }

    public function create(array $data)
    {
        return SubCategorey::create($data);
    }

    public function findOrFail(int $id)
    {
        return SubCategorey::findOrFail($id);
    }
}
