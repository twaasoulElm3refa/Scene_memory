<?php

namespace App\Repositories\Eloquent\SubCategories;

use App\Models\subCategorey;
use App\Repositories\Contracts\SubCategories\SubCategoryRepositoryInterface;

class SubCategoryRepository implements SubCategoryRepositoryInterface
{
    public function allBasic()
    {
        return subCategorey::get(['id', 'name']);
    }

    public function paginatedWithEventsCount(int $perPage)
    {
        return subCategorey::select('id', 'name', 'image')->withCount('events')->paginate($perPage);
    }

    public function findWithEvents(int $id)
    {
        return subCategorey::with('events')->find($id);
    }

    public function byCategoryWithTranslation(int $categoryId)
    {
        return subCategorey::with('translation')->where('category_id', $categoryId)->get(['id', 'name']);
    }

    public function create(array $data)
    {
        return subCategorey::create($data);
    }

    public function findOrFail(int $id)
    {
        return subCategorey::findOrFail($id);
    }
}
