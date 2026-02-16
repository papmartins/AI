<?php
namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Genre;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MovieController extends Controller {
    public function index(Request $request) {
        $query = Movie::with('genre')
            ->when($request->search, fn($q) => $q->where('title', 'like', '%'.$request->search.'%'))
            ->when($request->genre, fn($q) => $q->where('genre_id', $request->genre));

        return Inertia::render('Movies/Index', [
            'movies' => $query->paginate(12)->withQueryString(),
            'genres' => Genre::all(),
            'filters' => $request->only(['search', 'genre'])
        ]);
    }

    public function show(Movie $movie) {
        $movie->load(['genre', 'ratings.user', 'userRating']);
        return Inertia::render('Movies/Show', compact('movie'));
    }
}
