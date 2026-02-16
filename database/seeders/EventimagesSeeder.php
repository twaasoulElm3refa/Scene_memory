<?php

namespace Database\Seeders;

use App\Models\eventsImges;
use Illuminate\Database\Seeder;

class EventimagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $media = [
            [
                'url' => 'https://spotme.com/wp-content/uploads/2020/07/Hero-1.jpg',
                'video' => null,
                'event_id' => 1,
            ],
            [
                'url' => 'https://spotme.com/wp-content/uploads/2020/07/Hero-1.jpg',
                'video' => null,
                'event_id' => 1,
            ],
            [
                'url' => 'https://spotme.com/wp-content/uploads/2020/07/Hero-1.jpg',
                'video' => null,
                'event_id' => 2,
            ],
            [
                'url' => 'https://spotme.com/wp-content/uploads/2020/07/Hero-1.jpg',
                'video' => null,
                'event_id' => 2,
            ],
        ];

        foreach ($media as $item) {
            eventsImges::create($item);
        }


    }
}
