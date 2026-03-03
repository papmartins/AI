<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpParser\Node\Scalar\String_;

/**
 * Enhanced Intent Classifier Service
 * Communicates with Python NLP microservice for robust intent classification
 * Falls back to local rules if service unavailable
 */
class NLPIntentClassifierService
{
    protected $nlpServiceUrl;
    protected $fallbackClassifier;
    protected $cacheExpiry = 3600; // 1 hour cache
    protected $supportedLanguages = ['pt', 'en', 'es'];
    protected $requestTimeout = 10; // seconds

    public function __construct()
    {
        $this->nlpServiceUrl = config('services.nlp_service.url') ?? 'http://nlp-service:8001';
        $this->fallbackClassifier = new FallbackIntentClassifier();
    }

    /**
     * Classify multiple intents (compound questions)
     * 
     * @param string $question User's question
     * @return array Detected intents with condition type and snippets
     */
    public function classifyMultipleIntents(string $question, ?string $language = null): array
    {
        $cacheKey = "multi_intents:" . md5($question);
        
        // Try cache first
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            // Call microservice
            $response = Http::timeout($this->requestTimeout)
                ->post("{$this->nlpServiceUrl}/classify-multiple-intents", [
                    'question' => $question,
                    'language' => $language
                ]);

            if ($response->successful()) {
                $result = [
                    'intents' => $response->json('intents', ['unknown']),
                    'snippets' => [],
                    'condition' => $response->json('condition', 'AND'),
                    'source' => 'nlp_service'
                ];
                
                Cache::put($cacheKey, $result, $this->cacheExpiry);
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning("NLP service failed for multi-intent: {$e->getMessage()}");
        }

        // Fallback to local rules
        return $this->fallbackClassifier->classifyMultipleIntents($question);
    }

    /**
     * Classify single intent using ML microservice
     * 
     * @param string $question User's question
     * @return string Detected intention
     */
    public function classifyIntention(string $question): string
    {
        $cacheKey = "intent:" . md5($question);
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout($this->requestTimeout)
                ->post("{$this->nlpServiceUrl}/classify-intent", [
                    'question' => $question
                ]);

            if ($response->successful()) {
                $intent = $response->json('intent', 'unknown');
                Cache::put($cacheKey, $intent, $this->cacheExpiry);
                return $intent;
            }
        } catch (\Exception $e) {
            Log::warning("NLP service failed for intent: {$e->getMessage()}");
        }

        // Fallback
        return $this->fallbackClassifier->classifyIntention($question);
    }

    /**
     * Detect language of text
     * 
     * @param string $question User's question
     * @return string Language code (pt, en, es)
     */
    public function detectLanguage(string $question): string
    {
        $cacheKey = "lang:" . md5($question);
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout($this->requestTimeout)
                ->post("{$this->nlpServiceUrl}/detect-language", [
                    'text' => $question
                ]);

            if ($response->successful()) {
                $language = $response->json('language', 'en');
                Cache::put($cacheKey, $language, $this->cacheExpiry);
                return $language;
            }
        } catch (\Exception $e) {
            Log::warning("NLP service failed for language detection: {$e->getMessage()}");
        }

        return $this->fallbackClassifier->detectLanguage($question);
    }

    /**
     * Extract entities from text
     * Uses transformer-based NER
     * 
     * @param string $text Text to extract entities from
     * @param string|null $language Language hint
     * @return array List of extracted entities
     */
    public function extractEntities(string $text, ?string $language = null): array
    {
        $cacheKey = "entities:" . md5($text) . ":" . ($language ?? 'auto');
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout($this->requestTimeout)
                ->post("{$this->nlpServiceUrl}/extract-entities", [
                    'text' => $text,
                    'language' => $language
                ]);

            if ($response->successful()) {
                $entities = $response->json('entities', []);
                Cache::put($cacheKey, $entities, $this->cacheExpiry);
                return $entities;
            }
        } catch (\Exception $e) {
            Log::warning("NLP service failed for entity extraction: {$e->getMessage()}");
        }

        return [];
    }

    /**
     * Semantic search for movie titles
     * Handles new/unknown titles through similarity matching
     * 
     * @param string $query Search query (e.g., "Die Hard")
     * @param array $items List of movie items to search in
     * @param int $topK Number of results
     * @return array Ranked results with similarity scores
     */
    public function semanticSearch(string $query, array $items, int $topK = 5): array
    {
        if (empty($items)) {
            return [];
        }

        $cacheKey = "semantic_search:" . md5($query . json_encode($items));
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout($this->requestTimeout)
                ->post("{$this->nlpServiceUrl}/semantic-search", [
                    'query' => $query,
                    'items' => $items,
                    'top_k' => $topK
                ]);

            if ($response->successful()) {
                $results = [
                    'results' => $response->json('results', []),
                    'similarities' => $response->json('similarities', [])
                ];
                Cache::put($cacheKey, $results, $this->cacheExpiry);
                return $results;
            }
        } catch (\Exception $e) {
            Log::warning("NLP service failed for semantic search: {$e->getMessage()}");
        }

        // Fallback: simple fuzzy matching
        return $this->fallbackClassifier->fuzzySearchMovies($query, $items, $topK);
    }

    /**
     * Check NLP service health
     * 
     * @return bool
     */
    public function isServiceHealthy(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->nlpServiceUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("NLP service health check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get model path (for compatibility)
     */
    public function getModelPath(): string
    {
        return $this->nlpServiceUrl;
    }

    /**
     * Retrain classifier (triggers remote retraining)
     */
    public function retrain(): array
    {
        try {
            $response = Http::post("{$this->nlpServiceUrl}/retrain");
            
            if ($response->successful()) {
                Cache::flush(); // Clear all cached classifications
                return [
                    'success' => true,
                    'message' => 'NLP model retrained via microservice'
                ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrain NLP model: {$e->getMessage()}");
        }

        return [
            'success' => false,
            'message' => 'Failed to retrain NLP model'
        ];
    }
}
