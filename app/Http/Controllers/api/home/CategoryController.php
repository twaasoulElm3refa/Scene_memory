<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Repositories\Contracts\Categories\CategoryRepositoryInterface;
use App\Repositories\Contracts\SubCategories\SubCategoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 600;

    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SubCategoryRepositoryInterface $subCategoryRepository
    ) {
    }

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $version = Cache::get('categories:version', 1);
        $cacheKey = "categories:index:all:{$locale}:v{$version}";

        $categories = Cache::tags(['categories'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->categoryRepository->allWithTranslations();
        });

        if ($categories->isEmpty()) {
            return $this->error('No categories found', 404);
        }

        return $this->success($categories, 'All categories');
    }

    public function paginated(): JsonResponse
    {
        $page = request()->input('page', 1);
        $perPage = 4;
        $version = Cache::get('categories:version', 1);
        $cacheKey = "categories:paginated:v{$version}:p{$page}:pp{$perPage}";

        $categories = Cache::tags(['categories'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return $this->categoryRepository->paginatedWithSubCategoriesCount($perPage);
        });

        if ($categories->isEmpty()) {
            return $this->error('No categories found on this page', 404);
        }

        return $this->success($categories, 'Paginated categories');
    }

    public function single($id): JsonResponse
    {
        $cacheKey = "categories:single:{$id}";
        $category = Cache::tags(['categories'])->remember($cacheKey, $this->cacheTime, function () use ($id) {
            return $this->categoryRepository->findWithSubCategories((int) $id);
        });

        if (! $category) {
            return $this->error('Category not found', 404);
        }

        return $this->success($category, 'Category details');
    }

    public function sub_categories($id): JsonResponse
    {
        $locale = app()->getLocale();
        $cacheKey = "categories:sub:{$id}_{$locale}";

        $subCategories = Cache::tags(['categories', 'subCategories'])->remember($cacheKey, $this->cacheTime, function () use ($id) {
            return $this->subCategoryRepository->byCategoryWithTranslation((int) $id);
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

        $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();

        $category = $this->categoryRepository->create($data);

        $this->clearAllCategoriesCache();

        return $this->success($category, 'Category created successfully');
    }

    public function update(categoreyRequest $request, $id): JsonResponse
    {
        $category = $this->categoryRepository->find((int) $id);
        if (! $category) {
            return $this->error('Category not found', 404);
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();

        $category->update($data);

        $this->clearAllCategoriesCache();

        return $this->success($category, 'Category updated successfully');
    }

    public function delete($id): JsonResponse
    {
        $category = $this->categoryRepository->find((int) $id);
        if (! $category) {
            return $this->error('Category not found', 404);
        }

        $category->delete();
        $this->clearAllCategoriesCache();

        return $this->success(null, 'Category deleted successfully');
    }

    /**
     * مسح كل كاشات التصنيفات بعد أي تعديل
     */
    protected function clearAllCategoriesCache(): void
    {
        // زيادة نسخة الكاش لتحديث كل pagination تلقائياً
        Cache::increment('categories:version', 1);

        // مسح الكاشات العامة للتصنيفات
        Cache::tags(['categories'])->flush();
    }
}
