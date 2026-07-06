<?php

namespace Database\Seeders;

use App\Jobs\TranslatePlanJob;
use App\Models\LicenceType;
use App\Models\PlanTranslations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'id' => 1,
                'price' => 25,
                'name' => 'basic',
                'slug' => Str::slug('basic').'-'.time(),
            ],
            [
                'id' => 2,
                'price' => 50,
                'name' => 'professional',
                'slug' => Str::slug('professional').'-'.time(),
            ],
            [
                'id' => 3,
                'price' => 100,
                'name' => 'premium',
                'slug' => Str::slug('premium').'-'.time(),
            ],
        ];

        foreach ($plans as $plan) {
            $createdPlan = LicenceType::create($plan);
            PlanTranslations::create([
                'plan_id' => $createdPlan->id,
                'locale' => 'en',
                'name' => $plan['name'],
            ]);
            TranslatePlanJob::dispatch($createdPlan->id, $createdPlan->name);
        }
    }
}
