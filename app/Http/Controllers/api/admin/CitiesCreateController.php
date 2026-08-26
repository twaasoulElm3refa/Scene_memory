<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Jobs\TranslateCityJob;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitiesCreateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CityRepositoryInterface $cityRepository) {}

    public function create(CityRequest $request)
    {
        $data = $request->validated();
        try {
            $city = DB::transaction(function () use ($data) {
                $city = $this->cityRepository->create([
                    'name' => $data['name'] ?? '',
                    'country_id' => $data['country_id'] ?? null,
                    'slug' => Str::slug($data['name']).'-'.time(),
                ]);

                return $city;
            });

            // Job لترجمة المدينة
            TranslateCityJob::dispatch($city->id, $data['name'])->afterCommit();

            // مسح كل الكاش المرتبط بالمدن بعد إنشاء مدينة جديدة
            $this->clearCache();

            return $this->success(
                $city->load('translations'),
                'City Created Successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * مسح كل الكاش المرتبط بالمدن
     */
    private function clearCache()
    {
        // مسح كل الـ cache المرتبط بالـ tag 'cities'
        Cache::tags(['cities'])->flush();
    }
}
