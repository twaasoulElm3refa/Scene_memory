<?php

namespace Database\Seeders;

use App\Models\licenceType;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'id' => 1,
                'price' => 0,
                'name' => 'مستوي اساسي',
            ],
            [
                'id' => 2,
                'price' => 5,
                'name' => 'مستوي احترافي',
            ],
            [
                'id' => 3,
                'price' => 10,
                'name' => 'مستوي مؤسسي',
            ],
        ];

        foreach ($plans as $plan) {
            licenceType::create($plan);
        }
    }
}
