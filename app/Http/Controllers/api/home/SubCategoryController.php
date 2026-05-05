<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Repositories\Contracts\SubCategories\SubCategoryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SubCategoryController extends Controller
{
     use ApiResponse;

    protected $cacheTime = 3600;

    public function __construct(private readonly SubCategoryRepositoryInterface $subCategoryRepository)
    {
    }

    public function index()
    {
        $cacheKey = 'categories_index';
        $categories = Cache::remember($cacheKey, $this->cacheTime, function () {
            return $this->subCategoryRepository->allBasic();
        });
        if ($categories->isEmpty()) {
            return $this->error('No More categories', 404);
        }

        return $this->success($categories, 'All categories');
    }

    public function paginated()
    {
        $page = request()->get('page', 1);
        $perPage = 4;

        $version = Cache::get('categories_cache_version', 1);

        $cacheKey = "categories_v{$version}_page_{$page}_per_{$perPage}";

        $categories = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return $this->subCategoryRepository->paginatedWithEventsCount($perPage);
        });

        if ($categories->isEmpty()) {
            return $this->error('No More categories', 404);
        }

        return $this->success($categories, 'All categories paginated');
    }

    public function single()
    {
        $categoreyId = request('id');
        $cacheKey = "categorey_single_{$categoreyId}";
        $categorey = Cache::remember($cacheKey, $this->cacheTime, function () {
            return $this->subCategoryRepository->findWithEvents((int) request('id'));
        });
        if (! $categorey) {
            return $this->error('No More categories', 404);
        }

        return $this->success($categorey, 'category');
    }

    public function create(categoreyRequest $request)
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }
        $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();
        $categorey = $this->subCategoryRepository->create($data);
        Cache::increment('categories_cache_version');
        return $this->success($categorey, 'category Created Successfully');
    }

    public function update(categoreyRequest $request, $id)
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }
        $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();
        $categorey = $this->subCategoryRepository->findOrFail((int) $id);
        $categorey->update($data);
        Cache::increment('categories_cache_version');
        return $this->success($categorey, 'category Updated Successfully');
    }

    public function delete($id)
    {
        $categorey = $this->subCategoryRepository->findOrFail((int) $id);
        $categorey->delete();
        Cache::increment('categories_cache_version');
        return $this->success($categorey, 'category Deleted Successfully');
    }
}
