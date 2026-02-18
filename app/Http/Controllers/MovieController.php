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

        $movies = $query->paginate(12)->withQueryString();
        $movies->getCollection()->transform(function($movie) {
            $movie->avg_rating = $movie->ratings()->avg('rating');
            return $movie;
        });
        $rentedMovieIds = [];
        if (auth()->check()) {
            $rentedMovieIds = auth()->user()->rentals()->where('returned', 0)->pluck('movie_id')->toArray();
        }

        return Inertia::render('Movies/Index', [
            'movies' => $movies,
            'genres' => Genre::all(),
            'filters' => $request->only(['search', 'genre']),
            'rentedMovieIds' => $rentedMovieIds,
        ]);
    }

    public function show(Movie $movie) {
        $movie->load(['genre', 'ratings.user', 'userRating']);
        $isInWishlist = false;
        $isRented = false;
        $userRating = null;

        if (auth()->check()) {
            $isInWishlist = auth()->user()->wishlist()->where('movie_id', $movie->id)->exists();
            $isRented = auth()->user()->rentals()->where('returned', 0)->where('movie_id', $movie->id)->exists();
            $userRating = $movie->userRating?->rating;
        }

        return Inertia::render('Movies/Show', compact('movie', 'isInWishlist', 'isRented', 'userRating'));
    }
}
