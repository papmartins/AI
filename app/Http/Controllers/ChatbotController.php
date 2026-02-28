<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatbotController extends Controller
{
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
}