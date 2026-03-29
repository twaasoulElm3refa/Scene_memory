<?php

namespace Database\Seeders;

use App\Jobs\TranslatePlanJob;
use App\Models\licenceType;
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
                'price' => 0,
                'name' => 'free',
                'slug' => Str::slug('free').'-'.time(),
            ],
            [
                'id' => 2,
                'price' => 5,
                'name' => 'basic',
                'slug' => Str::slug('basic').'-'.time(),
            ],
            [
                'id' => 3,
                'price' => 10,
                'name' => 'professional',
                'slug' => Str::slug('professional').'-'.time(),
            ],
            [
                'id' => 4,
                'price' => 20,
                'name' => 'premium',
                'slug' => Str::slug('premium').'-'.time(),
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
