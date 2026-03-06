<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenresSeeder extends Seeder {
    public function run(): void {
        $genres = [
            ['Action', 'Ação', 'Acción'],
            ['Drama', 'Drama', 'Drama'],
            ['Comedy', 'Comédia', 'Comedia'],
            ['Horror', 'Terror', 'Terror'],
            ['Romance', 'Romance', 'Romance'],
            ['Sci-Fi', 'Ficção Científica', 'Ciencia Ficción'],
            ['Thriller', 'Suspense', 'Suspense']
        ];
        
        foreach ($genres as $genre) {
            DB::table('genres')->insert([
                'name_en' => $genre[0],
                'name_pt' => $genre[1],
                'name_es' => $genre[2],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}