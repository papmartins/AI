<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'year' => $this->faker->year,
            'genre_id' => \App\Models\Genre::factory(),
            'age_rating' => $this->faker->randomElement(['G', 'PG', 'PG-13', 'R', 'NC-17']),
            'price' => $this->faker->randomFloat(2, 1, 20),
            'stock' => $this->faker->numberBetween(1, 50),
        ];
    }
}
