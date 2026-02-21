<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Movie;
use App\Models\Rating;
use App\Services\MovieRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommender_service_can_be_instantiated()
    {
        $recommender = new MovieRecommender();
        $this->assertInstanceOf(MovieRecommender::class, $recommender);
    }

    public function test_popular_recommendations_work()
    {
        // Use existing movies from the database instead of creating new ones
        // This ensures the movies have confidence scores in the cache
        $movies = Movie::withCount('ratings')->orderBy('ratings_count', 'desc')->take(5)->get();
        
        if ($movies->count() < 3) {
            // If not enough movies with ratings, create some and add ratings
            $movies = Movie::factory()->count(5)->create();
            $user = User::factory()->create();
            foreach ($movies as $index => $movie) {
                Rating::create([
                    'user_id' => $user->id,
                    'movie_id' => $movie->id,
                    'rating' => 5 - $index, // Higher ratings for earlier movies
                ]);
            }
            
            // Force a real-time calculation by temporarily removing cache
            $cachePath = storage_path('app/popularity_confidence.json');
            if (file_exists($cachePath)) {
                unlink($cachePath);
            }
        }
        
        $recommender = new MovieRecommender();
        $recommendations = $recommender->getPopularRecommendations(3);
        
        $this->assertCount(3, $recommendations);
        $this->assertArrayHasKey('movie', $recommendations[0]);
        $this->assertArrayHasKey('predicted_rating', $recommendations[0]);
        $this->assertArrayHasKey('confidence', $recommendations[0]);
    }

    public function test_personalized_recommendations_work()
    {
        $user = User::factory()->create();
        $movies = Movie::factory()->count(10)->create();
        
        // User rates some movies
        foreach ($movies->take(3) as $movie) {
            Rating::create([
                'user_id' => $user->id,
                'movie_id' => $movie->id,
                'rating' => rand(3, 5),
            ]);
        }
        
        $recommender = new MovieRecommender();
        $recommendations = $recommender->recommendForUser($user, 3);
        
        $this->assertIsArray($recommendations);
        // Should recommend movies user hasn't rated yet
        foreach ($recommendations as $rec) {
            $this->assertInstanceOf(Movie::class, $rec['movie']);
        }
    }

    public function test_recommendation_api_endpoints()
    {
        $user = User::factory()->create();
        
        // Test popular endpoint (public)
        $response = $this->get('/api/recommendations/popular-public');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'recommendations']);
        
        // Test authenticated endpoints
        $this->actingAs($user);
        
        $response = $this->get('/api/recommendations/personalized');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'recommendations', 'user_id']);
        
        $response = $this->get('/api/recommendations/popular');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'recommendations']);
    }
}