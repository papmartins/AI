<?php
namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Genre;
use App\Models\User;
use App\Services\MovieRecommender;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MovieController extends Controller {
    public function __construct(protected MovieRecommender $recommender)
    {
    }
    
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

        // Get suggestions using ML recommender if user is authenticated
        $suggestions = [];
        if (auth()->check()) {
            try {
                // $suggestions = $this->getMovieSuggestions()->toArray();
                $mlRecommendations = $this->recommender->recommendForUser(auth()->user(), 4);
                
                // Debug: log recommendations
                \Log::info('ML Recommendations for user ' . auth()->user()->id, [
                    'count' => count($mlRecommendations),
                    'recommendations' => array_map(function($item) {
                        return ['movie_id' => $item['movie']->id, 'rating' => $item['predicted_rating']];
                    }, $mlRecommendations)
                ]);
                
                if (!empty($mlRecommendations)) {
                    $suggestions = array_map(function($item) { return $item['movie']; }, $mlRecommendations);
                }
            } catch (\Exception $e) {
                \Log::error('ML Recommendation failed: ' . $e->getMessage());
                // Fallback to popular recommendations
                $popularRecs = $this->recommender->getPopularRecommendations(4);
                $suggestions = array_map(function($item) { return $item['movie']; }, $popularRecs);
            }
        } else {
            // $suggestions = $this->getMovieSuggestions()->toArray();
            $suggestions = $this->recommender->getPopularRecommendations(4);
            $suggestions = array_map(function($item) { return $item['movie']; }, $suggestions);
        }
        
        // Debug: log final suggestions
        \Log::info('Final suggestions', ['count' => count($suggestions)]);

        return Inertia::render('Movies/Index', [
            'movies' => $movies,
            'genres' => Genre::all(),
            'filters' => $request->only(['search', 'genre']),
            'rentedMovieIds' => $rentedMovieIds,
            'suggestions' => $suggestions,
            'usingMLRecommendations' => auth()->check(),
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
                ->having('ratings_avg_rating', '>', 0)
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
        $similarMovies = [];

        if (auth()->check()) {
            $isInWishlist = auth()->user()->wishlist()->where('movie_id', $movie->id)->exists();
            $isRented = auth()->user()->rentals()->where('returned', 0)->where('movie_id', $movie->id)->exists();
            $userRating = $movie->userRating?->rating;
            
            // Get "You might also like" recommendations based on current movie
            $similarMovies = $this->getSimilarMovies($movie, auth()->user());
        } else {
            // Get similar movies for guests
            $similarMovies = $this->getSimilarMovies($movie, null);
        }

        return Inertia::render('Movies/Show', [
            'movie' => $movie,
            'isInWishlist' => $isInWishlist,
            'isRented' => $isRented,
            'userRating' => $userRating,
            'similarMovies' => $similarMovies,
        ]);
    }

    /**
     * Get movies similar to the given movie
     */
    protected function getSimilarMovies(Movie $movie, ?User $user = null): array
    {
        // Get movies from the same genre, excluding the current movie
        $query = Movie::with('genre')
            ->where('genre_id', $movie->genre_id)
            ->where('id', '!=', $movie->id)
            ->withAvg('ratings', 'rating')
            ->orderBy('ratings_avg_rating', 'desc')
            ->take(4);
            
        // If user is authenticated, exclude movies they've already interacted with
        if ($user) {
            $interactedMovieIds = $user->ratings()->pluck('movie_id')
                ->merge($user->rentals()->pluck('movie_id'))
                ->merge($user->wishlist()->pluck('movie_id'))
                ->unique();
            
            $query->whereNotIn('id', $interactedMovieIds);
        }
        
        return $query->get()->toArray();
    }
}
