<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Fallback Intent Classifier
 * Uses local rules when NLP microservice is unavailable
 * Simple, fast, and always reliable
 */
class FallbackIntentClassifier
{
    protected $intentRules = [
        'actor' => [
            'keywords' => ['actor', 'actress', 'star', 'starring', 'cast', 'ator', 'atriz', 'protagonizados', 'estrelou'],
            'patterns' => ['/movies?\s+with\s+/i', '/stars?\s+/i', '/featuring\s+/i']
        ],
        'director' => [
            'keywords' => ['director', 'directed', 'filmmaker', 'director', 'diretor', 'realizador', 'dirigiu', 'dirigido'],
            'patterns' => ['/directed\s+by\s+/i', '/diretor\s+/i', '/realizado\s+por\s+/i']
        ],
        'genre' => [
            'keywords' => ['genre', 'action', 'comedy', 'horror', 'drama', 'romance', 'thriller', 'gênero', 'ação', 'comédia'],
            'patterns' => ['/\b(action|comedy|horror|drama)\s+(movies?|films?)/i', '/films?\s+of\s+type/i']
        ],
        'year' => [
            'keywords' => ['year', 'released', 'from', 'anno', 'ano', 'lançado'],
            'patterns' => ['/\b(19|20)\d{2}\b/', '/released\s+in\s+(\d{4})/i']
        ],
        'rating' => [
            'keywords' => ['rated', 'rating', 'highly', 'best', 'good', 'avaliados', 'nota'],
            'patterns' => ['/high\w*\s+rated/i', '/well.\?rated/i']
        ],
        'title' => [
            'keywords' => ['title', 'called', 'named', 'título', 'chamado'],
            'patterns' => ['/movie\s+titled\s+/i', '/film\s+called\s+/i']
        ],
        'recommendation' => [
            'keywords' => ['recommend', 'suggest', 'popular', 'best', 'recomenda', 'sugere', 'populares'],
            'patterns' => ['/recommend\s+(me\s+)?/i', '/suggest\s+(me\s+)?/i']
        ]
    ];

    /**
     * Classify multiple intents using rules
     */
    public function classifyMultipleIntents(string $question): array
    {
        $lowerQuestion = strtolower($question);
        $detectedIntents = [];

        // Check each intent
        foreach ($this->intentRules as $intent => $rules) {
            if ($this->matchesRules($lowerQuestion, $rules)) {
                $detectedIntents[] = $intent;
            }
        }

        // Detect AND/OR condition
        $condition = 'AND';
        if (preg_match('/\s+(ou|or|either)\s+/i', $question)) {
            $condition = 'OR';
        }

        if (empty($detectedIntents)) {
            return [
                'intents' => ['unknown'],
                'snippets' => [],
                'condition' => 'AND',
                'source' => 'fallback'
            ];
        }

        // Limit to 2 intents max
        $intents = array_slice($detectedIntents, 0, 2);

        return [
            'intents' => $intents,
            'snippets' => [],
            'condition' => $condition,
            'source' => 'fallback'
        ];
    }

    /**
     * Classify single intent using rules
     */
    public function classifyIntention(string $question): string
    {
        $lowerQuestion = strtolower($question);

        foreach ($this->intentRules as $intent => $rules) {
            if ($this->matchesRules($lowerQuestion, $rules)) {
                return $intent;
            }
        }

        return 'unknown';
    }

    /**
     * Detect language
     */
    public function detectLanguage(string $question): string
    {
        $lower = strtolower($question);
        
        // Portuguese
        if (preg_match('/(que|com|para|ator|filme|geralmente|português)/i', $lower)) {
            return 'pt';
        }
        
        // Spanish
        if (preg_match('/(que|del|para|actor|película|generalmente|español|español)/i', $lower)) {
            return 'es';
        }

        return 'en';
    }

    /**
     * Fuzzy search movies by title similarity
     */
    public function fuzzySearchMovies(string $query, array $items, int $topK = 5): array
    {
        if (empty($items)) {
            return ['results' => [], 'similarities' => []];
        }

        $scored = [];
        
        foreach ($items as $item) {
            $itemTitle = $item['title'] ?? $item['text'] ?? '';
            $similarity = $this->calculateSimilarity($query, $itemTitle);
            $scored[] = [
                'item' => $item,
                'similarity' => $similarity
            ];
        }

        // Sort by similarity
        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        $results = [];
        $similarities = [];
        
        foreach (array_slice($scored, 0, $topK) as $scored_item) {
            $results[] = $scored_item['item'];
            $similarities[] = $scored_item['similarity'];
        }

        return [
            'results' => $results,
            'similarities' => $similarities
        ];
    }

    /**
     * Calculate string similarity (simple Levenshtein-based)
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        // Check if one contains the other
        if (stripos($str2, $str1) !== false || stripos($str1, $str2) !== false) {
            return 0.9;
        }

        // Calculate similarity based on common words
        $words1 = preg_split('/\s+/', strtolower($str1));
        $words2 = preg_split('/\s+/', strtolower($str2));
        
        $commonWords = count(array_intersect($words1, $words2));
        $totalWords = max(count($words1), count($words2));

        return $totalWords > 0 ? $commonWords / $totalWords : 0.0;
    }

    /**
     * Check if question matches intent rules
     */
    protected function matchesRules(string $lowerQuestion, array $rules): bool
    {
        // Check keywords
        foreach ($rules['keywords'] as $keyword) {
            if (stripos($lowerQuestion, $keyword) !== false) {
                return true;
            }
        }

        // Check patterns
        foreach ($rules['patterns'] as $pattern) {
            if (preg_match($pattern, $lowerQuestion)) {
                return true;
            }
        }

        return false;
    }
}
