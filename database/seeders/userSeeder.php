<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class userSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'محمد أحمد', 'email' => 'mohamed1@example.com', 'password' => bcrypt('123456')],
            ['name' => 'أحمد سعيد', 'email' => 'ahmed2@example.com', 'password' => bcrypt('123456')],
            ['name' => 'سارة علي', 'email' => 'sara3@example.com', 'password' => bcrypt('123456')],
            ['name' => 'ليلى حسن', 'email' => 'layla4@example.com', 'password' => bcrypt('123456')],
            ['name' => 'كريم مصطفى', 'email' => 'karim5@example.com', 'password' => bcrypt('123456')],
        ];

        foreach ($users as $user) {
            User::create($user);
        }

    }
}
