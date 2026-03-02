<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Facades\Log;

/**
 * Refactored NLP Chatbot Service that uses specialized services
 */
class NLPChatbotService
{
    protected $intentClassifier;
    protected $entityExtractor;
    protected $movieRecommender;
    protected $conversationContext;
    
    public function __construct(MovieRecommender $movieRecommender)
    {
        $this->intentClassifier = new IntentClassifierService();
        $this->entityExtractor = new EntityExtractorService();
        $this->movieRecommender = $movieRecommender;
        $this->conversationContext = [];
    }
    
    /**
     * Main method to process user questions
     *
     * @param string $question User's question
     * @param string|null $userId Optional user identifier
     * @return string Response to the user's question
     */
    public function processQuestion(string $question, ?string $userId = null): string
    {
        if (empty(trim($question))) {
            return 'Por favor, faça uma pergunta sobre filmes.';
        }
        
        // Initialize conversation context if userId provided
        if ($userId && !isset($this->conversationContext[$userId])) {
            $this->initializeConversation($userId);
        }
        
        // Classify intents (now returns intents, snippets, and condition)
        $intentData = $this->intentClassifier->classifyMultipleIntents($question);
        $intents = $intentData['intents'];
        $snippets = $intentData['snippets'];
        $condition = $intentData['condition'];
        
        // Store in conversation context
        if ($userId) {
            $this->conversationContext[$userId]['last_intent'] = $intentData;
        }
        
        // Handle compound questions
        if (count($intents) > 1) {
            return $this->handleCompoundQuestion($question, $intentData);
        }
        
        // Handle single intent questions
        if ($intents[0] !== 'unknown') {
            return $this->handleSingleIntentQuestion($question, $intents[0]);
        }
        
        // Check if this looks like a compound question that the ML missed
        $language = $this->detectLanguage($question);
        $lowerQuestion = strtolower($question);
        
        // Check for common compound question patterns
        $hasAndPattern = str_contains($lowerQuestion, ' e ') || str_contains($lowerQuestion, ' and ') || str_contains($lowerQuestion, ' y ');
        $hasOrPattern = str_contains($lowerQuestion, ' ou ') || str_contains($lowerQuestion, ' or ');
        
        if ($hasAndPattern || $hasOrPattern) {
            // Try rule-based compound question detection
            return $this->handleCompoundQuestion($question, [
                'intents' => ['actor', 'director'],
                'snippets' => [
                    'actor' => $this->entityExtractor->extractPersonName($question),
                    'director' => $this->entityExtractor->extractPersonName($question)
                ],
                'condition' => $hasAndPattern ? 'AND' : 'OR'
            ]);
        }
        
        // Fallback for unknown questions
        return $this->handleUnknownQuestion($question, $language);
    }
    
    /**
     * Handle compound questions with multiple intents
     *
     * @param string $question Original question
     * @param array $intentData Intent data with snippets
     * @return string Formatted response
     */
    protected function handleCompoundQuestion(string $question, array $intentData): string
    {
        $intents = $intentData['intents'];
        $snippets = $intentData['snippets'];
        $condition = $intentData['condition'];
        $language = $this->intentClassifier->detectLanguage($question);
        
        $intentEntities = [];
        
        foreach ($intents as $intent) {
            try {
                // Use snippet if available, otherwise use full question
                $textForExtraction = $snippets[$intent] ?? $question;
                $entities = $this->entityExtractor->extractEntitiesForIntent($textForExtraction, $intent, $language);
                $intentEntities[$intent] = [
                    'entities' => $entities,
                    'movies' => []
                ];
            } catch (\Exception $e) {
                Log::error("Error extracting entities for intent {$intent}: " . $e->getMessage());
                continue;
            }
        }
        
        // Handle specific intent combinations
        $combinationKey = implode('_', $intents);
        
        switch ($combinationKey) {
            case 'actor_director':
                return $this->handleActorDirectorQuestion($question, $intentEntities, $language, $condition);
                
            case 'actor_genre':
                return $this->handleActorGenreQuestion($question, $intentEntities, $language, $condition);
                
            // ... other combinations would be handled here
                
            default:
                return $this->getLanguageResponse('complex_question', $language);
        }
    }
    
    /**
     * Handle actor + director compound questions
     *
     * @param string $question Original question
     * @param array $intentData Extracted intent data
     * @param string $language Language code
     * @param string $condition AND or OR condition
     * @return string Formatted response
     */
    protected function handleActorDirectorQuestion(string $question, array $intentData, string $language, string $condition = 'AND'): string
    {
        $actorName = $intentData['actor']['entities'][0] ?? '';
        $directorName = $intentData['director']['entities'][0] ?? '';
        
        if (empty($actorName) || empty($directorName)) {
            return $this->getLanguageResponse('compound_entities_not_found', $language);
        }
        
        // Find movies that match the criteria
        $query = Movie::with(['genre', 'ratings']);
        
        if ($condition === 'AND') {
            $query->where('cast', 'like', '%' . $actorName . '%')
                  ->where('director', 'like', '%' . $directorName . '%');
        } else {
            $query->where(function($q) use ($actorName, $directorName) {
                $q->where('cast', 'like', '%' . $actorName . '%')
                 ->orWhere('director', 'like', '%' . $directorName . '%');
            });
        }
        
        $movies = $query->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_compound_movies_found', $language, [
                'actor' => $actorName,
                'director' => $directorName
            ]);
        }
        
        // Format the response
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        // Choose appropriate response based on condition
        if ($condition === 'OR') {
            $response = $this->getLanguageResponse('compound_actor_director_or_found', $language, [
                'actor' => $actorName,
                'director' => $directorName,
                'list' => $movieList
            ]);
        } else {
            $response = $this->getLanguageResponse('compound_actor_director_found', $language, [
                'actor' => $actorName,
                'director' => $directorName,
                'list' => $movieList
            ]);
        }
        
        return $response;
    }
    
    /**
     * Handle actor + genre compound questions
     *
     * @param string $question Original question
     * @param array $intentData Extracted intent data
     * @param string $language Language code
     * @param string $condition AND or OR condition
     * @return string Formatted response
     */
    protected function handleActorGenreQuestion(string $question, array $intentData, string $language, string $condition = 'AND'): string
    {
        $actorName = $intentData['actor']['entities'][0] ?? '';
        $genreKeywords = $intentData['genre']['entities'] ?? [];
        
        if (empty($actorName) || empty($genreKeywords)) {
            return $this->getLanguageResponse('compound_entities_not_found', $language);
        }
        
        $genreKeyword = $genreKeywords[0];
        
        // Find movies that match the criteria
        $query = Movie::with(['genre', 'ratings']);
        
        if ($condition === 'AND') {
            $query->where('cast', 'like', '%' . $actorName . '%')
                  ->whereHas('genre', function($q) use ($genreKeyword) {
                      $q->where('name', 'like', '%' . $genreKeyword . '%');
                  });
        } else {
            $query->where(function($q) use ($actorName, $genreKeyword) {
                $q->where('cast', 'like', '%' . $actorName . '%')
                 ->orWhereHas('genre', function($q) use ($genreKeyword) {
                     $q->where('name', 'like', '%' . $genreKeyword . '%');
                 });
            });
        }
        
        $movies = $query->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_compound_movies_found', $language, [
                'actor' => $actorName,
                'genre' => $genreKeyword
            ]);
        }
        
        // Format the response
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        // Choose appropriate response based on condition
        if ($condition === 'OR') {
            $responseKey = 'compound_actor_genre_or_found';
        } else {
            $responseKey = 'compound_actor_genre_found';
        }
        
        return $this->getLanguageResponse($responseKey, $language, [
            'actor' => $actorName,
            'genre' => $genreKeyword,
            'list' => $movieList
        ]);
    }
    
    /**
     * Handle single intent questions
     *
     * @param string $question User's question
     * @param string $intent Detected intent
     * @return string Formatted response
     */
    protected function handleSingleIntentQuestion(string $question, string $intent): string
    {
        $language = $this->intentClassifier->detectLanguage($question);
        
        switch ($intent) {
            case 'actor':
                return $this->handleActorQuestion($question, $language);
            case 'director':
                return $this->handleDirectorQuestion($question, $language);
            case 'title':
                return $this->handleTitleQuestion($question, $language);
            case 'recommendation':
                return $this->handleRecommendationQuestion($question, $language);
            case 'genre':
                return $this->handleGenreQuestion($question, $language);
            case 'year':
                return $this->handleYearQuestion($question, $language);
            case 'rating':
                return $this->handleRatingQuestion($question, $language);
            default:
                return $this->handleUnknownQuestion($question, $language);
        }
    }
    
    /**
     * Detect the language of the question
     *
     * @param string $question User's question
     * @return string Language code (pt, en, es)
     */
    protected function detectLanguage(string $question): string
    {
        return $this->intentClassifier->detectLanguage($question);
    }
    
    /**
     * Initialize conversation context for a user
     *
     * @param string $userId User identifier
     */
    protected function initializeConversation(string $userId): void
    {
        $this->conversationContext[$userId] = [
            'history' => [],
            'last_intent' => null,
            'entities' => [],
            'language' => 'auto',
            'preferences' => []
        ];
    }
    
    /**
     * Get language-specific response messages
     *
     * @param string $key Message key
     * @param string $language Language code
     * @param array $params Parameters for message formatting
     * @return string Formatted message
     */
    protected function getLanguageResponse(string $key, string $language, array $params = []): string
    {
        // Use Laravel's translation system with proper namespace
        $translationKey = "chatbot/responses.{$key}";
        
        // Try to get the translation for the specified language
        $message = trans($translationKey, $params, $language);
        
        // If the translation doesn't exist, fall back to English
        if ($message === $translationKey) {
            $message = trans("chatbot/responses.{$key}", $params, 'en');
        }
        
        // If still not found, return a default message
        if ($message === $translationKey) {
            $message = trans("chatbot/responses.unknown_response", [], $language);
        }
        
        return $message;
    }
    
    // ... (other handler methods would be implemented similarly)
    
    protected function handleActorQuestion(string $question, string $language): string
    {
        $actorName = $this->entityExtractor->extractPersonName($question);
        
        if (empty($actorName)) {
            return $this->getLanguageResponse('actor_not_found', $language);
        }
        
        $movies = Movie::with(['genre', 'ratings'])
            ->where('cast', 'like', '%' . $actorName . '%')
            ->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_actor_movies', $language, ['name' => $actorName]);
        }
        
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        return $this->getLanguageResponse('actor_movies_found', $language, ['name' => $actorName, 'list' => $movieList]);
    }
    
    protected function handleDirectorQuestion(string $question, string $language): string
    {
        $directorName = $this->entityExtractor->extractPersonName($question);

        if (empty($directorName)) {
            // Empty name might mean this is a movie title question
            // Try to extract title keywords
            $titleKeywords = $this->entityExtractor->extractTitleKeywords($question);
            if (!empty($titleKeywords)) {
                // This is likely a movie title, search for the movie and return its director
                $titleKeyword = $titleKeywords[0];
                $movies = Movie::with(['genre', 'ratings'])
                    ->where('title', 'like', '%' . $titleKeyword . '%')
                    ->get();
                
                if ($movies->isEmpty()) {
                    return $this->getLanguageResponse('no_director_movies', $language, ['name' => $titleKeyword]);
                }
                
                // Return the director of the found movie
                $movie = $movies->first();
                $director = $movie->director ?? 'Unknown';
                $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
                $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
                
                return $this->getLanguageResponse('movie_director_found', $language, [
                    'title' => $movie->title,
                    'director' => $director,
                    'year' => $movie->year,
                    'genre' => $genreName,
                    'rating' => number_format($rating, 2)
                ]);
            }
            
            return $this->getLanguageResponse('director_not_found', $language);
        }
        
        $movies = Movie::with(['genre', 'ratings'])
            ->where('director', 'like', '%' . $directorName . '%')
            ->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_director_movies', $language, ['name' => $directorName]);
        }
        
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        return $this->getLanguageResponse('director_movies_found', $language, ['name' => $directorName, 'list' => $movieList]);
    }
    
    /**
     * Handle title-based questions
     *
     * @param string $question User's question
     * @param string $language Language code
     * @return string Formatted response
     */
    protected function handleTitleQuestion(string $question, string $language): string
    {
        $titleKeywords = $this->entityExtractor->extractTitleKeywords($question);
        
        if (empty($titleKeywords)) {
            return $this->getLanguageResponse('title_not_found', $language);
        }
        
        $titleKeyword = $titleKeywords[0];
        
        $movies = Movie::with(['genre', 'ratings'])
            ->where('title', 'like', '%' . $titleKeyword . '%')
            ->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_title_movies', $language, ['title' => $titleKeyword]);
        }
        
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        return $this->getLanguageResponse('title_movies_found', $language, ['title' => $titleKeyword, 'list' => $movieList]);
    }
    
    /**
     * Handle recommendation questions
     *
     * @param string $question User's question
     * @param string $language Language code
     * @return string Formatted response
     */
    protected function handleRecommendationQuestion(string $question, string $language): string
    {
        try {
            $recommendations = $this->movieRecommender->getPopularRecommendations(5);
            
            if (empty($recommendations)) {
                return $this->getLanguageResponse('no_recommendations', $language);
            }
            
            $movieList = collect($recommendations)->map(function ($item) use ($language) {
                $movie = $item['movie'] ?? $item;
                $genreName = $movie->genre ? $movie->genre->name : ($item['genre'] ?? 'Unknown');
                $rating = $movie->ratings_avg_rating ?? ($item['predicted_rating'] ?? 0);
                $title = $movie->title ?? ($item['title'] ?? 'Unknown');
                $year = $movie->year ?? ($item['year'] ?? '????');
                return "• {$title} ({$year}) - {$genreName} - " . number_format($rating, 2) . "/5";
            })->implode("\n");
            
            return $this->getLanguageResponse('high_rated_movies', $language, ['list' => $movieList]);
        } catch (\Exception $e) {
            return $this->getLanguageResponse('no_recommendations', $language);
        }
    }
    
    /**
     * Handle genre-based questions
     *
     * @param string $question User's question
     * @param string $language Language code
     * @return string Formatted response
     */
    protected function handleGenreQuestion(string $question, string $language): string
    {
        $genreKeywords = $this->entityExtractor->extractGenreKeywords($question);
        
        if (empty($genreKeywords)) {
            return $this->getLanguageResponse('genre_not_found', $language);
        }
        
        $genreKeyword = $genreKeywords[0];
        
        $movies = Movie::with(['genre', 'ratings'])
            ->whereHas('genre', function($query) use ($genreKeyword) {
                $query->where('name', 'like', '%' . $genreKeyword . '%');
            })
            ->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_genre_movies', $language, ['genre' => $genreKeyword]);
        }
        
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        return $this->getLanguageResponse('genre_movies_found', $language, ['genre' => $genreKeyword, 'list' => $movieList]);
    }
    
    /**
     * Handle year-based questions
     *
     * @param string $question User's question
     * @param string $language Language code
     * @return string Formatted response
     */
    protected function handleYearQuestion(string $question, string $language): string
    {
        $year = $this->entityExtractor->extractYear($question);
        
        if (empty($year)) {
            return $this->getLanguageResponse('year_not_found', $language);
        }
        
        $movies = Movie::with(['genre', 'ratings'])
            ->where('year', $year)
            ->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_year_movies', $language, ['year' => $year]);
        }
        
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        return $this->getLanguageResponse('year_movies_found', $language, ['year' => $year, 'list' => $movieList]);
    }
    
    /**
     * Handle rating-based questions
     *
     * @param string $question User's question
     * @param string $language Language code
     * @return string Formatted response
     */
    protected function handleRatingQuestion(string $question, string $language): string
    {
        $movies = Movie::with(['genre', 'ratings'])
            ->whereHas('ratings', function($query) {
                $query->where('rating', '>=', 4); // High ratings
            })
            ->get();
        
        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_high_rated_movies', $language);
        }
        
        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");
        
        return $this->getLanguageResponse('high_rated_movies', $language, ['list' => $movieList]);
    }
    
    protected function handleUnknownQuestion(string $question, string $language): string
    {
        return $this->getLanguageResponse('unknown_question', $language);
    }
}
