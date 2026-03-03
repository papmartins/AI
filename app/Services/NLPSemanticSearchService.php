<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NLPSemanticSearchService
{
    protected $baseUrl;
    protected $timeout;
    
    public function __construct()
    {
        $this->baseUrl = config('nlp_service.nlp_service.url', 'http://localhost:8001');
        $this->timeout = config('nlp_service.nlp_service.timeout', 30);
    }
    
    /**
     * Perform semantic search
     *
     * @param string $query
     * @param array $items
     * @param string|null $language
     * @param int $topK
     * @return array
     */
    public function semanticSearch(string $query, array $items, ?string $language = null, int $topK = 5): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/semantic-search", [
                    'query' => $query,
                    'items' => $items,
                    'language' => $language,
                    'top_k' => $topK
                ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('NLP Semantic Search failed: ' . $response->body());
            return ['results' => [], 'similarities' => []];
            
        } catch (\Exception $e) {
            Log::error('NLP Semantic Search error: ' . $e->getMessage());
            return ['results' => [], 'similarities' => []];
        }
    }
    
    /**
     * Generate text embeddings
     *
     * @param string $text
     * @param string|null $language
     * @return array
     */
    public function embedText(string $text, ?string $language = null): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/embed-text", [
                    'text' => $text,
                    'language' => $language
                ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('NLP Text Embedding failed: ' . $response->body());
            return ['embedding' => [], 'dimension' => 0];
            
        } catch (\Exception $e) {
            Log::error('NLP Text Embedding error: ' . $e->getMessage());
            return ['embedding' => [], 'dimension' => 0];
        }
    }
}