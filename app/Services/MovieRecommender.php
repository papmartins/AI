<?php

namespace App\Services;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Extractors\CSV;


use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;


use Rubix\ML\Regressors\KNNRegressor;
use Rubix\ML\Kernels\Distance\Cosine;
use App\Models\User;
use App\Models\Movie;
use App\Models\Rating;
use App\Models\Rental;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class MovieRecommender
{
    protected string $modelPath;
    protected string $datasetPath;
    
    public function __construct()
    {
        $this->modelPath = storage_path('app/movie_recommender.model');
        $this->datasetPath = storage_path('app/movie_recommendations.csv');
    }
    
    /**
     * Prepare dataset from user interactions
     */
    protected function prepareDataset(): void
    {
        // Get all users and movies
        $users = User::all();
        $movies = Movie::all();
        
        $csvData = [];
        $csvData[] = ['user_id', 'movie_id', 'rating', 'rented', 'wishlisted', 'genre_id', 'movie_year'];
        
        foreach ($users as $user) {
            foreach ($movies as $movie) {
                // Get user's rating for this movie
                $rating = Rating::where('user_id', $user->id)
                    ->where('movie_id', $movie->id)
                    ->first();
                
                // Check if user rented this movie
                $rented = Rental::where('user_id', $user->id)
                    ->where('movie_id', $movie->id)
                    ->exists();
                
                // Check if movie is in user's wishlist
                $wishlisted = Wishlist::where('user_id', $user->id)
                    ->where('movie_id', $movie->id)
                    ->exists();
                
                $csvData[] = [
                    $user->id,
                    $movie->id,
                    $rating ? $rating->rating : 0,
                    $rented ? 1 : 0,
                    $wishlisted ? 1 : 0,
                    $movie->genre_id,
                    $movie->year
                ];
            }
        }
        
        // Write to CSV
        $this->writeCSV($csvData);
    }
    
    /**
     * Write data to CSV file
     */
    protected function writeCSV(array $data): void
    {
        $handle = fopen($this->datasetPath, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
    
    /**
     * Train the recommendation model
     */
    protected function train(): PersistentModel
    {
        $this->prepareDataset();
        
        $dataset = Labeled::fromIterator(
            new CSV($this->datasetPath, true),
            2 // rating column as target
        );
        
        // Use KNN for collaborative filtering
        $estimator = new PersistentModel(
            new KNNRegressor(10, true, new Cosine()), // 10 neighbors, weighted, cosine distance
            new Filesystem($this->modelPath)
        );
        
        $estimator->train($dataset);
        $estimator->save();
        
        return $estimator;
    }
    
    /**
     * Load existing model or train new one
     */
    protected function loadOrTrain(): PersistentModel
    {
        if (file_exists($this->modelPath)) {
            $estimator = PersistentModel::load(new Filesystem($this->modelPath));
        } else {
            $estimator = $this->train();
        }
        
        return $estimator;
    }
    
    /**
     * Get personalized movie recommendations for a user using quality, popularity and demographics
     */
    public function recommendForUser(User $user, int $limit = 6): array
    {
        // Check if user is frequent and caching is beneficial
        $cacheKey = $this->getCacheKey($user, $limit);
        $isFrequent = $this->isFrequentUser($user);
        
        // Try to get cached recommendations for frequent users
        if ($isFrequent && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            // Use the new recommendation algorithm based on quality, popularity and demographics
            $recommendations = $this->getQualityPopularityDemographicsRecommendations($user, $limit);
            
            // Cache recommendations for frequent users (30 minutes)
            if ($isFrequent) {
                Cache::put($cacheKey, $recommendations, now()->addMinutes(30));
            }
            
            return $recommendations;
            
        } catch (\Exception $e) {
            // Fallback to popularity-based recommendations if new algorithm fails
            $fallbackRecommendations = $this->getFallbackRecommendations($user, $limit);
            
            // Cache fallback recommendations for frequent users (shorter duration)
            if ($isFrequent) {
                Cache::put($cacheKey, $fallbackRecommendations, now()->addMinutes(15));
            }
            
            return $fallbackRecommendations;
        }
    }

    /**
     * Get recommendations based on movie quality (rating), popularity (rentals), and user demographics
     */
    protected function getQualityPopularityDemographicsRecommendations(User $user, int $limit = 6): array
    {
        // Get movies user has already interacted with
        $interactedMovieIds = Rating::where('user_id', $user->id)
            ->pluck('movie_id')
            ->merge(Rental::where('user_id', $user->id)->pluck('movie_id'))
            ->merge(Wishlist::where('user_id', $user->id)->pluck('movie_id'))
            ->unique();
        
        // Get all movies with their metrics, excluding interacted ones
        $movies = Movie::with(['genre', 'ratings', 'rentals'])
            ->withAvg('ratings', 'rating')
            ->withCount(['ratings', 'rentals'])
            ->whereNotIn('id', $interactedMovieIds)
            ->get();
        
        // Calculate recommendation scores for each movie
        $scoredMovies = [];
        foreach ($movies as $movie) {
            $qualityScore = $movie->ratings_avg_rating ?? 0;
            $popularityScore = $movie->rentals_count ?? 0;
            $demographicsScore = $this->calculateDemographicsSimilarityScore($user, $movie);
            
            // Combine scores with weights: 40% quality, 30% popularity, 30% demographics
            $combinedScore = (
                $qualityScore * 0.4 + 
                min($popularityScore / 10, 5) * 0.3 + // Normalize popularity
                $demographicsScore * 0.3
            );
            
            $scoredMovies[] = [
                'movie' => $movie,
                'quality_score' => $qualityScore,
                'popularity_score' => $popularityScore,
                'demographics_score' => $demographicsScore,
                'combined_score' => $combinedScore,
                'confidence' => $this->calculateConfidenceFromScores($qualityScore, $popularityScore, $demographicsScore)
            ];
        }
        
        // Sort by combined score (descending)
        usort($scoredMovies, function($a, $b) {
            return $b['combined_score'] <=> $a['combined_score'];
        });
        
        return array_slice($scoredMovies, 0, $limit);
    }

    /**
     * Calculate demographics similarity score between user and movie's typical audience
     */
    protected function calculateDemographicsSimilarityScore(User $user, Movie $movie): float
    {
        // Get users who rated this movie highly (4-5 stars)
        $similarUsers = Rating::where('movie_id', $movie->id)
            ->where('rating', '>=', 4)
            ->with('user')
            ->get()
            ->pluck('user');
        
        if ($similarUsers->isEmpty()) {
            return 0.5; // Neutral score if no data
        }
        
        // Calculate age similarity (if user has age data)
        $ageSimilarity = 0.5; // Default neutral
        if ($user->age) {
            $ageDiff = $similarUsers->map(function($similarUser) use ($user) {
                return $similarUser->age ? abs($user->age - $similarUser->age) : 100;
            })->avg();
            
            // Convert age difference to similarity score (0-1)
            $ageSimilarity = max(0, 1 - min($ageDiff / 20, 1)); // 20 years difference = 0 similarity
        }
        
        // Calculate gender similarity
        $genderSimilarity = 0.5; // Default neutral
        if ($user->gender) {
            $sameGenderCount = $similarUsers->filter(function($similarUser) use ($user) {
                return $similarUser->gender && strtolower($similarUser->gender) === strtolower($user->gender);
            })->count();
            
            $genderSimilarity = $sameGenderCount / max(1, $similarUsers->count());
        }
        
        // Combine age and gender similarity
        return ($ageSimilarity * 0.5 + $genderSimilarity * 0.5);
    }

    /**
     * Calculate confidence from quality, popularity and demographics scores
     */
    protected function calculateConfidenceFromScores(float $quality, float $popularity, float $demographics): float
    {
        // Normalize scores to 0-1 range
        $normalizedQuality = min($quality / 5, 1);
        $normalizedPopularity = min($popularity / 20, 1); // Assume 20 rentals = max popularity
        
        // Combine with weights
        $confidence = (
            $normalizedQuality * 0.4 +
            $normalizedPopularity * 0.3 +
            $demographics * 0.3
        );
        
        return min(1.0, max(0.1, $confidence));
    }
    
    /**
     * Get cache key for user recommendations
     */
    protected function getCacheKey(User $user, int $limit): string
    {
        return 'movie_recommendations_user_' . $user->id . '_limit_' . $limit;
    }

    /**
     * Check if user is a frequent user (has enough interactions for caching)
     */
    protected function isFrequentUser(User $user): bool
    {
        $interactionCount = Rating::where('user_id', $user->id)->count() +
                           Rental::where('user_id', $user->id)->count() +
                           Wishlist::where('user_id', $user->id)->count();
        
        return $interactionCount >= 5; // Consider user frequent if they have 5+ interactions
    }

    /**
     * Calculate confidence score for recommendation
     */
    protected function calculateConfidence(User $user, Movie $movie): float
    {
        // Simple confidence based on genre popularity and user's past ratings
        $userGenreRatings = Rating::where('user_id', $user->id)
            ->whereHas('movie', function($query) use ($movie) {
                $query->where('genre_id', $movie->genre_id);
            })
            ->avg('rating') ?? 0;
        
        $genreConfidence = $userGenreRatings / 5.0; // Normalize to 0-1 range
        
        // Add small random factor to avoid ties
        return min(1.0, $genreConfidence + (rand(0, 10) / 100));
    }
    
    /**
     * Fallback recommendations when ML is not available
     */
    protected function getFallbackRecommendations(User $user, int $limit = 6): array
    {
        // Get movies user has already interacted with
        $interactedMovieIds = Rating::where('user_id', $user->id)
            ->pluck('movie_id')
            ->merge(Rental::where('user_id', $user->id)->pluck('movie_id'))
            ->merge(Wishlist::where('user_id', $user->id)->pluck('movie_id'))
            ->unique();
        
        // Get top rated movies from genres the user likes
        $userGenres = Rating::where('user_id', $user->id)
            ->with('movie.genre')
            ->get()
            ->pluck('movie.genre.id')
            ->unique();
        
        if ($userGenres->isEmpty()) {
            // If no ratings, use popular genres
            $userGenres = [1, 2, 3]; // Default genres
        }
        
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
    
    /**
     * Get global popular recommendations (not personalized)
     */
    public function getPopularRecommendations(int $limit = 6): array
    {
        return Movie::with('genre')
            ->withAvg('ratings', 'rating')
            ->withCount('rentals')
            ->having('ratings_avg_rating', '>', 0)
            ->orderBy('ratings_avg_rating', 'desc')
            ->orderBy('rentals_count', 'desc')
            ->take($limit)
            ->get()
            ->map(function($movie) {
                return [
                    'movie' => $movie,
                    'predicted_rating' => $movie->ratings_avg_rating ?? 0,
                    'confidence' => 0.8 // High confidence for popular items
                ];
            })
            ->toArray();
    }
    
    /**
     * Clear cache for a specific user
     */
    public function clearUserCache(User $user): void
    {
        // Clear all cache keys for this user (different limits)
        for ($limit = 1; $limit <= 12; $limit++) {
            $cacheKey = $this->getCacheKey($user, $limit);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Clear cache for all users (useful after model retraining)
     */
    public function clearAllCaches(): void
    {
        // Get all users and clear their caches
        $users = User::all();
        foreach ($users as $user) {
            $this->clearUserCache($user);
        }
    }

    /**
     * Retrain the model
     */
    public function retrain(): array
    {
        $startTime = microtime(true);
        $estimator = $this->train();
        $endTime = microtime(true);
        
        // Clear all caches after retraining since recommendations may change
        $this->clearAllCaches();
        
        return [
            'status' => 'success',
            'training_time' => round($endTime - $startTime, 2),
            'model_saved' => true,
            'model_path' => $this->modelPath,
            'caches_cleared' => true
        ];
    }
}