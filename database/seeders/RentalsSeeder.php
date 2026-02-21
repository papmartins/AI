<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Movie;
use App\Models\Rental;
use Faker\Factory as Faker;

class RentalsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing rentals
        Rental::truncate();
        
        $faker = Faker::create();
        
        // Get all users and movies
        $users = User::all();
        $movies = Movie::all();
        
        // Check if we have enough users and movies
        if ($users->count() < 5 || $movies->count() < 20) {
            $this->command->info('Not enough users or movies to create rentals. Need at least 5 users and 20 movies.');
            $this->command->info('Users: ' . $users->count() . ', Movies: ' . $movies->count());
            return;
        }
                
        // Create 200 rental records
        for ($i = 0; $i < 2000; $i++) {
            // Random user and movie
            $user = $users->random();
            $movie = $movies->random();
            
            // Check if this rental already exists
            $existingRental = Rental::where('user_id', $user->id)
                ->where('movie_id', $movie->id)
                ->first();
            
            if ($existingRental) {
                $i--; // Try again
                continue;
            }
            
            // Random dates in the past 6 months
            $rentedAt = $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d');
            $dueDate = date('Y-m-d', strtotime($rentedAt . ' + 7 days'));
            
            // Randomly mark some as returned
            $returned = $faker->boolean(70); // 70% chance of being returned
            
            Rental::create([
                'user_id' => $user->id,
                'movie_id' => $movie->id,
                'rented_at' => $rentedAt,
                'due_date' => $dueDate,
                'returned' => $returned,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
        }
        
    }
}