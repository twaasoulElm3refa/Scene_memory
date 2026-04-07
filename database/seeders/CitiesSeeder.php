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
                'country_id' => 148,
            ],
            [
                'id'=> 2,
                'name' => 'القاهرة',
                'slug' => 'القاهرة',
                'country_id' => 51,
            ],
            [
                'id'=> 3,
                'name' => 'الدقهلية',
                'slug' => 'الدقهلية',
                'country_id' => 51,
            ],
            [
                'id'=> 4,
                'name' => 'لندن',
                'slug' => 'لندن',
                'country_id' => 181,
            ],
            [
                'id'=> 5,
                'name' => 'بغداد',
                'slug' => 'بغداد',
                'country_id' => 78,
            ],
            [
                'id'=> 6,
                'name' => 'مدريد',
                'slug' => 'مدريد',
                'country_id' => 160,
            ],
            [
                'id'=> 7,
                'name' => 'باريس',
                'slug' => 'باريس',
                'country_id' => 59,
            ],
            [
                'id'=> 8,
                'name' => 'واشنطن',
                'slug' => 'واشنطن',
                'country_id' => 182,
            ],
            [
                'id'=> 9,
                'name' => 'موسكو',
                'slug' => 'موسكو',
                'country_id' => 140,
            ],
            [
                'id'=> 10,
                'name' => 'دبي',
                'slug' => 'دبي',
                'country_id' => 180,
            ],
            [
                'id'=> 11,
                'name' => 'شانغهاي',
                'slug' => 'شانغهاي',
                'country_id' => 36,
            ],
            [
                'id'=> 12,
                'name' => 'ميلان',
                'slug' => 'ميلان',
                'country_id' => 80,
            ],
            [
                'id'=> 13,
                'name' => 'برلين',
                'slug' => 'برلين',
                'country_id' => 63,
            ],
            [
                'id'=> 13,
                'name' => 'أثينا',
                'slug' => 'أثينا',
                'country_id' => 65,
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
