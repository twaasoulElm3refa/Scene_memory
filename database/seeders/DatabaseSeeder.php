<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(CountriesSeeder::class);
        $this->call(CitiesSeeder::class);
        $this->call(CategoriesSeeder::class);
        $this->call(SubCategoreySeeder::class);
        $this->call(EventsSeeder::class);
        $this->call(EventimagesSeeder::class);
    }
}