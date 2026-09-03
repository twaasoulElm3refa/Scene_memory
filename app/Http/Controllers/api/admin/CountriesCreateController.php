<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CountriesRequest;
use App\Jobs\TranslateCountryJob;
use App\Repositories\Contracts\Countries\CountryRepositoryInterface;
use App\Services\LocationCacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Intl\Intl;

class CountriesCreateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CountryRepositoryInterface $countryRepository,
        private readonly LocationCacheService $locationCache
    ) {}

    public function create(CountriesRequest $request)
    {
        $data = $request->validated();

        try {
            $country = DB::transaction(function () use ($data, $request) {
                $code = strtoupper($data['code']);

                $country = $this->countryRepository->create([
                    'code' => $code ?? '',
                    'slug' => Str::slug($code).'-'.time(),
                ]);
                if ($request->hasFile('image')) {
                    $country->update([
                        'image' => $request->file('image')->store('countries', 'public'),
                    ]);
                }

                return $country;
            });

            $arabicName = $this->arabicCountryName($country->code);
            TranslateCountryJob::dispatch($country->id, $arabicName)->afterCommit();

            $this->locationCache->invalidate();

            return $this->success(
                $country->load('translations'),
                'Country Created Successfully'
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function arabicCountryName(string $code): string
    {
        $countryNames = require Intl::getDataDirectory().'/regions/ar.php';

        return $countryNames['Names'][$code] ?? $code;
    }
}
