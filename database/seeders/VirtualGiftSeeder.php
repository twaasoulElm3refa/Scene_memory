<?php

namespace Database\Seeders;

use App\Models\VirtualGift;
use Illuminate\Database\Seeder;

class VirtualGiftSeeder extends Seeder
{
    public function run(): void
    {
        $names = json_decode(file_get_contents(__DIR__.'/data/virtual_gift_names.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ([['coffee', 'Coffee Cup', 100], ['cake', 'Cake Slice', 300], ['rose', 'Rose', 500], ['gift-box', 'Gift Box', 1000], ['trophy', 'Golden Trophy', 2500]] as $index => [$slug, $name, $price]) {
            $translations = [];
            foreach ($names as $locale => $labels) {
                if ($locale !== 'en') {
                    $translations[$locale] = ['name' => $labels[$index]];
                }
            }
            VirtualGift::firstOrCreate(['slug' => $slug], ['name' => $name, 'price_minor' => $price, 'currency' => 'USD', 'translations' => $translations, 'image_path' => '/images/gifts/'.$slug.'.svg', 'sort_order' => $index, 'is_active' => true]);
        }
    }
}
