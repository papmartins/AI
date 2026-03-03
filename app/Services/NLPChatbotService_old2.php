<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced NLP Chatbot Service
 * Uses improved NLPIntentClassifierService with semantic understanding
 * Handles movie queries, recommendations, and entity extraction
 */
class NLPChatbotService
{

    public function __construct(
        private NLPIntentClassifierService $intentClassifier,
        private NLPEntityExtractorService $entityExtractor,
        private NLPSemanticSearchService $semanticSearch,
    ) {
    }

    /**
     * Process user question and return answer
     * 
     * @param string $question User's question
     * @return string Chatbot response
     */
    public function processQuestion(string $question): string
    {
        try {
            // Detect language
            $language = $this->intentClassifier->detectLanguage($question);

            // Classify intents
            $classification = $this->intentClassifier->classifyMultipleIntents($question, $language);
            $intents = $classification['intents'] ?? ['unknown'];

            $entities = $this->entityExtractor->extractEntities($question, $language);
            // dd($entities);

            $semantic = $this->semanticSearch->semanticSearch($question, $language);
            dd($entities, $classification);
            // Handle single vs compound intents
            if (count($intents) === 1) {
                return $this->handleSingleIntent($question, $intents[0], $language);
            } else {
                return $this->handleCompoundIntents($question, $intents, $classification['condition'] ?? 'AND', $language);
            }
        } catch (\Exception $e) {
            Log::error("Chatbot error: {$e->getMessage()}");
            return $this->getDefaultResponse($language ?? 'en');
        }
    }

    /**
     * Handle single intent
     */
    protected function handleSingleIntent(string $question, string $intent, string $language): string
    {
        switch ($intent) {
            case 'actor':
                return $this->handleActorQuestion($question, $language);
            case 'director':
                return $this->handleDirectorQuestion($question, $language);
            case 'genre':
                return $this->handleGenreQuestion($question, $language);
            case 'year':
                return $this->handleYearQuestion($question, $language);
            case 'rating':
                return $this->handleRatingQuestion($question, $language);
            case 'title':
                return $this->handleTitleQuestion($question, $language);
            case 'recommendation':
                return $this->handleRecommendationQuestion($question, $language);
            default:
                return $this->getDefaultResponse($language);
        }
    }

    /**
     * Handle compound intents (actor + director, genre + year, etc)
     */
    protected function handleCompoundIntents(string $question, array $intents, string $condition, string $language): string
    {
        // Example: actor + director
        if (in_array('actor', $intents) && in_array('director', $intents)) {
            return $this->handleActorDirectorQuestion($question, $language, $condition);
        }

        // Example: genre + year
        if (in_array('genre', $intents) && in_array('year', $intents)) {
            return $this->handleGenreYearQuestion($question, $language, $condition);
        }

        // Fallback to first intent
        return $this->handleSingleIntent($question, $intents[0], $language);
    }

    /**
     * Handle actor questions
     * Uses semantic search for finding actors
     */
    protected function handleActorQuestion(string $question, string $language): string
    {
        // Extract actor name
        $entities = $this->intentClassifier->extractEntities($question, $language);
        $actorName = null;


        foreach ($entities as $entity) {
            if ($entity['label'] === 'PERSON') {
                $actorName = $entity['text'];
                break;
            }
        }

        if (!$actorName) {
            return $this->getLanguageResponse('actor_not_found', $language);
        }

        // Search for movies
        $movies = Movie::with(['genre', 'ratings'])
            ->where('cast', 'like', '%' . $actorName . '%')
            ->get();

        if ($movies->isEmpty()) {
            // Try semantic search on actor names
            $allMovies = Movie::with(['genre', 'ratings'])->get()->toArray();
            $results = $this->intentClassifier->semanticSearch($actorName, $allMovies, 5);

            if (empty($results['results'])) {
                return $this->getLanguageResponse('no_actor_movies', $language, ['name' => $actorName]);
            }

            $movies = collect($results['results']);
        }

        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie['genre']['name'] ?? 'Unknown';
            $rating = $movie['ratings_avg_rating'] ?? $movie['avg_rating'] ?? 0;
            return "• {$movie['title']} ({$movie['year']}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");

        return $this->getLanguageResponse('actor_movies_found', $language, [
            'name' => $actorName,
            'list' => $movieList
        ]);
    }

    /**
     * Handle director questions
     * Uses semantic understanding to handle various phrasings
     */
    protected function handleDirectorQuestion(string $question, string $language): string
    {
        // Extract director name or fallback to movie title
        $entities = $this->intentClassifier->extractEntities($question, $language);
        $directorName = null;

        foreach ($entities as $entity) {
            if ($entity['label'] === 'PERSON') {
                $directorName = $entity['text'];
                break;
            }
        }

        if (!$directorName) {
            // Try extracting movie title instead
            $movieTitle = null;
            foreach ($entities as $entity) {
                if ($entity['label'] === 'MOVIE') {
                    $movieTitle = $entity['text'];
                    break;
                }
            }

            if ($movieTitle) {
                return $this->findMovieByTitle($movieTitle, $language);
            }

            return $this->getLanguageResponse('director_not_found', $language);
        }

        // Search for movies by director
        $movies = Movie::with(['genre', 'ratings'])
            ->where('director', 'like', '%' . $directorName . '%')
            ->get();

        if ($movies->isEmpty()) {
            // Try semantic search
            $allMovies = Movie::with(['genre', 'ratings'])->get()->toArray();
            $results = $this->intentClassifier->semanticSearch($directorName, $allMovies, 5);

            if (empty($results['results'])) {
                return $this->getLanguageResponse('no_director_movies', $language, ['name' => $directorName]);
            }

            $movies = collect($results['results']);
        }

        $movieList = $movies->map(function ($movie) use ($language) {
            $genreName = $movie['genre']['name'] ?? 'Unknown';
            $rating = $movie['ratings_avg_rating'] ?? $movie['avg_rating'] ?? 0;
            return "• {$movie['title']} ({$movie['year']}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");

        return $this->getLanguageResponse('director_movies_found', $language, [
            'name' => $directorName,
            'list' => $movieList
        ]);
    }

    /**
     * Handle title-based questions
     * Uses semantic search to find movies even if title not exact
     */
    protected function handleTitleQuestion(string $question, string $language): string
    {
        $entities = $this->intentClassifier->extractEntities($question, $language);
        $movieTitle = null;

        // Extract movie title from entities
        foreach ($entities as $entity) {
            if ($entity['label'] === 'MOVIE') {
                $movieTitle = $entity['text'];
                break;
            }
        }

        if (!$movieTitle) {
            return $this->getLanguageResponse('title_not_found', $language);
        }

        return $this->findMovieByTitle($movieTitle, $language);
    }

    /**
     * Find movie by title with semantic matching
     */
    protected function findMovieByTitle(string $titleQuery, string $language): string
    {
        // First try exact/fuzzy match
        $movies = Movie::with(['genre', 'ratings'])
            ->where('title', 'like', '%' . $titleQuery . '%')
            ->get();

        // If no exact match, use semantic search
        if ($movies->isEmpty()) {
            $allMovies = Movie::with(['genre', 'ratings'])->get()->toArray();
            $results = $this->intentClassifier->semanticSearch($titleQuery, $allMovies, 1);

            if (empty($results['results'])) {
                return $this->getLanguageResponse('no_movies_found', $language, ['title' => $titleQuery]);
            }

            $movie = $results['results'][0];
            $similarity = $results['similarities'][0] ?? 0.5;

            // Only return if similarity is reasonable
            if ($similarity < 0.5) {
                return $this->getLanguageResponse('no_movies_found', $language, ['title' => $titleQuery]);
            }
        } else {
            $movie = $movies->first()->toArray();
        }

        $genreName = $movie['genre']['name'] ?? 'Unknown';
        $rating = $movie['ratings_avg_rating'] ?? $movie['avg_rating'] ?? 0;
        $director = $movie['director'] ?? 'Unknown';

        return $this->getLanguageResponse('movie_details', $language, [
            'title' => $movie['title'],
            'director' => $director,
            'year' => $movie['year'],
            'genre' => $genreName,
            'rating' => number_format($rating, 2)
        ]);
    }

    /**
     * Handle genre questions
     */
    protected function handleGenreQuestion(string $question, string $language): string
    {
        $entities = $this->intentClassifier->extractEntities($question, $language);
        
        // Try to extract genre from entities or question
        $genreKeywords = $this->extractGenreKeywords($question, $language);
        
        if (empty($genreKeywords)) {
            return $this->getLanguageResponse('genre_not_found', $language);
        }

        $genre = $genreKeywords[0];
        
        $movies = Movie::with(['genre', 'ratings'])
            ->whereHas('genre', function ($q) use ($genre) {
                $q->where('name', 'like', '%' . $genre . '%');
            })
            ->get();

        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_movies_found', $language, ['genre' => $genre]);
        }

        $movieList = $movies->take(10)->map(function ($movie) {
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating ?? 0;
            return "• {$movie->title} ({$movie->year}) - " . number_format($rating, 2) . "/5";
        })->implode("\n");

        return $this->getLanguageResponse('genre_movies_found', $language, [
            'genre' => $genre,
            'list' => $movieList
        ]);
    }

    /**
     * Handle year questions
     */
    protected function handleYearQuestion(string $question, string $language): string
    {
        // Extract year from question
        preg_match('/\b(19|20)\d{2}\b/', $question, $matches);
        
        if (empty($matches)) {
            return $this->getLanguageResponse('year_not_found', $language);
        }

        $year = (int)$matches[0];
        
        $movies = Movie::with(['genre', 'ratings'])
            ->where('year', $year)
            ->get();

        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_movies_found', $language, ['year' => $year]);
        }

        $movieList = $movies->map(function ($movie) {
            $rating = $movie->ratings_avg_rating ?? $movie->avg_rating ?? 0;
            return "• {$movie->title} - " . number_format($rating, 2) . "/5";
        })->implode("\n");

        return $this->getLanguageResponse('year_movies_found', $language, [
            'year' => $year,
            'list' => $movieList
        ]);
    }

    /**
     * Handle rating questions
     */
    protected function handleRatingQuestion(string $question, string $language): string
    {
        $movies = Movie::with(['genre', 'ratings'])
            ->orderByDesc('ratings_avg_rating')
            ->limit(10)
            ->get();

        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_movies_found', $language);
        }

        $movieList = $movies->map(function ($movie) {
            $rating = $movie->ratings_avg_rating ?? 0;
            return "• {$movie->title} ({$movie->year}) - " . number_format($rating, 2) . "/5";
        })->implode("\n");

        return $this->getLanguageResponse('top_rated_movies', $language, ['list' => $movieList]);
    }

    /**
     * Handle recommendation questions
     */
    protected function handleRecommendationQuestion(string $question, string $language): string
    {
        $movies = Movie::with(['genre', 'ratings'])
            ->orderByDesc('ratings_avg_rating')
            ->limit(5)
            ->get();

        if ($movies->isEmpty()) {
            return $this->getLanguageResponse('no_recommendations', $language);
        }

        $movieList = $movies->map(function ($movie) {
            $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
            $rating = $movie->ratings_avg_rating ?? 0;
            return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
        })->implode("\n");

        return $this->getLanguageResponse('recommendations', $language, ['list' => $movieList]);
    }

    /**
     * Handle compound: actor + director
     */
    protected function handleActorDirectorQuestion(string $question, string $language, string $condition): string
    {
        return $this->getLanguageResponse('compound_not_supported', $language);
    }

    /**
     * Handle compound: genre + year
     */
    protected function handleGenreYearQuestion(string $question, string $language, string $condition): string
    {
        return $this->getLanguageResponse('compound_not_supported', $language);
    }

    /**
     * Extract genre keywords from question
     */
    protected function extractGenreKeywords(string $question, string $language): array
    {
        $genres = ['action', 'comedy', 'horror', 'drama', 'romance', 'thriller', 'animation',
                   'ação', 'comédia', 'terror', 'drama', 'romance', 'suspense', 'animação'];
        
        $found = [];
        foreach ($genres as $genre) {
            if (stripos($question, $genre) !== false) {
                $found[] = $genre;
            }
        }
        
        return $found;
    }

    /**
     * Get language-specific response
     */
    protected function getLanguageResponse(string $key, string $language, array $params = []): string
    {
        $translationKey = "chatbot/responses.{$key}";
        
        $message = trans($translationKey, $params, $language);
        
        if ($message === $translationKey) {
            $message = trans("chatbot/responses.{$key}", $params, 'en');
        }
        
        if ($message === $translationKey) {
            $message = trans("chatbot/responses.unknown_response", [], $language);
        }
        
        return $message;
    }

    /**
     * Get default response
     */
    protected function getDefaultResponse(string $language): string
    {
        return $this->getLanguageResponse('unknown_response', $language);
    }
}
