<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Models\Categories;
use App\Jobs\TranslateCategoryJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesCreateController extends Controller
{
    use ApiResponse;

    public function create(categoreyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {

            $category = DB::transaction(function () use ($data, $request) {

                if ($request->hasFile('image')) {
                    $data['image'] = $request->file('image')
                        ->store('categories', 'public');
                }

                $category = Categories::create([
                    'name'  => $data['name'] ?? '',
                    'image' => $data['image'] ?? '',
                    'slug'  => Str::slug($data['name']) . '-' . time(),
                ]);

                return $category;
            });

            TranslateCategoryJob::dispatch(
                $category->id,
                $data['name']
            );

            $this->clearAllCategoriesCache();

            return $this->success(
                $category->load('translations'),
                'Category created successfully'
            );

        } catch (\Exception $e) {

            return $this->error($e->getMessage());
        }
    }

    protected function clearAllCategoriesCache(): void
    {
        Cache::increment('categories:version', 1);
        Cache::forget('categories:index:all');
    }
}
