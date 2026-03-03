<?php

namespace Database\Seeders;

use App\Models\CategoreyTranslations;
use App\Models\Categories;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categgories = [
            [
                'name' => 'رياضي',
                'slug' => 'رياضي',
            ],
            [
                'name' => 'فني',
                'slug' => 'فني',
            ],

            [
                'name' => 'سياحي',
                'slug' => 'سياحي',
            ],

            [
                'name' => 'ثقافي',
                'slug' => 'ثقافي',
            ],
            [
                'name' => 'تعليمي',
                'slug' => 'تعليمي',
            ],
        ];
        foreach ($categgories as $category) {
          $categorey=  Categories::create($category);
            CategoreyTranslations::create([
                'category_id'=> $categorey->id,
                'locale' => 'ar',
                'name' => $category['name'],
            ]);
        }
    }
}
