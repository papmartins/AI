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
use Illuminate\Support\Facades\Log;

class MovieRecommender
{
    protected string $modelPath;
    protected string $datasetPath;
    protected string $metadataPath;
    protected string $confidenceCachePath;
    
    public function __construct()
    {
        $this->modelPath = storage_path(config('ml.movie_recommender.model_path', 'app/movie_recommender.model'));
        $this->datasetPath = storage_path(config('ml.movie_recommender.dataset_path', 'app/movie_recommendations.csv'));
        $this->metadataPath = storage_path(config('ml.movie_recommender.metadata_path', 'app/movie_metadata.json'));
        $this->confidenceCachePath = storage_path(config('ml.movie_recommender.confidence_cache_path', 'app/popularity_confidence.json'));
    }
    
    /**
     * Get pre-calculated movie features from cache
     */
    protected function getPrecalculatedMovieFeatures(): array
    {
        $cacheKey = 'precalculated_movie_features_v2';
        return Cache::get($cacheKey, []);
    }
    
    /**
     * Pre-calculate and cache movie features for faster recommendations
     * This avoids recalculating features for every recommendation request
     */
    public function precalculateAndCacheMovieFeatures(): array
    {
        $cacheKey = 'precalculated_movie_features_v2';
        
        // Return cached features if available and recent
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        
        // Load all data at once with eager loading to avoid N+1 queries
        $ratings = Rating::with(['user', 'movie'])->get();
        $rentals = Rental::get();
        $users = User::all();
        $movies = Movie::with('genre')->get();
        
        $movieFeatures = [];
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
                    'rentals' => [],
                    'user_ages' => []
                ];
            }
            $movieStats[$movieId]['ratings'][] = $rating->rating;
            $movieStats[$movieId]['user_ages'][] = $rating->user->age ?? 18;
            
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
                    'rentals' => [],
                    'user_ages' => []
                ];
            }
            $movieStats[$movieId]['rentals'][] = $rental->user_id;
        }
        
        // Calculate features for each movie
        foreach ($movies as $movie) {
            $movieId = $movie->id;
            $stats = $movieStats[$movieId] ?? ['ratings' => [], 'good_ratings' => [], 'rentals' => [], 'user_ages' => []];
            
            // Feature 1: Rental percentage (0-1)
            $rentalPercentage = count($stats['rentals']) / max(1, $totalUsers);
            
            // Feature 2: Average rental age (normalized)
            $avgRentalAge = !empty($stats['user_ages']) ? array_sum($stats['user_ages']) / count($stats['user_ages']) : 18;
            $avgRentalAge = min(1, max(0, ($avgRentalAge - 18) / 50)); // Normalize to 0-1 range
            
            // Feature 3: Average rating (normalized)
            $avgRating = !empty($stats['ratings']) ? array_sum($stats['ratings']) / count($stats['ratings']) : 0;
            $avgRating = min(1, max(0, $avgRating / 5)); // Normalize to 0-1 range
            
            // Feature 4: Average good rating age (normalized)
            $goodRatingAges = [];
            foreach ($stats['ratings'] as $index => $rating) {
                if ($rating > 3.5 && isset($stats['user_ages'][$index])) {
                    $goodRatingAges[] = $stats['user_ages'][$index];
                }
            }
            $avgGoodRatingAge = !empty($goodRatingAges) ? array_sum($goodRatingAges) / count($goodRatingAges) : 18;
            $avgGoodRatingAge = min(1, max(0, ($avgGoodRatingAge - 18) / 50)); // Normalize to 0-1 range
            
            // Store pre-calculated features
            $movieFeatures[$movieId] = [
                'rental_percentage' => $rentalPercentage,
                'avg_rental_age' => $avgRentalAge,
                'avg_rating' => $avgRating,
                'avg_good_rating_age' => $avgGoodRatingAge,
                'genre_id' => $movie->genre_id,
                'year' => $movie->year,
                'title' => $movie->title
            ];
            
            // Store metadata for reference
            $movieMetadata[$movieId] = [
                'title' => $movie->title,
                'genre' => $movie->genre->name ?? 'Unknown',
                'year' => $movie->year,
                'rating_count' => count($stats['ratings']),
                'rental_count' => count($stats['rentals']),
                'avg_rating' => $avgRating * 5 // Denormalize for display
            ];
        }
        
        // Cache features for 24 hours or until next model training
        Cache::put($cacheKey, $movieFeatures, 86400);
        
        // Also store metadata
        file_put_contents($this->metadataPath, json_encode($movieMetadata, JSON_PRETTY_PRINT));
        
        return $movieFeatures;
    }
    
    /**
     * Prepare dataset from user interactions
     * Uses demographic and popularity features for KNN compatibility
     */
    protected function prepareDataset(): void
    {
        // Get pre-calculated features if available
        $precalculatedFeatures = $this->getPrecalculatedMovieFeatures();
        
        if (!empty($precalculatedFeatures)) {
            // Use pre-calculated features for faster dataset preparation
            $csvData = [];
            $csvData[] = ['movie_id', 'rental_percentage', 'avg_rental_age', 'avg_rating', 'avg_good_rating_age'];
            
            foreach ($precalculatedFeatures as $movieId => $features) {
                $csvData[] = [
                    $movieId,
                    $features['rental_percentage'],
                    $features['avg_rental_age'],
                    $features['avg_rating'],
                    $features['avg_good_rating_age']
                ];
            }
            
            $this->writeCSV($csvData);
            return;
        }
        
        // Fallback to original implementation if no pre-calculated features
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
        
        // Pre-calculate and cache movie features for faster recommendations
        $this->precalculateAndCacheMovieFeatures();
        
        // Generate and cache popularity confidence scores
        $this->cachePopularityConfidence();
        
        // Clear all user recommendation caches since model changed
        Log::info('Clearing recommendation caches after model training');
        $this->clearAllCaches();
        
        return $estimator;
    }
    
    /**
     * Load existing model or train new one
     */
    protected function loadOrTrain(): PersistentModel
    {
        $startTime = microtime(true);
        Log::info('Loading or training model...');
        
        // Check if model file exists but has wrong number of features
        // Force retraining if we changed from 5 to 4 features
        $forceRetrain = false;
        
        if (file_exists($this->modelPath)) {
            Log::info('Model file exists, checking compatibility...');
            
            // Check the CSV headers to see if we have the right number of features
            if (file_exists($this->datasetPath)) {
                $handle = fopen($this->datasetPath, 'r');
                $headers = fgetcsv($handle);
                fclose($handle);
                
                // Expected: movie_id, rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age, rating
                // That's 6 columns total, but movie_id is not a feature, so 4 features + rating
                $expectedFeatureCount = 4; // rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age
                $actualFeatureCount = count($headers) - 2; // subtract movie_id and rating
                
                Log::info('Feature count verification', [
                    'expected_features' => $expectedFeatureCount,
                    'actual_features' => $actualFeatureCount,
                    'headers' => $headers
                ]);
                
                if ($actualFeatureCount != $expectedFeatureCount) {
                    Log::warning('Feature count mismatch, forcing model retraining', [
                        'expected_features' => $expectedFeatureCount,
                        'actual_features' => $actualFeatureCount,
                        'headers' => $headers
                    ]);
                    $forceRetrain = true;
                }
            }
        } else {
            Log::info('No existing model file found');
        }
        
        if (file_exists($this->modelPath) && !$forceRetrain) {
            Log::info('Loading existing model from: ' . $this->modelPath);
            
            // Get file info
            $fileSize = filesize($this->modelPath);
            $fileSizeKB = round($fileSize / 1024, 2);
            Log::info('Model file info', [
                'size_bytes' => $fileSize,
                'size_kb' => $fileSizeKB,
                'last_modified' => date('Y-m-d H:i:s', filemtime($this->modelPath))
            ]);
            
            $loadStart = microtime(true);
            $estimator = PersistentModel::load(new Filesystem($this->modelPath));
            $loadTime = round(microtime(true) - $loadStart, 2);
            Log::info("Model loaded in {$loadTime} seconds");
            
            // Try to get model info if available
            try {
                $modelInfo = [
                    'type' => 'KNN Regressor',
                    'k_neighbors' => 10, // Default value
                    'distance_metric' => 'Cosine'
                ];
                Log::info('Model information', $modelInfo);
            } catch (\Exception $e) {
                Log::warning('Could not get detailed model info: ' . $e->getMessage());
            }
            
        } else {
            Log::info('Training new model...');
            // Remove old model if forcing retrain
            if ($forceRetrain && file_exists($this->modelPath)) {
                unlink($this->modelPath);
                Log::info('Removed old model file');
            }
            
            $trainStart = microtime(true);
            $estimator = $this->train();
            $trainTime = round(microtime(true) - $trainStart, 2);
            Log::info("Model trained in {$trainTime} seconds");
        }
        
        $totalTime = round(microtime(true) - $startTime, 2);
        Log::info("Model load/train completed in {$totalTime} seconds");
        
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
        
        Log::info('Recommendation request', [
            'user_id' => $user->id,
            'is_frequent' => $isFrequent,
            'cache_key' => $cacheKey,
            'cache_exists' => Cache::has($cacheKey)
        ]);
        
        // Try to get cached recommendations for frequent users
        if ($isFrequent && Cache::has($cacheKey)) {
            Log::info('Returning cached recommendations for user ' . $user->id);
            return Cache::get($cacheKey);
        }
        
        try {
            Log::info('Generating new recommendations for user ' . $user->id);
            // Use Rubix ML model for predictions
            $recommendations = $this->getMLBasedRecommendations($user, $limit);
            
            // Cache recommendations for frequent users (30 minutes)
            if ($isFrequent) {
                Cache::put($cacheKey, $recommendations, now()->addMinutes(30));
                Log::info('Cached recommendations for user ' . $user->id);
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
        Log::info('Starting ML recommendations generation');
        
        // Check if we have enough movies and ratings for ML recommendations
        $totalMovies = Movie::count();
        $totalRatings = Rating::count();
        
        Log::info('Data availability check', [
            'total_movies' => $totalMovies,
            'total_ratings' => $totalRatings
        ]);
        
        // If not enough data, fall back to popular recommendations
        if ($totalMovies < 5 || $totalRatings < 10) {
            Log::warning('Not enough data for ML recommendations, falling back to popular');
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
        
        // Use pre-calculated features for faster recommendations
        $precalculatedFeatures = $this->getPrecalculatedMovieFeatures();
        
        if (!empty($precalculatedFeatures)) {
            Log::info('Using pre-calculated movie features for recommendations');
            
            $predictions = [];
            
            foreach ($precalculatedFeatures as $movieId => $features) {
                // Skip movies user has already interacted with
                if (in_array($movieId, $interactedMovieIds)) {
                    continue;
                }
                
                try {
                    // Get movie details
                    $movie = Movie::with(['genre'])->find($movieId);

                    if (!$movie) continue;
                    
                    // 1. Age rating filter - exclude movies not suitable for user's age
                    $movieAgeRating = $movie->age_rating ?? 0;
                    if ($userAge < $movieAgeRating) {
                        continue; // Exclude movies not suitable for user's age
                    }
                    
                    // Use pre-calculated features directly
                    // [rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age]
                    $featureVector = [
                        $features['rental_percentage'],
                        $features['avg_rental_age'],
                        $features['avg_rating'],
                        $features['avg_good_rating_age']
                    ];
                    
                    // Create Unlabeled dataset for prediction
                    $sampleDataset = Unlabeled::build([$featureVector]);
                    
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
                            'rental_percentage' => $features['rental_percentage'],
                            'rented_age_avg' => $features['avg_rental_age'],
                            'rating_avg' => $features['avg_rating'],
                            'rating_age_avg' => $features['avg_good_rating_age']
                        ]
                    ];
                    
                } catch (\Exception $e) {
                    Log::error('Error predicting rating for movie ' . $movieId . ': ' . $e->getMessage());
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
            
            Log::info('ML recommendations result with pre-calculated features', [
                'total_predictions' => count($predictions),
                'returned_count' => count($result),
                'execution_time_seconds' => $executionTime,
                'used_cache' => true
            ]);
            
            return $result;
            
        } else {
            // Fallback to original implementation if no pre-calculated features
            Log::info('No pre-calculated features found, using real-time calculation');
            
            $allMovies = Movie::with('genre')
                ->withAvg('ratings', 'rating')
                ->withCount('rentals')
                ->get();
            
            $predictions = [];
            
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
                    Log::error('Error predicting rating for movie ' . $movie->id . ': ' . $e->getMessage());
                    // Skip this movie if prediction fails
                    continue;
                }
            }
        }
        // Sort by predicted rating (descending)
        usort($predictions, function($a, $b) {
            return $b['predicted_rating'] <=> $a['predicted_rating'];
        });
        $result = array_slice($predictions, 0, $limit);
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        Log::info('ML recommendations result', [
            'total_predictions' => count($predictions),
            'returned_count' => count($result),
            'execution_time_seconds' => $executionTime,
            'predictions' => array_map(function($item) {
                return ['movie_id' => $item['movie']->id, 'rating' => $item['predicted_rating']];
            }, $result)
        ]);
        
        Log::info("ML recommendations generated in {$executionTime} seconds");
        
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
        // Use aggressive caching with multiple layers for optimal performance
        $cacheKey = 'popular_recommendations_v2_' . $limit;
        
        // Layer 1: Memory cache (fastest)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // Layer 2: File cache with pre-computed confidence scores
        if (file_exists($this->confidenceCachePath)) {
            $cachedData = json_decode(file_get_contents($this->confidenceCachePath), true);
            if (isset($cachedData['scores']) && !empty($cachedData['scores'])) {
                // Optimized query with single database call
                $movieIds = array_keys($cachedData['scores']);
                $movieIds = array_slice($movieIds, 0, $limit * 2);
                
                $movies = Movie::with(['genre', 'ratings', 'rentals'])
                    ->whereIn('id', $movieIds)
                    ->orderByRaw('FIELD(id, ' . implode(',', $movieIds) . ')')
                    ->get();
                
                $result = $movies->map(function($movie) use ($cachedData) {
                    return [
                        'movie' => $movie,
                        'predicted_rating' => $movie->ratings->avg('rating') ?? 0,
                        'confidence' => $cachedData['scores'][$movie->id] ?? 0.8
                    ];
                })->take($limit)->toArray();
                
                // Cache in memory for 1 hour
                Cache::put($cacheKey, $result, 3600);
                return $result;
            }
        }
        
        // Layer 3: Optimized real-time calculation with caching
        $movies = Movie::with(['genre', 'ratings', 'rentals'])
            ->withAvg('ratings', 'rating')
            ->withCount('rentals')
            ->having('ratings_avg_rating', '>', 0)
            ->orderBy('ratings_avg_rating', 'desc')
            ->orderBy('rentals_count', 'desc')
            ->take($limit * 2)
            ->get();

        $recommendations = $movies->map(function($movie) {
            return [
                'movie' => $movie,
                'predicted_rating' => $movie->ratings->avg('rating') ?? 0,
                'confidence' => $this->calculatePopularityConfidence($movie)
            ];
        })->sortByDesc('confidence')->values()->take($limit)->toArray();

        // Cache the result for future requests
        Cache::put($cacheKey, $recommendations, 3600);
        
        return $recommendations;
    }

    /**
     * Cache popularity confidence scores for all movies with optimizations
     */
    protected function cachePopularityConfidence(): void
    {
        // Process movies in batches for better memory efficiency
        $confidenceScores = [];
        $batchSize = 200;
        $totalMovies = Movie::whereHas('ratings')->count();
        
        for ($offset = 0; $offset < $totalMovies; $offset += $batchSize) {
            $movies = Movie::with(['ratings', 'rentals'])
                ->whereHas('ratings')
                ->skip($offset)
                ->take($batchSize)
                ->get();
            
            foreach ($movies as $movie) {
                // Direct calculation without method call overhead
                $ratingsCount = $movie->ratings->count();
                $rentalsCount = $movie->rentals->count();
                $avgRating = $movie->ratings->avg('rating') ?? 0;
                
                // Fast confidence calculation
                if ($ratingsCount >= 100 && $avgRating >= 4.5) {
                    $confidenceScores[$movie->id] = 0.95;
                } else {
                    $ratingConfidence = $ratingsCount >= 100 ? 0.5 : ($ratingsCount / 200);
                    $rentalConfidence = $rentalsCount >= 200 ? 0.3 : ($rentalsCount / 666);
                    $qualityConfidence = $avgRating >= 4.5 ? 0.2 : (($avgRating - 3) / 10);
                    $totalConfidence = max(0.1, $ratingConfidence + $rentalConfidence + $qualityConfidence);
                    $confidenceScores[$movie->id] = round($totalConfidence, 2);
                }
            }
            
            // Clear memory after each batch
            unset($movies);
        }

        // Sort by confidence for better recommendations (descending)
        arsort($confidenceScores);
        
        file_put_contents($this->confidenceCachePath, json_encode([
            'scores' => $confidenceScores,
            'timestamp' => now()->toDateTimeString(),
            'count' => count($confidenceScores),
            'optimized' => true
        ], JSON_PRETTY_PRINT));
        
        // Also cache in memory for immediate use
        Cache::put('popularity_confidence_scores', $confidenceScores, 86400);
    }

    /**
     * Calculate confidence for popular recommendations
     */
    protected function calculatePopularityConfidence(Movie $movie): float
    {
        // Use eager-loaded relationships for better performance
        $ratingsCount = $movie->ratings->count();
        $rentalsCount = $movie->rentals->count();
        $avgRating = $movie->ratings->avg('rating') ?? 0;

        // Optimized confidence calculation with early exit for popular movies
        if ($ratingsCount >= 100 && $avgRating >= 4.5) {
            return 0.95; // Very high confidence for highly rated popular movies
        }

        // Base confidence from ratings count (0-0.5) - faster calculation
        $ratingConfidence = $ratingsCount >= 100 ? 0.5 : ($ratingsCount / 200);

        // Additional confidence from rentals (0-0.3) - optimized
        $rentalConfidence = $rentalsCount >= 200 ? 0.3 : ($rentalsCount / 666);

        // Additional confidence from high ratings (0-0.2) - simplified
        $qualityConfidence = $avgRating >= 4.5 ? 0.2 : (($avgRating - 3) / 10);

        // Minimum confidence of 0.1 for all items
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