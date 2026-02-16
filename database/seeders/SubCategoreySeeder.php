<?php

namespace Database\Seeders;

use App\Models\Categories;
use App\Models\subCategorey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubCategoreySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'رياضي' => ['كرة قدم', 'لياقة بدنية'],
            'فني' => ['رسم', 'موسيقى'],
            'سياحي' => ['رحلات داخلية', 'رحلات خارجية'],
            'ثقافي' => ['أدب', 'تاريخ'],
            'تعليمي' => ['دورات تدريبية', 'ورش عمل'],
        ];

        $categories = Categories::all();

        foreach ($categories as $category) {
            if (! isset($data[$category->name])) {
                continue;
            }

            foreach ($data[$category->name] as $sub) {
                subCategorey::create([
                    'name' => $sub,
                    'category_id' => $category->id,
                    'slug'=>Str::slug($sub).'-'.Str::random(5).'-'.time(),
                ]);
            }
        }
    }
}
