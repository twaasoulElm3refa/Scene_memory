<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Jobs\TranslateSubCategoryJob;
use App\Repositories\Contracts\SubCategories\SubCategoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubCategoriesCreateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SubCategoryRepositoryInterface $subCategoryRepository) {}

    public function create(categoreyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $subCategory = DB::transaction(function () use ($data, $request) {
                if ($request->hasFile('image')) {
                    $data['image'] = $request->file('image')->store('sub_categories', 'public');
                }

                return $this->subCategoryRepository->create([
                    'name' => $data['name'] ?? '',
                    'image' => $data['image'] ?? '',
                    'slug' => Str::slug($data['name']).'-'.time(),
                    'category_id' => $data['category_id'] ?? null,
                ]);
            });

            // Dispatch job for translation
            TranslateSubCategoryJob::dispatch($subCategory->id, $data['name'])->afterCommit();

            // مسح كل كاش التصنيفات
            $this->clearAllCategoriesCache();

            return $this->success(
                $subCategory->load('translations'),
                'SubCategory created successfully'
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * مسح كل الكاشات المتعلقة بالتصنيفات والـ sub-categories
     */
    private function clearAllCategoriesCache()
    {
        // زيادة نسخة الكاش لتحديث كل pagination تلقائي
        Cache::increment('categories_cache_version');

        // مسح الكاشات العامة للتصنيفات
        Cache::tags(['categories', 'subCategories'])->flush();
    }
}
