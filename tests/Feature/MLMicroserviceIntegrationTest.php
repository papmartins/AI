<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\MLMicroserviceClient;

class MLMicroserviceIntegrationTest extends TestCase
{
    /**
     * Test ML microservice connectivity
     * @return void
     */
    public function test_microservice_connectivity()
    {
        $mlClient = $this->app->make("App\\Services\\MLMicroserviceClient");
        
        // Test basic connectivity with a simple question
        $result = $mlClient->classifyIntents("filmes de ação");
        
        // Verify response structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('intents', $result);
        $this->assertArrayHasKey('entities', $result);
        $this->assertArrayHasKey('language', $result);
        $this->assertArrayHasKey('confidence', $result);
        
        // Verify field types
        $this->assertIsArray($result['intents']);
        $this->assertIsArray($result['entities']);
        $this->assertIsString($result['language']);
        $this->assertIsFloat($result['confidence']);
        
        // Verify confidence is in valid range
        $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);
        
        // Verify language detection works
        $this->assertContains($result['language'], ['pt', 'en', 'es']);
    }

    /**
     * Test genre detection for multiple languages
     * @return void
     */
    public function test_genre_detection_multiple_languages()
    {
        $mlClient = $this->app->make("App\\Services\\MLMicroserviceClient");
        
        // Wait briefly for service to be ready (service should already be running in CI)
        $maxAttempts = 3;
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            if ($mlClient->checkServiceHealth()) {
                break;
            }
            $attempt++;
            sleep(1);
        }
        
        // Test Portuguese
        $result = $mlClient->classifyIntents("filmes de comédia");
        
        $this->assertEquals('pt', $result['language']);
        $this->assertContains('title', $result['intents']);
        
        // Test English
        $result = $mlClient->classifyIntents("movies of action");
        $this->assertEquals('en', $result['language']);
        $this->assertContains('title', $result['intents']);
        
        // Test Spanish
        $result = $mlClient->classifyIntents("películas de terror");
        $this->assertEquals('es', $result['language']);
        $this->assertContains('title', $result['intents']);
    }

    /**
     * Test genre question detection
     * @return void
     */
    public function test_genre_question_detection()
    {
        $mlClient = $this->app->make("App\\Services\\MLMicroserviceClient");
        
        // Test Portuguese genre question
        $result = $mlClient->classifyIntents("Que gêneros de filmes existem?");
        $this->assertEquals('pt', $result['language']);
        $this->assertContains('genre', $result['intents']);
        
        // Test English genre question
        $result = $mlClient->classifyIntents("What movie genres exist?");
        $this->assertEquals('en', $result['language']);
        $this->assertContains('genre', $result['intents']);
    }

    /**
     * Test actor and director detection
     * @return void
     */
    public function test_actor_director_detection()
    {
        $mlClient = $this->app->make("App\\Services\\MLMicroserviceClient");
        
        // Wait briefly for service to be ready (service should already be running in CI)
        $maxAttempts = 3;
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            if ($mlClient->checkServiceHealth()) {
                break;
            }
            $attempt++;
            sleep(1);
        }
        
        // Test actor detection
        $result = $mlClient->classifyIntents("Filmes com Brad Pitt");
        $this->assertEquals('pt', $result['language']);
        $this->assertArrayHasKey('actor', $result['entities']);
        
        // Test director detection
        $result = $mlClient->classifyIntents("Filmes dirigidos por Nolan");
        $this->assertEquals('pt', $result['language']);
        $this->assertArrayHasKey('director', $result['entities']);
    }

    /**
     * Test error handling
     * @return void
     */
    public function test_error_handling()
    {
        $mlClient = $this->app->make("App\\Services\\MLMicroserviceClient");
        
        // Test empty question
        $result = $mlClient->classifyIntents("");
        $this->assertIsArray($result);
        $this->assertArrayHasKey('intents', $result);
        
        // Test very long question
        $longQuestion = str_repeat("filmes ", 50);
        $result = $mlClient->classifyIntents($longQuestion);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('intents', $result);
    }

    /**
     * Test response time performance
     * @return void
     */
    public function test_response_time()
    {
        $mlClient = $this->app->make("App\\Services\\MLMicroserviceClient");
        
        $startTime = microtime(true);
        $result = $mlClient->classifyIntents("filmes de ação");
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        
        // Response should be reasonably fast (under 5 seconds)
        $this->assertLessThan(5.0, $responseTime);
        
        // Should still get valid results
        $this->assertIsArray($result);
        $this->assertArrayHasKey('intents', $result);
    }
}