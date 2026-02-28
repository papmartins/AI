<?php

namespace App\Http\Controllers;

use App\Services\NLPChatbotService;
use App\Services\IntentClassifierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class NLPChatbotController extends Controller
{
    public function __construct(protected NLPChatbotService $chatbotService, protected IntentClassifierService $intentClassifier)
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
    
    /**
     * Train the NLP model
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function train()
    {
        try {
            $modelPath = $this->getModelPath();
            
            // Force retraining by deleting any existing model
            if (File::exists($modelPath)) {
                File::delete($modelPath);
            }
            
            // This will trigger automatic retraining since the model file doesn't exist
            $estimator = $this->intentClassifier->loadOrTrainIntentionClassifier();
            
            Log::info('NLP model training completed successfully');
            
            return response()->json([
                'success' => true,
                'message' => 'Model training completed successfully',
                'model_path' => $modelPath
            ]);
            
        } catch (\Exception $e) {
            Log::error('NLP model training failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Model training failed: ' . $e->getMessage()
            ], 500);
        }
    }
}