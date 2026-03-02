<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Models\subCategorey;
use App\Models\SubCategoreyTranslations;
use App\Jobs\TranslateSubCategoryJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubCategoriesCreateController extends Controller
{
    use ApiResponse;

    public function create(categoreyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {

            $subCategory = DB::transaction(function () use ($data, $request) {

                if ($request->hasFile('image')) {
                    $data['image'] = $request->file('image')
                        ->store('sub_categories', 'public');
                }

                $subCategory = subCategorey::create([
                    'name'        => $data['name'] ?? '',
                    'image'       => $data['image'] ?? '',
                    'slug'        => Str::slug($data['name']) . '-' . time(),
                    'category_id' => $data['category_id'] ?? null,
                ]);

                SubCategoreyTranslations::create([
                    'category_id' => $subCategory->id,
                    'locale'      => 'ar',
                    'name'        => $data['name'],
                ]);

                return $subCategory;
            });

            TranslateSubCategoryJob::dispatch(
                $subCategory->id,
                $data['name']
            );

            $this->clearCache();

            Cache::increment('categories_cache_version');

            return $this->success(
                $subCategory->load('translations'),
                'SubCategory created successfully'
            );

        } catch (\Exception $e) {

            return $this->error($e->getMessage());
        }
    }

    private function clearCache()
    {
        for ($i = 0; $i < 10; $i++) {
            $cacheKey = "categories:paginated:v1:p{$i}:pp4";
            Cache::forget($cacheKey);
        }
    }
}