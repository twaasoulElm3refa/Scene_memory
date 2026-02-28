<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Models\Cities;
use App\Models\CityTranslations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stichoza\GoogleTranslate\GoogleTranslate;

class CitiesCreateController extends Controller
{
    use ApiResponse;

    public function create(CityRequest $request)
    {
        $data = $request->validated();
        try {
            $city = DB::transaction(function () use ($data) {
                $city = Cities::create([
                    'name' => $data['name'] ?? '',
                    'country_id' => $data['country_id'] ?? null,
                    'slug' => Str::slug($data['name']).'-'.time(),
                ]);
                $locales = ['en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
                CityTranslations::create([
                    'city_id' => $city->id,
                    'locale' => 'ar',
                    'name' => $data['name'],
                ]);
                foreach ($locales as $locale) {
                    $translated = $this->translateFree(
                        $data['name'],
                        'ar',
                        $locale
                    );
                    if ($translated) {
                        $city->translations()->create([
                            'locale' => $locale,
                            'name' => $translated,
                        ]);
                    }
                }

                return $city;
            });
            $this->clearCache();
            return $this->success($city->load('translations'), 'City Created Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

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
        Cache::forget('cities_count');
        for ($i = 0; $i < 10; $i++) {
            Cache::forget('cities_index_page_'.$i);
        }
        Cache::flush();
    }
}
