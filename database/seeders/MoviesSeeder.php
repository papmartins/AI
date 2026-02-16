<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class MoviesSeeder extends Seeder
{
    public function run(): void
    {
        $genres = Genre::all();
        if ($genres->isEmpty()) {
            return; // Para se não houver genres
        }

        $movies = [];
        $now = now();
        $titles = [
            'Die Hard', 'Mad Max Fury Road', 'John Wick', 'Dark Knight', 'Gladiator',
            'Shawshank Redemption', 'Forrest Gump', 'Fight Club', 'Godfather', 'Whiplash',
            'Big Lebowski', 'Superbad', 'Step Brothers', 'Anchorman', 'Groundhog Day',
            'The Shining', 'Get Out', 'Nightmare Elm Street', 'Hereditary', 'Exorcist',
            'The Notebook', 'La La Land', 'Titanic', 'Pride Prejudice', 'Before Sunrise'
        ];
        $descs = [
            'Epic action', 'High-octane chase', 'Revenge story', 'Superhero epic', 'Roman battles',
            'Prison friendship', 'Life journey', 'Mind-bending drama', 'Mafia family', 'Music passion',
            'Cult comedy', 'Teen chaos', 'Adult kids', 'News satire', 'Time loop',
            'Haunted hotel', 'Social horror', 'Dream killer', 'Family curse', 'Demon possession',
            'True love', 'Dream chasers', 'Ship tragedy', 'Class romance', 'Train romance'
        ];

        for ($i = 0; $i < 200; $i++) {
            $movies[] = [
                'title'       => $titles[$i % count($titles)] . ' #' . ($i + 1),
                'description' => $descs[$i % count($descs)],
                'year'        => rand(1970, 2025),
                'genre_id'    => $genres->random()->id,
                'price'       => rand(150, 400) / 100,
                'stock'       => rand(1, 10),
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        // Insere de uma vez (rápido!)
        Movie::insert($movies);
    }
}
