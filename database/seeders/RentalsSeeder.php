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
                
        // Create normal rental records
        for ($i = 0; $i < 1800; $i++) {
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
            
            // Randomly mark some as returned and set returned_at timestamp
            $returned = $faker->boolean(70); // 70% chance of being returned
            $returnedAt = null;
            
            if ($returned) {
                // 30% chance of being late, 70% chance of being on time
                if ($faker->boolean(30)) {
                    // Late return (3-14 days late)
                    $daysLate = rand(3, 14);
                    $returnedAt = date('Y-m-d H:i:s', strtotime($dueDate . " + $daysLate days"));
                } else {
                    // On-time return (within rental period)
                    $daysRented = rand(1, 6); // Returned 1-6 days after renting
                    $returnedAt = date('Y-m-d H:i:s', strtotime($rentedAt . " + $daysRented days"));
                }
            }
            
            Rental::create([
                'user_id' => $user->id,
                'movie_id' => $movie->id,
                'rented_at' => $rentedAt,
                'due_date' => $dueDate,
                'returned' => $returned,
                'returned_at' => $returnedAt,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Create anomalous rental patterns for specific test users
        $this->createAnomalousRentals($users, $movies, $faker);
    }
    
    /**
     * Create anomalous rental patterns for testing anomaly detection
     */
    protected function createAnomalousRentals($users, $movies, $faker): void
    {
        // Find our test users by email
        $rapidRenter = $users->firstWhere('email', 'rapid@email.com');
        $lateLarry = $users->firstWhere('email', 'late@email.com');
        $suspiciousSam = $users->firstWhere('email', 'suspicious@email.com');
        
        if ($rapidRenter) {
            // Rapid Renter: High rental frequency (50 rentals in 30 days)
            $this->command->info('Creating high-frequency rentals for Rapid Renter...');
            for ($i = 0; $i < 50; $i++) {
                $movie = $movies->random();
                $existingRental = Rental::where('user_id', $rapidRenter->id)
                    ->where('movie_id', $movie->id)
                    ->first();
                
                if ($existingRental) continue;
                
                // Rentals in the last 30 days
                $rentedAt = $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d');
                $dueDate = date('Y-m-d', strtotime($rentedAt . ' + 7 days'));
                $returned = $faker->boolean(80); // 80% returned
                $returnedAt = null;
                
                if ($returned) {
                    // Rapid renter sometimes returns late
                    if ($faker->boolean(20)) {
                        $daysLate = rand(1, 7);
                        $returnedAt = date('Y-m-d H:i:s', strtotime($dueDate . " + $daysLate days"));
                    } else {
                        $daysRented = rand(1, 5);
                        $returnedAt = date('Y-m-d H:i:s', strtotime($rentedAt . " + $daysRented days"));
                    }
                }
                
                Rental::create([
                    'user_id' => $rapidRenter->id,
                    'movie_id' => $movie->id,
                    'rented_at' => $rentedAt,
                    'due_date' => $dueDate,
                    'returned' => $returned,
                    'returned_at' => $returnedAt,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        if ($lateLarry) {
            // Late Larry: Frequent late returns (20 rentals, 90% late)
            $this->command->info('Creating late return rentals for Late Larry...');
            for ($i = 0; $i < 20; $i++) {
                $movie = $movies->random();
                $existingRental = Rental::where('user_id', $lateLarry->id)
                    ->where('movie_id', $movie->id)
                    ->first();
                
                if ($existingRental) continue;
                
                $rentedAt = $faker->dateTimeBetween('-6 months', '-1 month')->format('Y-m-d');
                $dueDate = date('Y-m-d', strtotime($rentedAt . ' + 7 days'));
                
                // 90% chance of being late (returned 10-30 days late)
                if ($faker->boolean(90)) {
                    $returnDaysLate = rand(10, 30);
                    $returnedAt = date('Y-m-d H:i:s', strtotime($dueDate . " + $returnDaysLate days"));
                    Rental::create([
                        'user_id' => $lateLarry->id,
                        'movie_id' => $movie->id,
                        'rented_at' => $rentedAt,
                        'due_date' => $dueDate,
                        'returned' => true,
                        'returned_at' => $returnedAt,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } else {
                    // 10% returned on time
                    $daysRented = rand(2, 5);
                    $returnedAt = date('Y-m-d H:i:s', strtotime($rentedAt . " + $daysRented days"));
                    Rental::create([
                        'user_id' => $lateLarry->id,
                        'movie_id' => $movie->id,
                        'rented_at' => $rentedAt,
                        'due_date' => $dueDate,
                        'returned' => true,
                        'returned_at' => $returnedAt,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
        
        if ($suspiciousSam) {
            // Suspicious Sam: Combination of bad behaviors
            $this->command->info('Creating suspicious rentals for Suspicious Sam...');
            for ($i = 0; $i < 35; $i++) {
                $movie = $movies->random();
                $existingRental = Rental::where('user_id', $suspiciousSam->id)
                    ->where('movie_id', $movie->id)
                    ->first();
                
                if ($existingRental) continue;
                
                // High frequency in short time
                $rentedAt = $faker->dateTimeBetween('-21 days', 'now')->format('Y-m-d');
                $dueDate = date('Y-m-d', strtotime($rentedAt . ' + 7 days'));
                
                // 60% chance of being late
                if ($faker->boolean(60)) {
                    $returnDaysLate = rand(5, 20);
                    $returnedAt = date('Y-m-d H:i:s', strtotime($dueDate . " + $returnDaysLate days"));
                    Rental::create([
                        'user_id' => $suspiciousSam->id,
                        'movie_id' => $movie->id,
                        'rented_at' => $rentedAt,
                        'due_date' => $dueDate,
                        'returned' => true,
                        'returned_at' => $returnedAt,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } else {
                    // 40% returned on time or not returned
                    $returned = $faker->boolean(70);
                    $returnedAt = null;
                    if ($returned) {
                        $daysRented = rand(1, 6);
                        $returnedAt = date('Y-m-d H:i:s', strtotime($rentedAt . " + $daysRented days"));
                    }
                    Rental::create([
                        'user_id' => $suspiciousSam->id,
                        'movie_id' => $movie->id,
                        'rented_at' => $rentedAt,
                        'due_date' => $dueDate,
                        'returned' => $returned,
                        'returned_at' => $returnedAt,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}