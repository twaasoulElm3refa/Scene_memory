<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CountriesRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Intl\Countries;

class CountriesCreateController extends Controller
{
    use ApiResponse;

    public function create(CountriesRequest $request)
    {
        $data = $request->validated();

        try {
            $country = DB::transaction(function () use ($data, $request) {
                $code = strtoupper($data['code']);

                $country = \App\Models\Countries::create([
                    'code' => $code ?? '',
                    'slug' => Str::slug($code).'-'.time(),
                ]);
                if ($request->hasFile('image')) {
                    $country->update([
                        'image' => $request->file('image')->store('countries', 'public'),
                    ]);
                }
                $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
                foreach ($locales as $locale) {
                    $name = Countries::getName($code, $locale);
                    if ($name) {
                        $country->translations()->create([
                            'locale' => $locale,
                            'name' => $name,
                        ]);
                    }
                }

                return $country;
            });

            Cache::forget('countries_count');
            Cache::forget('countries_index');
            for( $i = 0; $i < 10; $i++ ) {
                Cache::forget("countries_index_page_{$i}");
            }

            return $this->success(
                $country->load('translations'),
                'Country Created Successfully'
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
