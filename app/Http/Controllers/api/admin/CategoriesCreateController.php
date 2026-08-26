<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Jobs\TranslateCategoryJob;
use App\Repositories\Contracts\Categories\CategoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesCreateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CategoryRepositoryInterface $categoryRepository) {}

    public function create(categoreyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $category = DB::transaction(function () use ($data, $request) {
                if ($request->hasFile('image')) {
                    $data['image'] = $request->file('image')->store('categories', 'public');
                }

                return $this->categoryRepository->create([
                    'name' => $data['name'] ?? '',
                    'image' => $data['image'] ?? '',
                    'slug' => Str::slug($data['name']).'-'.time(),
                ]);
            });

            // Dispatch translation job
            TranslateCategoryJob::dispatch($category->id, $data['name'])->afterCommit();

            // Clear cache after creation
            $this->clearAllCategoriesCache();

            return $this->success(
                $category->load('translations'),
                'Category created successfully'
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Clear all cache related to categories
     */
    protected function clearAllCategoriesCache(): void
    {
        // Increase version to invalidate paginated cache automatically
        Cache::increment('categories:version', 1);

        // Flush all category-related cache using tags
        Cache::tags(['categories'])->flush();
    }
}
