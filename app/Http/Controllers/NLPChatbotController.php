<?php

namespace App\Http\Controllers;

use App\Services\NLPChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class NLPChatbotController extends Controller
{
    public function __construct(protected NLPChatbotService $chatbotService)
    {
    }
    
    /**
     * Show the chatbot page with example questions
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        // Get the current locale from session or request
        $locale = $request->session()->get('locale') ?? app()->getLocale();
        
        // Load examples from the language files
        $examples = $this->loadChatbotExamples($locale);
        
        return Inertia::render('Chatbot', [
            'examples' => $examples
        ]);
    }
    
    /**
     * Load chatbot examples from language files
     *
     * @param string $locale
     * @return array
     */
    protected function loadChatbotExamples(string $locale): array
    {
        // Try to load from the language file
        $examples = trans('chatbot/questions.suggestions', [], $locale);
        
        // If no examples found in the specified locale, fall back to Portuguese
        if (empty($examples)) {
            $examples = trans('chatbot/questions.suggestions', [], 'pt');
        }
        
        // Ensure we always return an array
        return is_array($examples) ? $examples : [];
    }
    
    public function chat(Request $request)
    {
        $requestArray = $request->json()->all();
        $question = $requestArray['question'] ?? null;
        $userId = $request->user()?->id ?? 'guest_' . uniqid();
        
        // Get locale from header or query parameter (simple and reliable)
        $locale = $request->header('X-Locale') ?? $request->input('lang') ?? 'pt';
        
        // Set the locale for this request
        app()->setLocale($locale);
        
        if (empty($question)) {
            return response()->json([
                'response' => __('chatbot/responses.unknown_question')
            ]);
        }
        
        // Processar a pergunta usando o microserviço NLP (caixa negra)
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
    
    /**
     * Get the NLP model file path
     *
     * @return string
     */
    protected function getModelPath(): string
    {
        return storage_path(config('ml.nlp.model_path', 'app/nlp_intention_classifier.model'));
    }
    
    /**
     * Check if the NLP model file exists
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkModel()
    {
        $modelPath = $this->getModelPath();
        return response()->json([
            'exists' => File::exists($modelPath),
            'path' => $modelPath
        ]);
    }
    
    /**
     * Delete the existing NLP model file
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteModel()
    {
        $modelPath = $this->getModelPath();
        
        if (File::exists($modelPath)) {
            try {
                File::delete($modelPath);
                Log::info('Deleted existing NLP model file');
                return response()->json([
                    'success' => true,
                    'message' => 'Existing model deleted successfully'
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to delete NLP model: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete model: ' . $e->getMessage()
                ], 500);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'No existing model to delete'
        ]);
    }
    
}