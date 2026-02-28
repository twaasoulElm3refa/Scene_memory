<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Models\CategoreyTranslations;
use App\Models\Categories;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Stichoza\GoogleTranslate\GoogleTranslate;

class CategoriesCreateController extends Controller
{
    use ApiResponse;

    public function create(categoreyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $category = DB::transaction(function () use ($data, $request) {
                if ($request->hasFile('image')) {
                    $data['image'] = $request->file('image')->store('categories', 'public');
                }


                $category = Categories::create([
                    'name'=> $data['name'] ?? '',
                    'image'=> $data['image'] ?? ''  ,
                    'slug' => str_replace(' ', '-', strtolower($data['name'])).'-'.time(),
                ]);

                CategoreyTranslations::create([
                    'category_id' => $category->id,
                    'locale' => 'ar',
                    'name' => $data['name'],
                ]);

                $locales = ['en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];

                foreach ($locales as $locale) {
                    $translated = $this->translateFree($data['name'], 'ar', $locale);
                    if ($translated) {
                        $category->translations()->create([
                            'locale' => $locale,
                            'name' => $translated,
                        ]);
                    }
                }
                return $category;
            });
            $this->clearAllCategoriesCache();
            return $this->success($category->load('translations'), 'Category created successfully');

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

    protected function clearAllCategoriesCache(): void
    {
        Cache::increment('categories:version', 1);
        Cache::forget('categories:index:all');
    }
}
