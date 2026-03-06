<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MLMicroserviceClient
{
    protected $baseUrl;
    protected $timeout;
    protected $cacheTtl;

    public function __construct()
    {
        $this->baseUrl = config('ml.microservice_url', 'http://localhost:8001');
        $this->timeout = config('ml.microservice_timeout', 30);
        $this->cacheTtl = config('ml.microservice_cache_ttl', 3600);
    }

    /**
     * Classify intents from user question
     *
     * @param string $question User's question
     * @param string|null $userId Optional user identifier
     * @param string|null $language Optional language code
     * @return array Classification result
     */
    public function classifyIntents(string $question, ?string $userId = null): array
    {
        $cacheKey = 'ml_intents_' . md5($question . ($language ?? '') . ($userId ?? ''));
        
        // Try to get cached result
        // if (config('ml.cache.enabled', true)) {
        //     $cachedResult = Cache::get($cacheKey);
        //     if ($cachedResult) {
        //         return $cachedResult;
        //     }
        // }

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/classify-intent', [
                    'question' => $question,
                    'user_id' => $userId,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Cache the result
                if (config('ml.cache.enabled', true)) {
                    Cache::put($cacheKey, $result, $this->cacheTtl);
                }
                
                return $result;
            } else {
                Log::error('ML Microservice error: ' . $response->body());
                return $this->fallbackClassification($question);
            }
        } catch (\Exception $e) {
            Log::error('ML Microservice connection error: ' . $e->getMessage());
            return $this->fallbackClassification($question);
        }
    }

    /**
     * Full analysis of user question
     *
     * @param string $question User's question
     * @param string|null $userId Optional user identifier
     * @param string|null $language Optional language code
     * @return array Analysis result
     */
    public function analyzeQuestion(string $question, ?string $userId = null, ?string $language = null): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/analyze', [
                    'question' => $question,
                    'user_id' => $userId,
                    'language' => $language
                ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('ML Microservice analysis error: ' . $response->body());
                return [];
            }
        } catch (\Exception $e) {
            Log::error('ML Microservice analysis connection error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if microservice is available
     *
     * @return bool Service availability
     */
    public function checkServiceHealth(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Fallback classification when microservice is unavailable
     *
     * @param string $question User's question
     * @return array Fallback classification
     */
    protected function fallbackClassification(string $question): array
    {
        $lowerQuestion = strtolower($question);
        $intents = [];
        $entities = [];
        
        // Check for genre queries
        $genreKeywords = ['ação', 'comédia', 'drama', 'terror', 'romance', 'ficção', 
                         'aventura', 'suspense', 'animação', 'fantasia', 'documentário',
                         'action', 'comedy', 'horror', 'sci-fi', 'adventure', 'thriller',
                         'animation', 'fantasy', 'documentary', 'crime', 'mystery'];
        
        foreach ($genreKeywords as $genre) {
            if (str_contains($lowerQuestion, $genre)) {
                $intents[] = 'title'; // We're looking for movies (title) of this genre
                $entities['genre'] = [$genre];
                return [
                    'intents' => $intents,
                    'entities' => $entities,
                    'language' => str_contains($lowerQuestion, 'ação') || str_contains($lowerQuestion, 'gênero') ? 'pt' : 'en',
                    'confidence' => 0.7
                ];
            }
        }
        
        // Simple fallback logic for other intents
        if (str_contains($lowerQuestion, 'actor') || str_contains($lowerQuestion, 'ator')) {
            $intents[] = 'actor';
        }
        
        if (str_contains($lowerQuestion, 'director') || str_contains($lowerQuestion, 'diretor')) {
            $intents[] = 'director';
        }
        
        if (str_contains($lowerQuestion, 'genre') || str_contains($lowerQuestion, 'gênero')) {
            $intents[] = 'genre';
        }
        
        if (preg_match('/\d{4}/', $lowerQuestion)) {
            $intents[] = 'year';
        }
        
        if (empty($intents)) {
            // Default to title intent for movie searches
            $intents[] = 'title';
        }
        
        return [
            'intents' => $intents,
            'entities' => $entities,
            'language' => str_contains($lowerQuestion, 'ação') || str_contains($lowerQuestion, 'gênero') ? 'pt' : 'en',
            'confidence' => 0.5
        ];
    }

    /**
     * Set base URL for microservice
     *
     * @param string $url Base URL
     */
    public function setBaseUrl(string $url): void
    {
        $this->baseUrl = $url;
    }

    /**
     * Set timeout for HTTP requests
     *
     * @param int $timeout Timeout in seconds
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }
}