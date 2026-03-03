<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\NLPEntityExtractorService;
use App\Services\NLPSemanticSearchService;
use App\Services\IntentClassifierService;

class NLPController extends Controller
{
    protected $entityExtractor;
    protected $semanticSearch;
    protected $intentClassifier;

    public function __construct()
    {
        $this->entityExtractor = new NLPEntityExtractorService();
        $this->semanticSearch = new NLPSemanticSearchService();
        // $this->intentClassifier = new IntentClassifierService(); // If you have this service
    }

    /**
     * Process NLP analysis for a question
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeQuestion(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'language' => 'nullable|string|in:en,pt,es'
        ]);

        $question = $request->input('question');
        $language = $request->input('language') ?? $this->entityExtractor->detectLanguage($question);

        // Extract entities
        $entitiesResult = $this->entityExtractor->extractEntities($question, $language);
        $entities = $entitiesResult['entities'] ?? [];

        // Perform semantic search (example with movie data)
        $movieData = [
            ['title' => 'The Godfather', 'description' => 'Crime drama about a mafia family'],
            ['title' => 'Inception', 'description' => 'Sci-fi thriller about dreams'],
            ['title' => 'The Dark Knight', 'description' => 'Superhero film with Joker'],
        ];

        $semantic = $this->semanticSearch->semanticSearch($question, $movieData, $language);

        // Debug output (as shown in your example)
        // dd($entities, $semantic);

        return response()->json([
            'success' => true,
            'question' => $question,
            'language' => $language,
            'entities' => $entities,
            'semantic_results' => $semantic['results'] ?? [],
            'similarities' => $semantic['similarities'] ?? []
        ]);
    }

    /**
     * Extract entities from text
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function extractEntities(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'language' => 'nullable|string|in:en,pt,es'
        ]);

        $text = $request->input('text');
        $language = $request->input('language');

        $result = $this->entityExtractor->extractEntities($text, $language);

        return response()->json([
            'success' => true,
            'entities' => $result['entities'] ?? [],
            'language' => $result['language'] ?? 'en'
        ]);
    }

    /**
     * Perform semantic search
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function semanticSearch(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:1000',
            'items' => 'required|array|max:100',
            'language' => 'nullable|string|in:en,pt,es',
            'top_k' => 'nullable|integer|min:1|max:50'
        ]);

        $query = $request->input('query');
        $items = $request->input('items');
        $language = $request->input('language');
        $topK = $request->input('top_k', 5);

        $result = $this->semanticSearch->semanticSearch($query, $items, $language, $topK);

        return response()->json([
            'success' => true,
            'results' => $result['results'] ?? [],
            'similarities' => $result['similarities'] ?? []
        ]);
    }
}