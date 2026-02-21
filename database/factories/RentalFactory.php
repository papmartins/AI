<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Movie;

class RentalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::inRandomOrder()->first();
        $movie = Movie::inRandomOrder()->first();
        
        if (!$user || !$movie) {
            return [];
        }
        
        $rentedAt = $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d');
        $dueDate = date('Y-m-d', strtotime($rentedAt . ' + 7 days'));
        
        // 70% chance of being returned
        $returned = $this->faker->boolean(70);
        $returnedAt = null;
        
        if ($returned) {
            // 30% chance of being late
            if ($this->faker->boolean(30)) {
                $daysLate = rand(1, 14);
                $returnedAt = date('Y-m-d H:i:s', strtotime($dueDate . " + $daysLate days"));
            } else {
                $daysRented = rand(1, 6);
                $returnedAt = date('Y-m-d H:i:s', strtotime($rentedAt . " + $daysRented days"));
            }
        }
        
        return [
            'user_id' => $user->id,
            'movie_id' => $movie->id,
            'rented_at' => $rentedAt,
            'due_date' => $dueDate,
            'returned' => $returned,
            'returned_at' => $returnedAt,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
}