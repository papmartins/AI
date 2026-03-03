<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Rubix\ML\Tokenizers\Word;

use function __;

class NLPChatbotService
{
    protected $nlpServiceUrl;
    protected $cacheExpiry = 3600; // 1 hour cache
    protected $requestTimeout = 30; // seconds

    public function __construct()
    {
        $this->nlpServiceUrl = config('services.nlp_service.url') ?? 'http://nlp-service:8001';
    }

    /**
     * Process question using NLP microservice (black box approach)
     * All NLP logic is handled by the microservice
     */
    public function processQuestion(string $question, ?string $userId = null): string
    {
        try {
            $result = [];
            $response = Http::timeout($this->requestTimeout)
                ->post("{$this->nlpServiceUrl}/extract-entities", [
                    'text' => $question,
                    'language' => app()->getLocale()
                ]);

            if ($response->successful()) {
                $result['entities'] = $response->json()['entities'] ?? [];
                $result['conjunction'] = $response->json()['conjunction'] ?? null;
                $result['language'] = $response->json()['language'] ?? null;
            } else {
                $errorBody = $response->body();
                Log::warning("NLP microservice returned error: " . $response->status() . " - " . $errorBody);
                
                // Handle connection errors
                $result['entities'] = $errorBody;
            }
            $response = Http::timeout($this->requestTimeout)
                ->post("{$this->nlpServiceUrl}/classify-multiple-intents", [
                    'question' => $question,
                    'language' => app()->getLocale()
                ]);

            if ($response->successful()) {
                $result['intents'] = $response->json()['intents'] ?? [];
            } else {
                $errorBody = $response->body();
                Log::warning("NLP microservice returned error: " . $response->status() . " - " . $errorBody);
                
                // Handle connection errors
                $result['intents'] = $errorBody;
            }
            
            $tokenizer = new Word();
            $tokens = $tokenizer->tokenize(strtolower($question));
                dd($result, $tokens);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("NLP microservice connection error: " . $e->getMessage());
            $result = __("chatbot/responses.processing_error", [], app()->getLocale());
            return response()->json($result);
        }

    }
 
    /**
     * Check if NLP service is healthy
     */
    public function isServiceHealthy(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->nlpServiceUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}