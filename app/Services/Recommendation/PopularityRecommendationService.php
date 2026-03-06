<?php

namespace App\Services\Recommendation;

use App\Models\Movie;
use App\Models\User;
use App\Models\Rating;
use App\Models\Rental;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PopularityRecommendationService
{
    protected string $confidenceCachePath;

    public function __construct()
    {
        $this->confidenceCachePath = storage_path(config('ml.movie_recommender.confidence_cache_path', 'app/popularity_confidence.json'));
    }

    public function getPopularRecommendations(int $limit = 6): array
    {
        $cacheKey = 'popular_recommendations_' . $limit;
        
        // Try to get cached recommendations
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Layer 1: Try to get from pre-cached confidence data
        $precalculated = $this->getPrecalculatedPopularityData();
        if (!empty($precalculated)) {
            $result = array_slice($precalculated, 0, $limit);
            Cache::put($cacheKey, $result, 3600);
            return $result;
        }

        // Layer 2: Try to get from cache file
        if (file_exists($this->confidenceCachePath)) {
            $cachedData = json_decode(file_get_contents($this->confidenceCachePath), true);
            if (is_array($cachedData) && !empty($cachedData)) {
                $result = array_slice($cachedData, 0, $limit);
                Cache::put($cacheKey, $result, 3600);
                return $result;
            }
        }

        // Layer 3: Optimized real-time calculation with caching
        $movies = Movie::with(['genre', 'ratings', 'rentals'])
            ->withAvg('ratings', 'rating')
            ->withCount('rentals')
            ->having('ratings_avg_rating', '>', 0)
            ->orderBy('ratings_avg_rating', 'desc')
            ->orderBy('rentals_count', 'desc')
            ->take($limit * 2)
            ->get();

        $recommendations = $movies->map(function($movie) {
            return [
                'movie' => $movie,
                'predicted_rating' => $movie->ratings->avg('rating') ?? 0,
                'confidence' => $this->calculatePopularityConfidence($movie)
            ];
        })->sortByDesc('confidence')->values()->take($limit)->toArray();

        // Cache the result for future requests
        Cache::put($cacheKey, $recommendations, 3600);

        return $recommendations;
    }

    protected function getPrecalculatedPopularityData(): array
    {
        $cacheKey = 'precalculated_popularity_data_v2';
        return Cache::get($cacheKey, []);
    }

    public function cachePopularityConfidence(): void
    {
        Log::info('Caching popularity confidence for all movies');
        
        $movies = Movie::with(['ratings', 'rentals'])->get();
        $confidenceData = [];

        foreach ($movies as $movie) {
            $confidence = $this->calculatePopularityConfidence($movie);
            $confidenceData[] = [
                'movie_id' => $movie->id,
                'title' => $movie->title,
                'avg_rating' => $movie->ratings->avg('rating') ?? 0,
                'rating_count' => $movie->ratings->count(),
                'rental_count' => $movie->rentals->count(),
                'confidence' => $confidence
            ];
        }

        // Sort by confidence and save
        usort($confidenceData, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        file_put_contents($this->confidenceCachePath, json_encode($confidenceData, JSON_PRETTY_PRINT));
        
        // Also cache in memory for 1 hour
        Cache::put('precalculated_popularity_data_v2', $confidenceData, 3600);
        
        Log::info('Popularity confidence cached successfully');
    }

    protected function calculatePopularityConfidence(Movie $movie): float
    {
        $ratingCount = $movie->ratings->count();
        $rentalCount = $movie->rentals->count();
        $avgRating = $movie->ratings->avg('rating') ?? 0;
        
        // Base confidence from average rating (normalized to 0-1 scale)
        $confidence = $avgRating / 5.0;
        
        // Boost confidence based on number of ratings (up to 0.3 bonus)
        if ($ratingCount > 0) {
            $confidence += min($ratingCount / 30, 0.3);
        }
        
        // Boost confidence based on number of rentals (up to 0.2 bonus)
        if ($rentalCount > 0) {
            $confidence += min($rentalCount / 50, 0.2);
        }
        
        // Ensure confidence is between 0.1 and 1.0
        return min(max($confidence, 0.1), 1.0);
    }

    public function getFallbackRecommendations(User $user, int $limit = 6): array
    {
        // Get user's preferred genres from their ratings
        $userGenres = Rating::with('movie.genre')
            ->where('user_id', $user->id)
            ->get()
            ->pluck('movie.genre.id')
            ->unique();

        if ($userGenres->isEmpty()) {
            // If no ratings, use popular genres
            $userGenres = [1, 2, 3]; // Default genres
        }

        // Get movies the user has already interacted with
        $interactedMovieIds = Rating::where('user_id', $user->id)->pluck('movie_id')
            ->merge(Rental::where('user_id', $user->id)->pluck('movie_id'))
            ->merge(Wishlist::where('user_id', $user->id)->pluck('movie_id'))
            ->unique();

        $movies = Movie::with('genre')
            ->withAvg('ratings', 'rating')
            ->withCount(['ratings', 'rentals'])
            ->whereIn('genre_id', $userGenres)
            ->whereNotIn('id', $interactedMovieIds)
            ->orderBy('ratings_avg_rating', 'desc')
            ->orderBy('rentals_count', 'desc')
            ->take($limit * 2) // Get more to allow for filtering
            ->get();

        $recommendations = [];
        foreach ($movies as $movie) {
            $qualityScore = $movie->ratings_avg_rating ?? 0;
            $popularityScore = $movie->rentals_count ?? 0;
            $demographicsScore = $this->calculateDemographicsSimilarityScore($user, $movie);

            $recommendations[] = [
                'movie' => $movie,
                'quality_score' => $qualityScore,
                'popularity_score' => $popularityScore,
                'demographics_score' => $demographicsScore,
                'combined_score' => (
                    $qualityScore * 0.5 + 
                    min($popularityScore / 10, 5) * 0.3 + 
                    $demographicsScore * 0.2
                ),
                'confidence' => 0.6 // Medium confidence for fallback
            ];
        }

        // Sort by combined score and take top limit
        usort($recommendations, function($a, $b) {
            return $b['combined_score'] <=> $a['combined_score'];
        });
        return array_slice($recommendations, 0, $limit);
    }

    protected function calculateDemographicsSimilarityScore(User $user, Movie $movie): float
    {
        // This is a simplified demographic similarity score
        // In a real application, you might use more sophisticated demographic data
        return 0.5; // Neutral score for now
    }
}