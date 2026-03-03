<?php

namespace Database\Seeders;

use App\Jobs\TranslateCityJob;
use App\Models\Cities;
use App\Models\CityTranslations;
use Illuminate\Database\Seeder;

class CitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            [
                'id' => 1,
                'name' => 'الرياض',
                'slug' => 'الرياض',
                'country_id' => 192,
            ],
            [
                'id'=> 2,
                'name' => 'القاهرة',
                'slug' => 'القاهرة',
                'country_id' => 63,
            ],
        ];

        foreach ($cities as $city) {
           $city= Cities::create($city);
           CityTranslations::create([
                'city_id' => $city->id,
                'locale'  => 'ar',
                'name'    => $city->name,
            ]);
            TranslateCityJob::dispatch($city->id, $city->name);
        }
    }
}
