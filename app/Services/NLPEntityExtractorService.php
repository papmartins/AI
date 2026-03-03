<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NLPEntityExtractorService
{
    protected $baseUrl;
    protected $timeout;
    
    public function __construct()
    {
        $this->baseUrl = config('nlp_service.nlp_service.url', 'http://localhost:8001');
        $this->timeout = config('nlp_service.nlp_service.timeout', 30);
    }
    
    /**
     * Extract entities from text
     *
     * @param string $text
     * @param string|null $language
     * @return array
     */
    public function extractEntities(string $text, ?string $language = null):array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/extract-entities", [
                    'text' => $text,
                    'language' => $language
                ]);
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('NLP Entity Extraction failed: ' . $response->body());
            return ['entities' => [], 'language' => 'en'];
            
        } catch (\Exception $e) {
            Log::error('NLP Entity Extraction error: ' . $e->getMessage());
            return ['entities' => [], 'language' => 'en'];
        }
    }
    
    /**
     * Detect language of text
     *
     * @param string $text
     * @return string
     */
    public function detectLanguage(string $text): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/detect-language", [
                    'text' => $text
                ]);
            
            if ($response->successful()) {
                return $response->json('language', 'en');
            }
            
            return 'en';
            
        } catch (\Exception $e) {
            Log::error('NLP Language Detection error: ' . $e->getMessage());
            return 'en';
        }
    }
}