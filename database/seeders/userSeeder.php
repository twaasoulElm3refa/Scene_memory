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
          [
            'id'=>1,
            'name'=> 'Mohamed Maher',
            'email'=> 'm7mdellham77@gmail.com',
            'password'=> bcrypt('password'),
          ],
          [
            'id'=>2,
            'name'=> 'Scemory Admin',
            'email'=> 'admin@admin.com',
            'password'=> bcrypt('Scemory@2026'),
          ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }

    }
}
