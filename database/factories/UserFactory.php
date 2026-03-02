<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
 public function definition(): array
    {
        $name = fake()->unique()->userName(); // أو fake()->name() لو عايز أسماء حقيقية

        return [
            'name'            => $name,
            'email'           => fake()->unique()->safeEmail(),
            'phone'           => fake()->optional(0.7)->numerify('01#########'), 
            'role'            => fake()->randomElement(['user', 'admin', 'editor', 'moderator']),
            'is_active'       => fake()->boolean(90), // 90% active
            'last_login_at'   => fake()->optional(0.8)->dateTimeBetween('-3 months', 'now'),
            'email_verified_at' => fake()->optional(0.85)->dateTimeBetween('-2 months', 'now'),
            'password'        => static::$password ??= Hash::make('12345678'),
            'slug'            => Str::slug($name) . '-' . fake()->randomNumber(4, true), 
            'remember_token'  => Str::random(10),
            'created_at'      => fake()->dateTimeBetween('-1 year', 'now'),
            'updated_at'      => function (array $attributes) {
                return fake()->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
