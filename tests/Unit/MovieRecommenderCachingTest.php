<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Movie;
use App\Models\Rating;
use App\Services\MovieRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MovieRecommenderCachingTest extends TestCase
{
    use RefreshDatabase;

    protected MovieRecommender $recommender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recommender = new MovieRecommender();
    }

    public function test_frequent_user_caching()
    {
        // Create a frequent user (with 5+ interactions)
        $user = User::factory()->create();
        $movies = Movie::factory()->count(10)->create();
        
        // Add 5 ratings to make user "frequent"
        foreach ($movies->take(5) as $movie) {
            Rating::create([
                'user_id' => $user->id,
                'movie_id' => $movie->id,
                'rating' => 4
            ]);
        }

        // Clear any existing cache
        Cache::flush();

        // First call should not be cached
        $cacheKey = $this->recommender->getCacheKey($user, 6);
        $this->assertFalse(Cache::has($cacheKey));

        // Call recommendForUser
        $recommendations1 = $this->recommender->recommendForUser($user, 6);

        // Now cache should be populated
        $this->assertTrue(Cache::has($cacheKey));

        // Second call should return cached results
        $recommendations2 = $this->recommender->recommendForUser($user, 6);

        // Results should be identical
        $this->assertEquals($recommendations1, $recommendations2);
    }

    public function test_infrequent_user_no_caching()
    {
        // Create an infrequent user (with < 5 interactions)
        $user = User::factory()->create();
        $movies = Movie::factory()->count(3)->create();
        
        // Add only 2 ratings
        foreach ($movies->take(2) as $movie) {
            Rating::create([
                'user_id' => $user->id,
                'movie_id' => $movie->id,
                'rating' => 3
            ]);
        }

        // Clear any existing cache
        Cache::flush();

        // Call recommendForUser
        $this->recommender->recommendForUser($user, 6);

        // Cache should not be populated for infrequent users
        $cacheKey = $this->recommender->getCacheKey($user, 6);
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_cache_invalidation()
    {
        // Create a frequent user
        $user = User::factory()->create();
        $movies = Movie::factory()->count(10)->create();
        
        // Add 5 ratings
        foreach ($movies->take(5) as $movie) {
            Rating::create([
                'user_id' => $user->id,
                'movie_id' => $movie->id,
                'rating' => 4
            ]);
        }

        // Clear any existing cache
        Cache::flush();

        // Populate cache
        $this->recommender->recommendForUser($user, 6);
        $cacheKey = $this->recommender->getCacheKey($user, 6);
        $this->assertTrue(Cache::has($cacheKey));

        // Clear cache for user
        $this->recommender->clearUserCache($user);

        // Cache should be empty
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_cache_key_generation()
    {
        $user = User::factory()->create();
        
        $cacheKey1 = $this->recommender->getCacheKey($user, 6);
        $cacheKey2 = $this->recommender->getCacheKey($user, 4);
        $cacheKey3 = $this->recommender->getCacheKey($user, 6);

        // Different limits should produce different keys
        $this->assertNotEquals($cacheKey1, $cacheKey2);
        
        // Same parameters should produce same key
        $this->assertEquals($cacheKey1, $cacheKey3);
    }
}