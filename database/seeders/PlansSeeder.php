<?php

namespace Database\Seeders;

use App\Jobs\TranslatePlanJob;
use App\Models\licenceType;
use App\Models\PlanTranslations;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'id' => 1,
                'price' => 0,
                'name' => 'free',
            ],
            [
                'id' => 2,
                'price' => 5,
                'name' => 'basic',
            ],
            [
                'id' => 3,
                'price' => 10,
                'name' => 'professional',
            ],
            [
                'id' => 4,
                'price' => 20,
                'name' => 'premium',
            ],
        ];

        foreach ($plans as $plan) {
            $createdPlan = licenceType::create($plan);

            PlanTranslations::create([
                'plan_id' => $createdPlan->id,
                'locale' => 'en',
                'name' => $plan['name'],
            ]);
            TranslatePlanJob::dispatch($createdPlan->id, $createdPlan->name);
        }
    }
}
