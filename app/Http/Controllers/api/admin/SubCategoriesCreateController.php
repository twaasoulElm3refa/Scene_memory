<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Models\subCategorey;
use App\Models\SubCategoreyTranslations;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Stichoza\GoogleTranslate\GoogleTranslate;

class SubCategoriesCreateController extends Controller
{
    use ApiResponse;

    public function create(categoreyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $subCategory = DB::transaction(function () use ($data, $request) {

                if ($request->hasFile('image')) {
                    $data['image'] = $request->file('image')->store('sub_categories', 'public');
                }
                $subCategory = subCategorey::create([
                    'name' => $data['name'] ?? '',
                    'image' => $data['image'] ?? '',
                    'slug' => str_replace(' ', '-', strtolower($data['name'])).'-'.time(),
                    'category_id' => $data['category_id'] ?? null, 
                ]);
                SubCategoreyTranslations::create([
                    'category_id' => $subCategory->id,
                    'locale' => 'ar',
                    'name' => $data['name'],
                ]);
                $locales = ['en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
                foreach ($locales as $locale) {
                    $translated = $this->translateFree($data['name'], 'ar', $locale);
                    if ($translated) {
                        $subCategory->translations()->create([
                            'locale' => $locale,
                            'name' => $translated,
                        ]);
                    }
                }
                $this->clearCache();
                return $subCategory;
            });
            Cache::increment('categories_cache_version');
            return $this->success($subCategory->load('translations'), 'SubCategory created successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * دالة الترجمة باستخدام GoogleTranslate
     */
    private function translateFree($text, $source, $target)
    {
        try {
            $tr = new GoogleTranslate($target);
            $tr->setSource($source);

            return $tr->translate($text);

        } catch (\Exception $e) {
            \Log::error('Translate Error: '.$e->getMessage());

            return null;
        }
    }

    private function clearCache()
    {
        for( $i = 0; $i < 10; $i++ ) {
         $cacheKey = "categories:paginated:v1:p{$i}:pp4";
         Cache::forget($cacheKey);
        }
        Cache::flush();
    }
}
