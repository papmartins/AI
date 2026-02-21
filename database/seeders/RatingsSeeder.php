<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Seeder;

class RatingsSeeder extends Seeder
{
    public function run(): void
    {
        $users  = User::pluck('id')->all();
        $movies = Movie::pluck('id')->all();

        if (empty($users) || empty($movies)) {
            return;
        }

        $ratings = [];
        $now = now();

        // cada user avalia entre 20 e 80 filmes
        foreach ($users as $userId) {
            $user = \App\Models\User::find($userId);
            $moviesForUser = collect($movies)->shuffle()->take(rand(20, 80));

            foreach ($moviesForUser as $movieId) {
                // Create inconsistent ratings for Mood Swinger
                if ($user && $user->email === 'mood@email.com') {
                    // Very inconsistent ratings: mostly 1s and 5s with some 3s
                    $rating = rand(0, 10) < 7 ? (rand(0, 1) ? 1 : 5) : 3;
                } else {
                    // Normal ratings for other users
                    $rating = rand(1, 5);
                }

                $ratings[] = [
                    'user_id'    => $userId,
                    'movie_id'   => $movieId,
                    'rating'     => $rating,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // inserir em chunks para performance
        foreach (array_chunk($ratings, 500) as $chunk) {
            Rating::insert($chunk);
        }
    }
}
