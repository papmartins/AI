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

        // Get suggestions - top rated movies from popular genres
        $suggestions = $this->getMovieSuggestions();

        return Inertia::render('Movies/Index', [
            'movies' => $movies,
            'genres' => Genre::all(),
            'filters' => $request->only(['search', 'genre']),
            'rentedMovieIds' => $rentedMovieIds,
            'suggestions' => $suggestions,
        ]);
    }
    
    /**
     * Get movie suggestions based on popular genres and ratings
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getMovieSuggestions() {
        // Get top 3 most popular genres based on number of movies rented
        $popularGenres = Genre::withCount(['movies' => function($query) {
            $query->whereHas('rentals');
        }])
        ->orderBy('movies_count', 'desc')
        ->take(3)
        ->get();

        if ($popularGenres->isEmpty()) {
            // Fallback: get top rated movies from any genre
            return Movie::with('genre')
                ->withAvg('ratings', 'rating')
                ->orderBy('ratings_avg_rating', 'desc')
                ->take(4)
                ->get();
        }
                
        // Get top rated movies from popular genres
        $suggestions = collect();
        foreach ($popularGenres as $genre) {
            $genreMovies = Movie::with('genre')
                ->withAvg('ratings', 'rating')
                ->where('genre_id', $genre->id)
                ->orderBy('ratings_avg_rating', 'desc')
                ->take(2)
                ->get();
            
            $suggestions = $suggestions->merge($genreMovies);
        }
        
        return $suggestions->take(4);
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
