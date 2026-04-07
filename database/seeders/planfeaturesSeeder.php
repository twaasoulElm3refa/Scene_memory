<?php

namespace Database\Seeders;

use App\Jobs\TranslateBenefitJob;
use Illuminate\Database\Seeder;

class planfeaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $feature=[
            [
                'id' => 1,
                'plan_id' => 1,
                'feature' => 'Get 1000 reward points ',
                'is_active' => true,
            ],
            [
                'id' => 2,
                'plan_id' => 1,
                'feature' => 'premium content',
                'is_active' => true,
            ],
            [
                'id' => 3,
                'plan_id' => 1,
                'feature' => 'customer support',
                'is_active' => true,
            ],
            [
                'id' => 4,
                'plan_id' => 2,
                'feature' => 'Get 2500 reward points',
                'is_active' => true,
            ],
            [
                'id' => 5,
                'plan_id' => 2,
                'feature' => 'premium content',
                'is_active' => true,
            ],
            [
                'id' => 6,
                'plan_id' => 2,
                'feature' => 'customer support',
                'is_active' => true,
            ],
            [
                'id' => 7,
                'plan_id' => 2,
                'feature' => 'Notified On New Events',
                'is_active' => true,
            ],
            [
                'id' => 8,
                'plan_id' => 3,
                'feature' => 'Get 5000 reward points',
                'is_active' => true,
            ],
            [
                'id' => 9,
                'plan_id' => 3,
                'feature' => '+1000 Bonus points',
                'is_active' => true,
            ],
            [
                'id' => 10,
                'plan_id' => 3,
                'feature' => 'premium content',
                'is_active' => true,
            ],
            [
                'id' => 11,
                'plan_id' => 3,
                'feature' => 'customer support',
                'is_active' => true,
            ],
            [
                'id' => 12,
                'plan_id' => 3,
                'feature' => 'Notified On New Events',
                'is_active' => true,
            ],
        ];

        foreach ($feature as $f) {
            $plan = \App\Models\PlanBenefits::create($f);
            TranslateBenefitJob::dispatch($plan->id, $plan->feature);
        }
    }
}
