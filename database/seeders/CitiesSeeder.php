<?php

namespace Database\Seeders;

use App\Models\Cities;
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
                'country_id' => 2,
            ],
            [
                'id'=> 2,
                'name' => 'القاهرة',
                'slug' => 'القاهرة',
                'country_id' => 1,
            ],
        ];

        foreach ($cities as $city) {
            Cities::create($city);
        }
    }
}
