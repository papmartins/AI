<?php

namespace App\Http\Controllers;

use App\Services\NLPChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class NLPChatbotController extends Controller
{
    public function __construct(protected NLPChatbotService $chatbotService)
    {
    }
    
    public function chat(Request $request)
    {
        $requestArray = $request->json()->all();
        $question = $requestArray['question'] ?? null;
        $userId = $request->user()?->id ?? 'guest_' . uniqid();
        
        if (empty($question)) {
            return response()->json([
                'response' => 'Por favor, faça uma pergunta sobre filmes.'
            ]);
        }
        
        // Processar a pergunta usando NLP com contexto de usuário
        $response = $this->chatbotService->processQuestion($question, $userId);
        
        return response()->json([
            'question' => $question,
            'response' => $response
        ]);
    }
    
    /**
     * Get example questions/suggestions for the chatbot
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSuggestions(Request $request)
    {
        // Get locale from headers (sent by frontend) or from the application
        $locale = $request->header('X-Locale') ?? app()->getLocale();
        
        // Load suggestions from language files
        $suggestions = trans('chatbot/questions.suggestions', [], $locale);
        
        // Fallback to Portuguese if suggestions not found in current locale
        if (empty($suggestions) || $suggestions === 'chatbot/questions.suggestions') {
            $suggestions = trans('chatbot/questions.suggestions', [], 'pt');
        }
        
        return response()->json([
            'suggestions' => $suggestions
        ]);
    }
    
}