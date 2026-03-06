<?php

namespace App\Services;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Refactored NLP Chatbot Service that uses specialized services
 */
class NLPChatbotService
{
    protected $intentClassifier;
    protected $entityExtractor;
    protected $movieRecommender;
    protected $mlClient;
    
    protected $intentConfig = [
            'actor' => ['column' => 'cast', 'response_founded' => 'actor_found', 'response_not_founded' => 'actor_not_found'],
            'director' => ['column' => 'director', 'response_founded' => 'director_found', 'response_not_founded' => 'director_not_found'],
            'title' => ['column' => 'title', 'response_founded' => 'movie_found', 'response_not_founded' => 'movie_not_found'],
            'genre' => ['column' => 'genre_name', 'response_founded' => 'genre_found', 'response_not_founded' => 'genre_not_found'],
            'year' => ['column' => 'year', 'response_founded' => 'year_found', 'response_not_founded' => 'year_not_found'],
            'rating' => ['column' => 'rating', 'response_founded' => 'rating_found', 'response_not_founded' => 'rating_not_found'],
        ];
    
    public function __construct(MovieRecommender $movieRecommender)
    {
        $this->mlClient = new MLMicroserviceClient();
        $this->movieRecommender = $movieRecommender;
        
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
                
        // Try to use ML microservice first if available
        $useMicroservice = $this->mlClient->checkServiceHealth();
        if ($useMicroservice) {
            // dd($this->processWithMicroservice($question, $userId));
            return $this->processWithMicroservice($question, $userId);
        }
        
        // Fallback to PHP implementation
        return "Error: ML microservice is not available. Please try again later.";
    }
    
    /**
     * Process question using ML microservice
     *
     * @param string $question User's question
     * @param string|null $userId Optional user identifier
     * @return string Response to the user's question
     */
    protected function processWithMicroservice(string $question, ?string $userId = null): string
    {
        try {
            // Classify intents using microservice
            $intentData = $this->mlClient->classifyIntents($question, $userId);

            if (! empty($intentData['intents'] ?? [])) {
                return $this->handleCompoundQuestion($question, $intentData);
            }
                        
            // Fallback for unknown questions
            return $this->handleCompoundQuestion($question, $intentData);
            
        } catch (\Exception $e) {
            Log::error('ML Microservice processing error: ' . $e->getMessage());
            return "ML microservice process error. Please try again later." . $e->getMessage();
        }
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
        $entities = $intentData['entities'];
        $condition = $intentData['condition'] ?? 'AND';
        $language = $intentData['language'] ?? 'en';
        
        // Handle recommendation separately
        if (in_array('recommendation', $intents)) {
            return $this->handleRecommendationQuestion($question, $language);
        }
        
        // Build query based on entities (entities keys are filter columns)
        $query = Movie::query();
        
        // Apply filters from entities
        foreach ($entities as $column => $values) {
            // Handle different column types
            if ($column === 'genre') {
                if ($condition === 'AND') {
                    $query->whereHas('genre', function($q) use ($values, $language) {
                        $q->whereIn('name_'.$language, $values);
                    });
                } else {
                    $query->orWhereHas('genre', function($q) use ($values, $language) {
                        $q->whereIn('name_'.$language, $values);
                    });
                }
            } elseif ($column === 'rating') {
                if ($condition === 'AND') {
                    $query->whereHas('ratings', function($q) use ($values) {
                        $q->whereIn('rating', $values);
                    });
                } else {
                    $query->orWhereHas('ratings', function($q) use ($values) {
                        $q->whereIn('rating', $values);
                    });
                }
            } else {
                if ($condition === 'AND') {
                    $query->where(
                        $this->intentConfig[$column]['column'] ?? 'title',
                        'like',
                        "%".($values[0] ?? null)."%"
                    );
                } else {
                    $query->orWhere(
                        $this->intentConfig[$column]['column'] ?? 'title',
                        'like',
                        "%".($values[0] ?? null)."%"
                    );
                }
            }
        }
        
        $movies = $query->with(['genre', 'ratings'])->get();
        
        $response = "";
        foreach ($intents as $intent) {
            if (! isset($this->intentConfig[$intent])) {
                continue;
            }
            $response .= $this->formatMoviesResponse($movies, $language, $intent);
        }
        return $response;
    }
    
    protected function formatMoviesResponse(Collection $movies, string $language, string $intent): string
    {

        if ($movies->isEmpty()) {
            return $this->getLanguageResponse($this->intentConfig[$intent]['response_not_founded'], $language, []);
        }

        switch ($intent) {
            case 'actor':
                $list = collect($movies)->map(function (Movie $movie) {
                    return "• {$movie->cast}";
                })->implode("\n");
                break;
            case 'director':
                $list = collect($movies)->map(function (Movie $movie) {
                    return "• {$movie->director}";
                })->implode("\n");
                break;
            case 'title':
                $list = collect($movies)->map(function (Movie $movie) {
                    $genreName = $movie->genre ? $movie->genre->name : 'Unknown';
                    $rating = $movie->ratings_avg_rating ?? $movie->avg_rating;
                    return "• {$movie->title} ({$movie->year}) - {$genreName} - " . number_format($rating, 2) . "/5";
                })->implode("\n");
                break;
            case 'genre':
                if ($movies->isEmpty()) {
                    $list = Genre::pluck('name')->implode("\n");
                } else {
                    $list = collect($movies)->map(function (Movie $movie) {
                        return "• {$movie->genre->name}";
                    })->implode("\n");
                }
                break;
            case 'year':
                $list = collect($movies)->map(function (Movie $movie) {
                    return "• {$movie->year}";
                })->implode("\n");
                break;
                break;
            case 'rating':
                $list = collect($movies)->map(function (Movie $movie) {
                    return "• {$movie->avgRating}";
                })->implode("\n");
                break;
            default:
                $list = 'unknown_response';
        }
        
        return $this->getLanguageResponse($this->intentConfig[$intent]['response_founded'], $language, ['list' => $list]);
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
        
        return $message."<br>";
    }
            
    /**
     * Handle recommendation questions
     *
     * @param string $question User's question
     * @param string $language Language code
     * @return string Formatted response
     */
    protected function handleRecommendationQuestion(string $question, string $language, bool $getList = false): array|string
    {
        try {
            $recommendations = $this->movieRecommender->getPopularRecommendations(5);
            
            if (empty($recommendations)) {
                if ($getList) {
                    return collect([]);
                }
                return $this->getLanguageResponse('no_recommendations', $language);
            }
            
            if ($getList) {
                return collect($recommendations)->pluck('movie.id')->toArray();
            }
            
            $movieList = collect($recommendations)->map(function ($item) use ($language) {
                $movie = $item['movie'] ?? $item;
                $genreName = $movie->genre ? $movie->genre->name : ($item['genre'] ?? 'Unknown');
                $rating = $movie->ratings_avg_rating ?? ($item['predicted_rating'] ?? 0);
                $title = $movie->title ?? ($item['title'] ?? 'Unknown');
                $year = $movie->year ?? ($item['year'] ?? '????');
                return "• {$title} ({$year}) - {$genreName} - " . number_format($rating, 2) . "/5";
            });
                        
            return $this->getLanguageResponse('high_rated_movies', $language, ['list' => $movieList->implode("\n")]);
        } catch (\Exception $e) {
            if ($getList) {
                return collect([]);
            }
            return $this->getLanguageResponse('no_recommendations', $language);
        }
    }

    protected function handleUnknownQuestion(string $question, string $language, bool $getList = false): string
    {
        if ($getList) {
            return collect([]);
        }
        return $this->getLanguageResponse('unknown_question', $language);
    }
    
}
