<?php

namespace Database\Seeders;

use App\Models\Countries;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'id' => 1,
                'name' => 'مصر',
                'slug' => 'مصر',
            ],
            [
                'id' => 2,
                'name' => 'السعوديه',
                'slug' => 'السعوديه',
            ],
        ];
        
        foreach ($countries as $country) {
            Countries::create($country);
        }
    }
}
