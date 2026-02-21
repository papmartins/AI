<?php

namespace App\Services;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Transformers\NumericStringConverter;

use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;


use Rubix\ML\Regressors\KNNRegressor;
use Rubix\ML\Kernels\Distance\Cosine;
use App\Models\User;
use App\Models\Movie;
use App\Models\Rating;
use App\Models\Rental;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class MovieRecommender
{
    protected string $modelPath;
    protected string $datasetPath;
    protected string $metadataPath;
    protected string $confidenceCachePath;
    
    public function __construct()
    {
        $this->modelPath = storage_path('app/movie_recommender.model');
        $this->datasetPath = storage_path('app/movie_recommendations.csv');
        $this->metadataPath = storage_path('app/movie_metadata.json');
        $this->confidenceCachePath = storage_path('app/popularity_confidence.json');
    }
    
    /**
     * Prepare dataset from user interactions
     * Uses demographic and popularity features for KNN compatibility
     */
    protected function prepareDataset(): void
    {
        // Load all data at once with eager loading to avoid N+1 queries
        $ratings = Rating::with(['user', 'movie'])->get();
        $rentals = Rental::get();
        $users = User::all();
        
        $csvData = [];
        // Headers: movie_id, rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age, rating
        $csvData[] = ['movie_id', 'rental_percentage', 'avg_rental_age', 'avg_rating', 'avg_good_rating_age'];
        
        $movieMetadata = [];
        $totalUsers = $users->count();
        
        // Pre-calculate movie statistics for efficiency
        $movieStats = [];
        foreach ($ratings as $rating) {
            $movieId = $rating->movie_id;
            if (!isset($movieStats[$movieId])) {
                $movieStats[$movieId] = [
                    'ratings' => [],
                    'good_ratings' => [],
                    'rentals' => []
                ];
            }
            $movieStats[$movieId]['ratings'][] = $rating->rating;
            
            if ($rating->rating > 3.5) {
                $movieStats[$movieId]['good_ratings'][] = $rating->rating;
            }
        }
        
        foreach ($rentals as $rental) {
            $movieId = $rental->movie_id;
            if (!isset($movieStats[$movieId])) {
                $movieStats[$movieId] = [
                    'ratings' => [],
                    'good_ratings' => [],
                    'rentals' => []
                ];
            }
            $movieStats[$movieId]['rentals'][] = $rental->user_id;
        }
        
        // Process ratings to create training data
        foreach ($ratings as $ratingRecord) {
            $user = $ratingRecord->user;
            $movie = $ratingRecord->movie;
            
            if (!$user || !$movie) {
                continue;
            }
            
            $userAge = $user->age ?? 18;
            $movieAgeRating = $movie->age_rating ?? 0;
            
            // Skip if user is too young for the movie
            if ($userAge < $movieAgeRating) {
                continue;
            }
            
            // 1. Rental percentage (closer to 1 is better)
            $movieRentalsCount = count($movieStats[$movie->id]['rentals'] ?? []);
            $rentalPercentage = $totalUsers > 0 ? ($movieRentalsCount / $totalUsers) : 0;
            
            // 2. Average age of users who rented this movie
            $rentalAges = [];
            foreach ($movieStats[$movie->id]['rentals'] ?? [] as $rentalUserId) {
                $rentalUser = $users->find($rentalUserId);
                if ($rentalUser && $rentalUser->birth_date) {
                    $rentalAges[] = $rentalUser->birth_date->age;
                }
            }
            $avgRentalAge = count($rentalAges) > 0 ? array_sum($rentalAges) / count($rentalAges) : $userAge;
            
            // 3. User age
            $userAge = $user->age ?? 18;
            
            // 4. Average rating (closer to 5 is better)
            $movieRatings = $movieStats[$movie->id]['ratings'] ?? [];
            $avgRating = count($movieRatings) > 0 ? array_sum($movieRatings) / count($movieRatings) : 3;
            
            // 5. Average age of users who gave good ratings (> 3.5)
            $goodRatingAges = [];
            $goodRatings = Rating::where('movie_id', $movie->id)
                ->where('rating', '>', 3.5)
                ->with('user')
                ->get();
            
            foreach ($goodRatings as $goodRating) {
                if ($goodRating->user && $goodRating->user->birth_date) {
                    $goodRatingAges[] = $goodRating->user->birth_date->age;
                }
            }
            
            $avgGoodRatingAge = count($goodRatingAges) > 0 ? array_sum($goodRatingAges) / count($goodRatingAges) : $userAge;
            
            // Add to training data with movie_id and raw values:
            // [movie_id, rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age]
            $csvData[] = [
                $movie->id,
                round($rentalPercentage, 4),
                round($avgRentalAge, 4),
                round($avgRating, 4),
                round($avgGoodRatingAge, 4)
            ];
            
            // Store metadata
            $movieMetadata[$movie->id] = [
                'title' => $movie->title ?? 'Unknown',
                'genre_id' => $movie->genre_id,
                'year' => $movie->year ?? 2000,
                'age_rating' => $movie->age_rating ?? 0
            ];
        }
        
        // Write to CSV
        $this->writeCSV($csvData);
        
        // Save metadata for reference
        file_put_contents($this->metadataPath, json_encode($movieMetadata, JSON_PRETTY_PRINT));
    }
    
    /**
     * Write data to CSV file
     */
    protected function writeCSV(array $data): void
    {
        $handle = fopen($this->datasetPath, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
    
    /**
     * Train the recommendation model
     */
    protected function train(): PersistentModel
    {
        $this->prepareDataset();
        
        // Load dataset from CSV
        // Columns: 0=movie_id, 1=rental_percentage, 2=avg_rental_age, 3=avg_rating, 4=avg_good_rating_age, 5=rating
        // We want to use columns 1-4 as features (indices 1-4) and column 5 as target (index 5)
        $dataset = Labeled::fromIterator(
            new CSV($this->datasetPath, true),
            5 // rating column as target (last column, index 5)
        );
        
        // Convert string features to numeric
        $converter = new NumericStringConverter();
        $dataset = $dataset->apply($converter);
        
        // Also convert labels to numeric (NumericStringConverter doesn't handle labels)
        $samples = $dataset->samples();
        $labels = $dataset->labels();
        
        // Cast labels to float
        $numericLabels = array_map(function($label) {
            return (float)$label;
        }, $labels);
        
        // Rebuild dataset with numeric labels
        $dataset = Labeled::build($samples, $numericLabels);
        
        // Use KNN for collaborative filtering
        $estimator = new PersistentModel(
            new KNNRegressor(10, true, new Cosine()), // 10 neighbors, weighted, cosine distance
            new Filesystem($this->modelPath)
        );
        
        $estimator->train($dataset);
        $estimator->save();
        
        // Generate and cache popularity confidence scores
        $this->cachePopularityConfidence();
        
        // Clear all user recommendation caches since model changed
        \Log::info('Clearing recommendation caches after model training');
        $this->clearAllCaches();
        
        return $estimator;
    }
    
    /**
     * Load existing model or train new one
     */
    protected function loadOrTrain(): PersistentModel
    {
        $startTime = microtime(true);
        \Log::info('Loading or training model...');
        
        // Check if model file exists but has wrong number of features
        // Force retraining if we changed from 5 to 4 features
        $forceRetrain = false;
        
        if (file_exists($this->modelPath)) {
            \Log::info('Model file exists, checking compatibility...');
            
            // Check the CSV headers to see if we have the right number of features
            if (file_exists($this->datasetPath)) {
                $handle = fopen($this->datasetPath, 'r');
                $headers = fgetcsv($handle);
                fclose($handle);
                
                // Expected: movie_id, rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age, rating
                // That's 6 columns total, but movie_id is not a feature, so 4 features + rating
                $expectedFeatureCount = 4; // rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age
                $actualFeatureCount = count($headers) - 2; // subtract movie_id and rating
                
                \Log::info('Feature count verification', [
                    'expected_features' => $expectedFeatureCount,
                    'actual_features' => $actualFeatureCount,
                    'headers' => $headers
                ]);
                
                if ($actualFeatureCount != $expectedFeatureCount) {
                    \Log::warning('Feature count mismatch, forcing model retraining', [
                        'expected_features' => $expectedFeatureCount,
                        'actual_features' => $actualFeatureCount,
                        'headers' => $headers
                    ]);
                    $forceRetrain = true;
                }
            }
        } else {
            \Log::info('No existing model file found');
        }
        
        if (file_exists($this->modelPath) && !$forceRetrain) {
            \Log::info('Loading existing model from: ' . $this->modelPath);
            
            // Get file info
            $fileSize = filesize($this->modelPath);
            $fileSizeKB = round($fileSize / 1024, 2);
            \Log::info('Model file info', [
                'size_bytes' => $fileSize,
                'size_kb' => $fileSizeKB,
                'last_modified' => date('Y-m-d H:i:s', filemtime($this->modelPath))
            ]);
            
            $loadStart = microtime(true);
            $estimator = PersistentModel::load(new Filesystem($this->modelPath));
            $loadTime = round(microtime(true) - $loadStart, 2);
            \Log::info("Model loaded in {$loadTime} seconds");
            
            // Try to get model info if available
            try {
                $modelInfo = [
                    'type' => 'KNN Regressor',
                    'k_neighbors' => 10, // Default value
                    'distance_metric' => 'Cosine'
                ];
                \Log::info('Model information', $modelInfo);
            } catch (\Exception $e) {
                \Log::warning('Could not get detailed model info: ' . $e->getMessage());
            }
            
        } else {
            \Log::info('Training new model...');
            // Remove old model if forcing retrain
            if ($forceRetrain && file_exists($this->modelPath)) {
                unlink($this->modelPath);
                \Log::info('Removed old model file');
            }
            
            $trainStart = microtime(true);
            $estimator = $this->train();
            $trainTime = round(microtime(true) - $trainStart, 2);
            \Log::info("Model trained in {$trainTime} seconds");
        }
        
        $totalTime = round(microtime(true) - $startTime, 2);
        \Log::info("Model load/train completed in {$totalTime} seconds");
        
        return $estimator;
    }
    
    /**
     * Get personalized movie recommendations for a user using Rubix ML predictions
     */
    public function recommendForUser(User $user, int $limit = 6): array
    {
        // Check if user is frequent and caching is beneficial
        $cacheKey = $this->getCacheKey($user, $limit);
        $isFrequent = $this->isFrequentUser($user);
        
        \Log::info('Recommendation request', [
            'user_id' => $user->id,
            'is_frequent' => $isFrequent,
            'cache_key' => $cacheKey,
            'cache_exists' => Cache::has($cacheKey)
        ]);
        
        // Try to get cached recommendations for frequent users
        if ($isFrequent && Cache::has($cacheKey)) {
            \Log::info('Returning cached recommendations for user ' . $user->id);
            return Cache::get($cacheKey);
        }
        
        try {
            \Log::info('Generating new recommendations for user ' . $user->id);
            // Use Rubix ML model for predictions
            $recommendations = $this->getMLBasedRecommendations($user, $limit);
            
            // Cache recommendations for frequent users (30 minutes)
            if ($isFrequent) {
                Cache::put($cacheKey, $recommendations, now()->addMinutes(30));
                \Log::info('Cached recommendations for user ' . $user->id);
            }
            
            return $recommendations;
            
        } catch (\Exception $e) {
            dd($e->getMessage());
            // Fallback to popularity-based recommendations if ML fails
            $fallbackRecommendations = $this->getFallbackRecommendations($user, $limit);
            
            // Cache fallback recommendations for frequent users (shorter duration)
            if ($isFrequent) {
                Cache::put($cacheKey, $fallbackRecommendations, now()->addMinutes(15));
            }
            
            return $fallbackRecommendations;
        }
    }

    /**
     * Get recommendations using Rubix ML model predictions
     */
    protected function getMLBasedRecommendations(User $user, int $limit = 6): array
    {
        $startTime = microtime(true);
        \Log::info('Starting ML recommendations generation');
        
        // Check if we have enough movies and ratings for ML recommendations
        $totalMovies = Movie::count();
        $totalRatings = Rating::count();
        
        \Log::info('Data availability check', [
            'total_movies' => $totalMovies,
            'total_ratings' => $totalRatings
        ]);
        
        // If not enough data, fall back to popular recommendations
        if ($totalMovies < 5 || $totalRatings < 10) {
            \Log::warning('Not enough data for ML recommendations, falling back to popular');
            return $this->getFallbackRecommendations($user, $limit);
        }
        
        // Load or train the model
        $estimator = $this->loadOrTrain();
        
        // Get movies user has already interacted with
        $interactedMovieIds = Rating::where('user_id', $user->id)
            ->pluck('movie_id')
            ->merge(Rental::where('user_id', $user->id)->pluck('movie_id'))
            ->merge(Wishlist::where('user_id', $user->id)->pluck('movie_id'))
            ->unique()
            ->toArray();
        
        // Get user's average rating
        $userAvgRating = Rating::where('user_id', $user->id)->avg('rating') ?? 3;
        
        // Get user's age for demographic filtering
        $userAge = $user->age ?? 18;
        
        // Get all movies and create predictions
        $allMovies = Movie::with('genre')
            ->withAvg('ratings', 'rating')
            ->withCount('rentals')
            ->get();
        
        $predictions = [];
        // Debug: log user info
        \Log::info('Starting ML recommendations for user', [
            'user_id' => $user->id,
            'user_age' => $user->age,
            'interacted_movie_ids' => $interactedMovieIds
        ]);        
        
        foreach ($allMovies as $movie) {
            // Skip movies user has already interacted with
            if (in_array($movie->id, $interactedMovieIds)) {
                continue;
            }
            
            try {
                // 1. Age rating filter - exclude movies not suitable for user's age
                $movieAgeRating = $movie->age_rating ?? 0;
                if ($userAge < $movieAgeRating) {
                    continue; // Exclude movies not suitable for user's age
                }
                
                // Create feature vector with raw values as requested:
                // [rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age]
                $features = [
                    1, //rental_percentage we want the best possible rating, so we set this to 1 to indicate it's a feature we want to maximize
                    $userAge,
                    1, //avg_rating we want the best possible rating, so we set this to 1 to indicate it's a feature we want to maximize
                    $userAge
                ];
                
                // Create Unlabeled dataset for prediction
                $sampleDataset = Unlabeled::build([$features]);
                
                // dd($sampleDataset);
                // Make prediction
                $predictedRatings = $estimator->predict($sampleDataset);
                $predictedRating = $predictedRatings[0] ?? 3;
                
                // Clamp to valid rating range (1-5)
                $predictedRating = max(1, min(5, $predictedRating));
                
                $predictions[] = [
                    'movie' => $movie,
                    'predicted_rating' => $predictedRating,
                    'confidence' => $this->calculatePredictionConfidence($movie, $predictedRating),
                    'algorithm' => 'rubix_ml_knn',
                    'features' => [
                        'rental_percentage' => 1,
                        'rented_age_avg' => $userAge,
                        'rating_avg' => 1,
                        'rating_age_avg' => $userAge
                    ]
                ];
                
            } catch (\Exception $e) {
                \Log::error('Error predicting rating for movie ' . $movie->id . ': ' . $e->getMessage());
                // Skip this movie if prediction fails
                continue;
            }
        }
        // Sort by predicted rating (descending)
        usort($predictions, function($a, $b) {
            return $b['predicted_rating'] <=> $a['predicted_rating'];
        });
        $result = array_slice($predictions, 0, $limit);
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        \Log::info('ML recommendations result', [
            'total_predictions' => count($predictions),
            'returned_count' => count($result),
            'execution_time_seconds' => $executionTime,
            'predictions' => array_map(function($item) {
                return ['movie_id' => $item['movie']->id, 'rating' => $item['predicted_rating']];
            }, $result)
        ]);
        
        \Log::info("ML recommendations generated in {$executionTime} seconds");
        
        return $result;
    }

    /**
     * Calculate confidence for ML prediction
     */
    protected function calculatePredictionConfidence(Movie $movie, float $prediction): float
    {
        // Higher confidence for movies with more ratings
        $ratingsCount = Rating::where('movie_id', $movie->id)->count();
        $confidence = min(1.0, ($ratingsCount / 50)); // 50 ratings = full confidence
        
        // Minimum 0.5 confidence
        return max(0.1, $confidence);
    }

    /**
     * Calculate demographic similarity score for fallback recommendations
     */
    protected function calculateDemographicsSimilarityScore(User $user, Movie $movie): float
    {
        $userAge = $user->age ?? 18;
        $movieAgeRating = $movie->age_rating ?? 0;
        
        // Basic demographic similarity: closer age rating to user age is better
        // Normalize to 0-1 range
        if ($movieAgeRating == 0) {
            return 0.8; // Neutral score for movies with no age rating
        }
        
        $ageDifference = abs($movieAgeRating - $userAge);
        // Normalize: 0 difference = 1.0, 30+ difference = 0.1
        $similarity = max(0.1, 1.0 - ($ageDifference / 30.0));
        
        return round($similarity, 2);
    }


    
    /**
     * Get cache key for user recommendations
     */
    public function getCacheKey(User $user, int $limit): string
    {
        return 'movie_recommendations_user_' . $user->id . '_limit_' . $limit;
    }

    /**
     * Check if user is a frequent user (has enough interactions for caching)
     */
    protected function isFrequentUser(User $user): bool
    {
        $interactionCount = Rating::where('user_id', $user->id)->count() +
                           Rental::where('user_id', $user->id)->count() +
                           Wishlist::where('user_id', $user->id)->count();
        
        return $interactionCount >= 5; // Consider user frequent if they have 5+ interactions
    }


    
    /**
     * Fallback recommendations when ML is not available
     */
    protected function getFallbackRecommendations(User $user, int $limit = 6): array
    {
        // Get movies user has already interacted with
        $interactedMovieIds = Rating::where('user_id', $user->id)
            ->pluck('movie_id')
            ->merge(Rental::where('user_id', $user->id)->pluck('movie_id'))
            ->merge(Wishlist::where('user_id', $user->id)->pluck('movie_id'))
            ->unique();
        
        // Get top rated movies from genres the user likes
        $userGenres = Rating::where('user_id', $user->id)
            ->with('movie.genre')
            ->get()
            ->pluck('movie.genre.id')
            ->unique();
        
        if ($userGenres->isEmpty()) {
            // If no ratings, use popular genres
            $userGenres = [1, 2, 3]; // Default genres
        }
        
        $movies = Movie::with('genre')
            ->withAvg('ratings', 'rating')
            ->withCount(['ratings', 'rentals'])
            ->whereIn('genre_id', $userGenres)
            ->whereNotIn('id', $interactedMovieIds)
            ->orderBy('ratings_avg_rating', 'desc')
            ->orderBy('rentals_count', 'desc')
            ->take($limit * 2) // Get more to allow for filtering
            ->get();
        
        $recommendations = [];
        foreach ($movies as $movie) {
            $qualityScore = $movie->ratings_avg_rating ?? 0;
            $popularityScore = $movie->rentals_count ?? 0;
            $demographicsScore = $this->calculateDemographicsSimilarityScore($user, $movie);
            
            $recommendations[] = [
                'movie' => $movie,
                'quality_score' => $qualityScore,
                'popularity_score' => $popularityScore,
                'demographics_score' => $demographicsScore,
                'combined_score' => (
                    $qualityScore * 0.5 + 
                    min($popularityScore / 10, 5) * 0.3 + 
                    $demographicsScore * 0.2
                ),
                'confidence' => 0.6 // Medium confidence for fallback
            ];
        }
        
        // Sort by combined score and take top limit
        usort($recommendations, function($a, $b) {
            return $b['combined_score'] <=> $a['combined_score'];
        });
        return array_slice($recommendations, 0, $limit);
    }
    
    /**
     * Get global popular recommendations (not personalized)
     */
    public function getPopularRecommendations(int $limit = 6): array
    {
        // Try to use cached confidence scores if available
        if (file_exists($this->confidenceCachePath)) {
            $cachedData = json_decode(file_get_contents($this->confidenceCachePath), true);
            if (isset($cachedData['scores']) && !empty($cachedData['scores'])) {
                $movieIds = array_keys($cachedData['scores']);
                $movieIds = array_slice($movieIds, 0, $limit * 2); // Get more than needed
                
                $movies = Movie::with('genre')
                    ->withAvg('ratings', 'rating')
                    ->withCount(['ratings', 'rentals'])
                    ->whereIn('id', $movieIds)
                    ->orderByRaw('FIELD(id, ' . implode(',', $movieIds) . ')')
                    ->get();
                
                return $movies->map(function($movie) use ($cachedData) {
                    return [
                        'movie' => $movie,
                        'predicted_rating' => $movie->ratings_avg_rating ?? 0,
                        'confidence' => $cachedData['scores'][$movie->id] ?? 0.8
                    ];
                })->take($limit)->toArray();
            }
        }
        
        // Fallback to real-time calculation if cache not available
        $movies = Movie::with('genre')
            ->withAvg('ratings', 'rating')
            ->withCount(['ratings', 'rentals'])
            ->having('ratings_avg_rating', '>', 0)
            ->orderBy('ratings_avg_rating', 'desc')
            ->orderBy('rentals_count', 'desc')
            ->take($limit * 2) // Get more movies to allow for confidence-based sorting
            ->get();

        $recommendations = $movies->map(function($movie) {
            return [
                'movie' => $movie,
                'predicted_rating' => $movie->ratings_avg_rating ?? 0,
                'confidence' => $this->calculatePopularityConfidence($movie)
            ];
        })->sortByDesc('confidence')->values()->take($limit)->toArray();

        return $recommendations;
    }

    /**
     * Cache popularity confidence scores for all movies
     */
    protected function cachePopularityConfidence(): void
    {
        $movies = Movie::withAvg('ratings', 'rating')
            ->withCount(['ratings', 'rentals'])
            ->having('ratings_avg_rating', '>', 0)
            ->get();

        $confidenceScores = [];
        
        foreach ($movies as $movie) {
            $confidenceScores[$movie->id] = $this->calculatePopularityConfidence($movie);
        }
        
        // Sort movies by confidence (descending) and store
        uasort($confidenceScores, function($a, $b) {
            return $b <=> $a;
        });
        
        file_put_contents($this->confidenceCachePath, json_encode([
            'scores' => $confidenceScores,
            'timestamp' => now()->toDateTimeString(),
            'count' => count($confidenceScores)
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Calculate confidence for popular recommendations
     */
    protected function calculatePopularityConfidence(Movie $movie): float
    {
        $ratingsCount = $movie->ratings_count ?? 0;
        $rentalsCount = $movie->rentals_count ?? 0;
        $avgRating = $movie->ratings_avg_rating ?? 0;

        // Base confidence from ratings count (0-0.5)
        $ratingConfidence = min(0.5, ($ratingsCount / 100));

        // Additional confidence from rentals (0-0.3)
        $rentalConfidence = min(0.3, ($rentalsCount / 200));

        // Additional confidence from high ratings (0-0.2)
        $qualityConfidence = min(0.2, (($avgRating - 3) / 2));

        // Minimum confidence of 0.6 for popular items
        $totalConfidence = max(0.1, $ratingConfidence + $rentalConfidence + $qualityConfidence);

        return round($totalConfidence, 2);
    }
    
    /**
     * Clear cache for a specific user
     */
    public function clearUserCache(User $user): void
    {
        // Clear all cache keys for this user (different limits)
        for ($limit = 1; $limit <= 12; $limit++) {
            $cacheKey = $this->getCacheKey($user, $limit);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Get information about the trained model
     */
    public function getModelInfo(): array
    {
        $info = [
            'model_file_exists' => file_exists($this->modelPath),
            'dataset_file_exists' => file_exists($this->datasetPath),
            'metadata_file_exists' => file_exists($this->confidenceCachePath)
        ];
        
        if (file_exists($this->modelPath)) {
            $info['model_file_size'] = filesize($this->modelPath);
            $info['model_last_trained'] = date('Y-m-d H:i:s', filemtime($this->modelPath));
            $info['model_type'] = 'KNN Regressor';
            $info['k_neighbors'] = 10;
            $info['distance_metric'] = 'Cosine';
        }
        
        if (file_exists($this->datasetPath)) {
            $handle = fopen($this->datasetPath, 'r');
            $headers = fgetcsv($handle);
            $rowCount = 0;
            while (fgetcsv($handle) !== false) {
                $rowCount++;
            }
            fclose($handle);
            
            $info['dataset_row_count'] = $rowCount;
            $info['dataset_headers'] = $headers;
            $info['dataset_feature_count'] = count($headers) - 2; // exclude movie_id and rating
        }
        
        return $info;
    }

    /**
     * Clear cache for all users (useful after model retraining)
     */
    public function clearAllCaches(): void
    {
        // Skip cache flushing to avoid potential deadlocks
        // Caches will expire naturally with their TTL
        // For manual cache clearing, use: php artisan cache:clear
    }

    /**
     * Retrain the model
     */
    public function retrain(): array
    {
        $startTime = microtime(true);
        $estimator = $this->train();
        $endTime = microtime(true);
        
        // Check if confidence cache was created
        $confidenceCacheCreated = file_exists($this->confidenceCachePath);
        
        return [
            'status' => 'success',
            'training_time' => round($endTime - $startTime, 2),
            'model_saved' => true,
            'model_path' => $this->modelPath,
            'confidence_cache_created' => $confidenceCacheCreated,
            'confidence_cache_path' => $confidenceCacheCreated ? $this->confidenceCachePath : null,
            'caches_cleared' => false,
            'note' => 'Cache will expire naturally or run: php artisan cache:clear'
        ];
    }
}