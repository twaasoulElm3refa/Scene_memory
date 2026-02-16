<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Events>
 */
class EventsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => $this->faker->numberBetween(1, 10),
            'city_id' => $this->faker->numberBetween(1,5),
            'category_id' => $this->faker->numberBetween(1,5),
            "title" => $this->faker->sentence,
            'description' => $this->faker->text(),
            'start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'end_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'langitude' => $this->faker->latitude(),
            'lattitude' => $this->faker->longitude(),
        ];
    }
}
