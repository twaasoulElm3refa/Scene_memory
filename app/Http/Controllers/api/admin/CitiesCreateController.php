<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Models\Cities;
use App\Models\CityTranslations;
use App\Jobs\TranslateCityJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitiesCreateController extends Controller
{
    use ApiResponse;

    public function create(CityRequest $request)
    {
        $data = $request->validated();

        try {

            $city = DB::transaction(function () use ($data) {

                $city = Cities::create([
                    'name'       => $data['name'] ?? '',
                    'country_id' => $data['country_id'] ?? null,
                    'slug'       => Str::slug($data['name']) . '-' . time(),
                ]);

                return $city;
            });

            TranslateCityJob::dispatch(
                $city->id,
                $data['name']
            );

            $this->clearCache();

            return $this->success(
                $city->load('translations'),
                'City Created Successfully'
            );

        } catch (\Exception $e) {

            return $this->error($e->getMessage());
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
