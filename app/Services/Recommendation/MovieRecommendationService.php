<?php

namespace App\Services\Recommendation;

use App\Models\User;
use App\Models\Movie;
use App\Models\Rating;
use App\Models\Rental;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MovieRecommendationService
{
    protected MLRecommendationService $mlService;
    protected PopularityRecommendationService $popularityService;

    public function __construct()
    {
        $this->mlService = new MLRecommendationService();
        $this->popularityService = new PopularityRecommendationService();
    }

    /**
     * Get personalized movie recommendations for a user
     */
    public function recommendForUser(User $user, int $limit = 6): array
    {
        // Check if user is frequent and caching is beneficial
        $cacheKey = $this->getCacheKey($user, $limit);
        $isFrequent = $this->isFrequentUser($user);
        
        Log::info('Recommendation request', [
            'user_id' => $user->id,
            'is_frequent' => $isFrequent,
            'cache_key' => $cacheKey,
            'cache_exists' => Cache::has($cacheKey)
        ]);
        
        // Try to get cached recommendations for frequent users
        if ($isFrequent && Cache::has($cacheKey)) {
            Log::info('Returning cached recommendations for user ' . $user->id);
            return Cache::get($cacheKey);
        }
        
        try {
            Log::info('Generating new recommendations for user ' . $user->id);
            // Use ML model for predictions
            $recommendations = $this->mlService->getMLBasedRecommendations($user, $limit);
            
            // If ML recommendations are insufficient, fall back to popularity-based
            if (empty($recommendations)) {
                Log::info('ML recommendations empty, falling back to popularity-based for user ' . $user->id);
                $recommendations = $this->popularityService->getFallbackRecommendations($user, $limit);
            }
            
            // Cache recommendations for frequent users (30 minutes)
            if ($isFrequent) {
                Cache::put($cacheKey, $recommendations, now()->addMinutes(30));
                Log::info('Cached recommendations for user ' . $user->id);
            }
            
            return $recommendations;
            
        } catch (\Exception $e) {
            Log::error('ML recommendation failed, falling back to popularity-based: ' . $e->getMessage());
            
            // Fallback to popularity-based recommendations
            $recommendations = $this->popularityService->getFallbackRecommendations($user, $limit);
            
            // Cache fallback recommendations for 10 minutes to avoid repeated failures
            if ($isFrequent) {
                Cache::put($cacheKey, $recommendations, now()->addMinutes(10));
            }
            
            return $recommendations;
        }
    }

    /**
     * Get global popular recommendations (not personalized)
     */
    public function getPopularRecommendations(int $limit = 6): array
    {
        return $this->popularityService->getPopularRecommendations($limit);
    }

    /**
     * Clear cache for a specific user
     */
    public function clearUserCache(User $user): void
    {
        $cacheKey = $this->getCacheKey($user, 6); // Clear cache for default limit
        Cache::forget($cacheKey);
        Log::info('Cleared recommendation cache for user ' . $user->id);
    }

    /**
     * Clear all recommendation caches
     */
    public function clearAllCaches(): void
    {
        Cache::forget('popular_recommendations_6');
        Cache::forget('precalculated_popularity_data_v2');
        Cache::forget('precalculated_movie_features_v2');
        
        // Clear user-specific caches (this would need to be more comprehensive in production)
        Cache::forget('user_recommendations_*');
        
        Log::info('Cleared all recommendation caches');
    }

    /**
     * Pre-calculate and cache movie features and popularity data
     */
    public function precalculateAndCacheData(): void
    {
        Log::info('Starting pre-calculation of recommendation data');
        
        // Cache popularity confidence
        $this->popularityService->cachePopularityConfidence();
        
        Log::info('Pre-calculation completed');
    }

    /**
     * Retrain the ML model
     */
    public function retrainMLModel(): array
    {
        return $this->mlService->retrain();
    }

    /**
     * Get information about the ML model
     */
    public function getModelInfo(): array
    {
        return $this->mlService->getModelInfo();
    }

    /**
     * Get cache key for user recommendations
     */
    public function getCacheKey(User $user, int $limit): string
    {
        return 'user_recommendations_' . $user->id . '_' . $limit;
    }

    /**
     * Check if user is frequent (has enough interactions to benefit from caching)
     */
    protected function isFrequentUser(User $user): bool
    {
        $ratingCount = $user->ratings()->count();
        $rentalCount = $user->rentals()->count();
        $wishlistCount = $user->wishlist()->count();
        
        // User is considered frequent if they have at least 5 interactions
        return ($ratingCount + $rentalCount + $wishlistCount) >= 5;
    }
}