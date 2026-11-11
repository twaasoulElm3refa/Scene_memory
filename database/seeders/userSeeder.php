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
            'name'=> 'Scemory Admin',
            'email'=> 'scemorygmail@gmail.com',
            'role'=> 'admin',
            'password'=> bcrypt('Scemory@2026'),
            'slug'=> 'scemory-admin',
          ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }

    }
}
