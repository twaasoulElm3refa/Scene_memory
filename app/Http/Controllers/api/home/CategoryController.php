<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Requests\categoreyRequest;
use App\Models\Categories;
use App\Models\subCategorey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 600; 

    public function index(): JsonResponse
    {
        $cacheKey = 'categories:index:all';

        $categories = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Categories::get(['id', 'name']);
        });

        if ($categories->isEmpty()) {
            return $this->error('No categories found', 404);
        }

        return $this->success($categories, 'All categories');
    }

    public function paginated(): JsonResponse
    {
        $page    = request()->input('page', 1);
        $perPage = 4; 
        $version = Cache::get('categories:version', 1);

        $cacheKey = "categories:paginated:v{$version}:p{$page}:pp{$perPage}";

        $categories = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return Categories::query()
                ->select('id', 'name', 'image', 'created_at')
                ->withCount('subCategories')
                ->paginate($perPage);
        });

        if ($categories->isEmpty()) {
            return $this->error('No categories found on this page', 404);
        }

        return $this->success($categories, 'Paginated categories');
    }

    public function single($id): JsonResponse
    {
        $cacheKey = "categories:single:{$id}";

        $category = Cache::remember($cacheKey, $this->cacheTime, function () use ($id) {
            return Categories::with('subCategories')->find($id);
        });

        if (! $category) {
            return $this->error('Category not found', 404);
        }

        return $this->success($category, 'Category details');
    }

    public function sub_categories($id): JsonResponse
    {
        $cacheKey = "categories:sub:{$id}";

        $subCategories = Cache::remember($cacheKey, $this->cacheTime, function () use ($id) {
            return subCategorey::where('category_id', $id)
                ->get(['id', 'name']);
        });

        if ($subCategories->isEmpty()) {
            return $this->error('No sub-categories found', 404);
        }

        return $this->success($subCategories, 'Sub categories');
    }

    public function create(categoreyRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $data['slug'] = str_replace(' ', '-', strtolower($data['name'])) . '-' . time();

        $category = Categories::create($data);

        $this->clearAllCategoriesCache();

        return $this->success($category, 'Category created successfully');
    }

    public function update(categoreyRequest $request, $id): JsonResponse
    {
        $category = Categories::find($id);

        if (! $category) {
            return $this->error('Category not found', 404);
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $data['slug'] = str_replace(' ', '-', strtolower($data['name'])) . '-' . time();

        $category->update($data);

        $this->clearAllCategoriesCache();

        return $this->success($category, 'Category updated successfully');
    }

    public function delete($id): JsonResponse
    {
        $category = Categories::find($id);
        if (! $category) {
            return $this->error('Category not found', 404);
        }
        $category->delete();
        $this->clearAllCategoriesCache();
        return $this->success(null, 'Category deleted successfully');
    }

    /**
     * بيتم استدعاؤه بعد كل عملية تغيير (create/update/delete)
     */
    protected function clearAllCategoriesCache(): void
    {
        Cache::increment('categories:version', 1);
        Cache::forget('categories:index:all');
    }
}