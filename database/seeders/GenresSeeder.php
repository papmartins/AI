<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenresSeeder extends Seeder {
    public function run(): void {
        $genres = ['Action', 'Drama', 'Comedy', 'Horror', 'Romance', 'Sci-Fi', 'Thriller'];
        foreach ($genres as $genre) {
            DB::table('genres')->insert([
                'name' => $genre, 
                'created_at' => now(), 
                'updated_at' => now()
            ]);
        }
    }
}