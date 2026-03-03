<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Movie;

class NLPSemanticSearchDatabaseService
{
    protected $baseUrl;
    protected $timeout;
    
    public function __construct()
    {
        $this->baseUrl = config('nlp_service.nlp_service.url', 'http://localhost:8001');
        $this->timeout = config('nlp_service.nlp_service.timeout', 30);
    }
    
    /**
     * Perform semantic search using movie data from database
     *
     * @param string $query
     * @param string|null $language
     * @param int $topK
     * @param array|null $filterByIntent Filter by specific intent (e.g., ['title', 'actor'])
     * @return array
     */
    public function semanticSearchFromDatabase(string $query, ?string $language = null, int $topK = 5, ?array $filterByIntent = null): array
    {
        try {
            // Fetch movies from database
            $moviesQuery = Movie::query();
            
            // Apply intent-based filtering if specified
            if ($filterByIntent && !empty($filterByIntent)) {
                foreach ($filterByIntent as $intent) {
                    switch ($intent) {
                        case 'title':
                            // For title searches, we want all movies
                            break;
                        case 'actor':
                            $moviesQuery->whereNotNull('cast')->where('cast', '!=', '');
                            break;
                        case 'director':
                            $moviesQuery->whereNotNull('director')->where('director', '!=', '');
                            break;
                        case 'genre':
                            $moviesQuery->whereNotNull('genre')->where('genre', '!=', '');
                            break;
                        case 'year':
                            $moviesQuery->whereNotNull('release_year');
                            break;
                    }
                }
            }
            
            // Get movies with necessary fields
            $movies = $moviesQuery->select('id', 'title', 'description', 'genre_id', 'director', 'cast', 'year')
                // ->limit(100) // Limit for performance
                ->get()
                ->map(function ($movie) {
                    return [
                        'id' => $movie->id,
                        'title' => $movie->title,
                        'description' => $this->buildMovieDescription($movie),
                        'genre' => $movie->genre,
                        'director' => $movie->director,
                        'cast' => $movie->cast,
                        'year' => $movie->release_year
                    ];
                })->toArray();
            
            if (empty($movies)) {
                return ['results' => [], 'similarities' => []];
            }
            
            // Call NLP microservice with database movies
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/semantic-search", [
                    'query' => $query,
                    'items' => $movies,
                    'language' => $language,
                    'top_k' => $topK
                ]);
            
            if ($response->successful()) {
                $result = $response->json();
                // Add movie IDs to results for easy reference
                if (!empty($result['results'])) {
                    foreach ($result['results'] as &$movieResult) {
                        $movieId = $movies[array_search($movieResult['title'], array_column($movies, 'title'))]['id'] ?? null;
                        if ($movieId) {
                            $movieResult['movie_id'] = $movieId;
                        }
                    }
                }
                return $result;
            }
            
            Log::error('NLP Semantic Search failed: ' . $response->body());
            return ['results' => [], 'similarities' => []];
            
        } catch (\Exception $e) {
            Log::error('NLP Semantic Search Database error: ' . $e->getMessage());
            return ['results' => [], 'similarities' => []];
        }
    }
    
    /**
     * Build comprehensive description from movie data
     */
    protected function buildMovieDescription(Movie $movie): string
    {
        $parts = [];
        
        if ($movie->genre) {
            $parts[] = $movie->genre;
        }
        
        if ($movie->director) {
            $parts[] = "directed by {$movie->director}";
        }
        
        if ($movie->cast) {
            $parts[] = "starring {$movie->cast}";
        }
        
        if ($movie->release_year) {
            $parts[] = "released in {$movie->release_year}";
        }
        
        if ($movie->description) {
            $parts[] = $movie->description;
        }
        
        return implode(' ', $parts);
    }
    
    /**
     * Search movies by specific intent
     */
    public function searchByIntent(string $query, array $intents, ?string $language = null, int $topK = 5): array
    {
        return $this->semanticSearchFromDatabase($query, $language, $topK, $intents);
    }
    
    /**
     * Get movie recommendations based on semantic similarity
     */
    public function getSemanticRecommendations(string $query, ?string $language = null, int $limit = 5): array
    {
        $result = $this->semanticSearchFromDatabase($query, $language, $limit);
        
        // Return movie IDs for easy lookup
        $recommendations = [];
        foreach ($result['results'] ?? [] as $movie) {
            if (isset($movie['movie_id'])) {
                $recommendations[] = $movie['movie_id'];
            }
        }
        
        return $recommendations;
    }
}