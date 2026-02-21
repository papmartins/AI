<?php

namespace App\Services;

use Rubix\ML\AnomalyDetectors\IsolationForest;
use Rubix\ML\AnomalyDetectors\LocalOutlierFactor;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;
use App\Models\User;
use App\Models\Rental;
use App\Models\Rating;
use App\Models\AnomalyDetection;
use Illuminate\Support\Facades\Log;

class AnomalyDetector
{
    protected string $modelPath;
    protected string $datasetPath;

    public function __construct()
    {
        $this->modelPath = storage_path('app/anomaly_detector.model');
        $this->datasetPath = storage_path('app/anomaly_dataset.csv');
    }

    /**
     * Prepare dataset from user rental and rating patterns
     */
    protected function prepareDataset(): void
    {
        $users = User::with(['rentals', 'ratings'])->get();
        
        $csvData = [];
        $csvData[] = ['rentals_per_day', 'avg_rental_duration', 'rating_deviation', 'return_delay_percentage'];
        
        foreach ($users as $user) {
            $rentalCount = $user->rentals->count();
            $ratingCount = $user->ratings->count();
            
            if ($rentalCount === 0) {
                continue; // Skip users with no rentals
            }
            
            // 1. Rentals per day (normalized)
            // Use earliest rental date instead of user creation date for accurate calculation
            $earliestRentalDate = $user->rentals->min('rented_at');
            if ($earliestRentalDate) {
                $earliestDate = \Carbon\Carbon::parse($earliestRentalDate);
                $accountAgeDays = $earliestDate->diffInDays(now());
            } else {
                // Use earliest rental date instead of user creation date for accurate calculation
        $earliestRentalDate = $user->rentals->min('rented_at');
        if ($earliestRentalDate) {
            $earliestDate = \Carbon\Carbon::parse($earliestRentalDate);
            $accountAgeDays = $earliestDate->diffInDays(now());
        } else {
            $accountAgeDays = $user->created_at->diffInDays(now());
        }
            }
            $rentalsPerDay = $accountAgeDays > 0 ? ($rentalCount / $accountAgeDays) : $rentalCount;
            
            // 2. Average rental duration (days)
            $totalRentalDuration = 0;
            $completedRentals = 0;
            
            foreach ($user->rentals as $rental) {
                if ($rental->returned_at) {
                    $totalRentalDuration += $rental->created_at->diffInDays($rental->returned_at);
                    $completedRentals++;
                }
            }
            
            $avgRentalDuration = $completedRentals > 0 ? ($totalRentalDuration / $completedRentals) : 0;
            
            // 3. Rating deviation from user's average
            $userAvgRating = $user->ratings->avg('rating') ?? 3;
            $ratingDeviation = 0;
            
            foreach ($user->ratings as $rating) {
                $ratingDeviation += abs($rating->rating - $userAvgRating);
            }
            
            $avgRatingDeviation = $ratingCount > 0 ? ($ratingDeviation / $ratingCount) : 0;
            
            // 4. Return delay percentage
            $lateReturns = 0;
            foreach ($user->rentals as $rental) {
                if ($rental->returned_at && $rental->due_date) {
                    $dueDate = \Carbon\Carbon::parse($rental->due_date);
                    if ($rental->returned_at > $dueDate) {
                        $lateReturns++;
                    }
                }
            }
            
            $returnDelayPercentage = $completedRentals > 0 ? ($lateReturns / $completedRentals) : 0;
            
            $csvData[] = [
                round($rentalsPerDay, 4),
                round($avgRentalDuration, 4),
                round($avgRatingDeviation, 4),
                round($returnDelayPercentage, 4)
            ];
        }
        
        $this->writeCSV($csvData);
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
     * Train anomaly detection model
     */
    protected function train(): PersistentModel
    {
        $this->prepareDataset();
        
        if (!file_exists($this->datasetPath)) {
            throw new \Exception('Dataset file not created');
        }
        
        // Load dataset
        $dataset = Unlabeled::fromIterator(
            new \Rubix\ML\Extractors\CSV($this->datasetPath, true)
        );
        
        // Use Isolation Forest for anomaly detection
        $estimator = new PersistentModel(
            new IsolationForest(100, null, 0.1), // 100 estimators, default ratio, 10% contamination
            new Filesystem($this->modelPath)
        );
        
        $estimator->train($dataset);
        $estimator->save();
        
        return $estimator;
    }
    
    /**
     * Load or train model
     */
    protected function loadOrTrain(): PersistentModel
    {
        if (file_exists($this->modelPath)) {
            return PersistentModel::load(new Filesystem($this->modelPath));
        }
        
        return $this->train();
    }
    
    /**
     * Detect anomalies for all users
     */
    public function detectAnomalies(): array
    {
        try {
            $estimator = $this->loadOrTrain();
            
            // Load the dataset for scoring
            if (!file_exists($this->datasetPath)) {
                $this->prepareDataset();
            }
            
            $dataset = Unlabeled::fromIterator(
                new \Rubix\ML\Extractors\CSV($this->datasetPath, true)
            );
            
            // Get anomaly scores (higher = more anomalous)
            $scores = $estimator->score($dataset);
            
            $results = [];
            $users = User::all();
            
            foreach ($scores as $index => $score) {
                if ($index < count($users)) {
                    $user = $users[$index];
                    
                    if ($score > 0.3) { // Adjusted threshold for better sensitivity
                        $anomalyType = $this->determineAnomalyType($user, $score);
                        
                        $results[] = [
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'user_email' => $user->email,
                            'anomaly_score' => round($score, 4),
                            'anomaly_type' => $anomalyType,
                            'severity' => $this->getSeverityLevel($score)
                        ];
                        
                        // Log the anomaly
                        $this->logAnomaly($user, $anomalyType, $score);
                    }
                }
            }
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('Anomaly detection failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Determine specific type of anomaly
     */
    protected function determineAnomalyType(User $user, float $score): string
    {
        $anomalyTypes = [];
        
        $rentalCount = $user->rentals->count();
        
        $accountAgeDays = $user->created_at->diffInDays(now());
        
        // High rental frequency
        if ($accountAgeDays > 0 && ($rentalCount / $accountAgeDays) > 0.5) {
            $anomalyTypes[] = 'high_rental_frequency';
        }
        
        // Inconsistent ratings
        $ratings = $user->ratings;
        if ($ratings->count() > 5) {
            $ratingStdDev = $this->calculateStandardDeviation($ratings->pluck('rating')->toArray());
            if ($ratingStdDev > 1.5) {
                $anomalyTypes[] = 'inconsistent_ratings';
            }
        }
        
        // Late returns pattern
        $lateReturns = 0;
        foreach ($user->rentals as $rental) {
            if ($rental->returned_at && $rental->due_date) {
                $dueDate = \Carbon\Carbon::parse($rental->due_date);
                if ($rental->returned_at > $dueDate) {
                    $lateReturns++;
                }
            }
        }
        
        if ($user->rentals->count() > 0 && ($lateReturns / $user->rentals->count()) > 0.3) {
            $anomalyTypes[] = 'frequent_late_returns';
        }
        
        // If no specific types found, use general
        if (empty($anomalyTypes)) {
            $anomalyTypes[] = 'general_suspicious_activity';
        }
        
        // Return all matching types as a comma-separated string
        return implode(', ', $anomalyTypes);
    }
    
    /**
     * Calculate standard deviation
     */
    protected function calculateStandardDeviation(array $values): float
    {
        if (empty($values)) {
            return 0;
        }
        
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        $variance = array_sum($squaredDifferences) / count($values);
        return sqrt($variance);
    }
    
    /**
     * Get severity level based on score
     */
    protected function getSeverityLevel(float $score): string
    {
        if ($score > 0.8) {
            return 'high';
        } elseif ($score > 0.6) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Log anomaly to database
     */
    protected function logAnomaly(User $user, string $type, float $score): void
    {
        // Check if an anomaly of this type already exists for this user
        $existingAnomaly = AnomalyDetection::where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', 'pending')
            ->first();
        
        if ($existingAnomaly) {
            // Update the existing anomaly with new details and score
            $existingAnomaly->update([
                'score' => $score,
                'details' => [
                    'detected_at' => now()->toDateTimeString(),
                    'user_agent' => request()->userAgent() ?? 'system',
                    'ip_address' => request()->ip() ?? 'unknown',
                    'updated' => true,
                    'previous_score' => $existingAnomaly->score
                ],
                'updated_at' => now()
            ]);
        } else {
            // Create a new anomaly record
            AnomalyDetection::create([
                'user_id' => $user->id,
                'type' => $type,
                'score' => $score,
                'details' => [
                    'detected_at' => now()->toDateTimeString(),
                    'user_agent' => request()->userAgent() ?? 'system',
                    'ip_address' => request()->ip() ?? 'unknown'
                ],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    /**
     * Check if a specific user is anomalous
     */
    public function checkUserAnomaly(User $user): array
    {
        try {
            $estimator = $this->loadOrTrain();
            
            // Prepare user features
            $userFeatures = $this->prepareUserFeatures($user);
            
            if (empty($userFeatures)) {
                return [
                    'is_anomaly' => false,
                    'score' => 0,
                    'severity' => 'none',
                    'type' => 'none',
                    'message' => 'Insufficient data for analysis'
                ];
            }
            
            $dataset = Unlabeled::build([$userFeatures]);
            $scores = $estimator->score($dataset);
            $score = $scores[0] ?? 0;
            
            $isAnomaly = $score > 0.3; // Adjusted threshold for better sensitivity (0.3 instead of 0.4)
            
            return [
                'is_anomaly' => $isAnomaly,
                'score' => round($score, 4),
                'severity' => $isAnomaly ? $this->getSeverityLevel($score) : 'none',
                'type' => $isAnomaly ? $this->determineAnomalyType($user, $score) : 'none',
                'features' => $userFeatures
            ];
            
        } catch (\Exception $e) {
            Log::error('User anomaly check failed: ' . $e->getMessage());
            return [
                'is_anomaly' => false,
                'score' => 0,
                'severity' => 'none',
                'type' => 'none',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prepare features for a single user
     */
    protected function prepareUserFeatures(User $user): array
    {
        $rentalCount = $user->rentals->count();
        
        if ($rentalCount === 0) {
            return [];
        }
        
        // Use earliest rental date instead of user creation date for accurate calculation
        $earliestRentalDate = $user->rentals->min('rented_at');
        if ($earliestRentalDate) {
            $earliestDate = \Carbon\Carbon::parse($earliestRentalDate);
            $accountAgeDays = $earliestDate->diffInDays(now());
        } else {
            $accountAgeDays = $user->created_at->diffInDays(now());
        }
        $rentalsPerDay = $accountAgeDays > 0 ? ($rentalCount / $accountAgeDays) : $rentalCount;
        
        // Calculate average rental duration
        $totalRentalDuration = 0;
        $completedRentals = 0;
        
        foreach ($user->rentals as $rental) {
            if ($rental->returned_at) {
                $totalRentalDuration += $rental->created_at->diffInDays($rental->returned_at);
                $completedRentals++;
            }
        }
        
        $avgRentalDuration = $completedRentals > 0 ? ($totalRentalDuration / $completedRentals) : 0;
        
        // Calculate rating deviation
        $userAvgRating = $user->ratings->avg('rating') ?? 3;
        $ratingDeviation = 0;
        
        foreach ($user->ratings as $rating) {
            $ratingDeviation += abs($rating->rating - $userAvgRating);
        }
        
        $ratingCount = $user->ratings->count();
        $avgRatingDeviation = $ratingCount > 0 ? ($ratingDeviation / $ratingCount) : 0;
        
        // Calculate return delay percentage
        $lateReturns = 0;
        foreach ($user->rentals as $rental) {
            if ($rental->returned_at && $rental->due_date) {
                $dueDate = \Carbon\Carbon::parse($rental->due_date);
                if ($rental->returned_at > $dueDate) {
                    $lateReturns++;
                }
            }
        }
        
        $returnDelayPercentage = $completedRentals > 0 ? ($lateReturns / $completedRentals) : 0;
        
        return [
            $rentalsPerDay,
            $avgRentalDuration,
            $avgRatingDeviation,
            $returnDelayPercentage
        ];
    }
    
    /**
     * Get anomaly detection statistics
     */
    public function getStatistics(): array
    {
        $totalAnomalies = AnomalyDetection::count();
        $pendingAnomalies = AnomalyDetection::where('status', 'pending')->count();
        $resolvedAnomalies = AnomalyDetection::where('status', 'resolved')->count();
        
        $anomalyTypes = AnomalyDetection::select('type', 
            \DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderBy('count', 'desc')
            ->get();
        
        return [
            'total_anomalies' => $totalAnomalies,
            'pending_anomalies' => $pendingAnomalies,
            'resolved_anomalies' => $resolvedAnomalies,
            'anomaly_types' => $anomalyTypes,
            'model_trained' => file_exists($this->modelPath),
            'last_trained' => file_exists($this->modelPath) ? date('Y-m-d H:i:s', filemtime($this->modelPath)) : null
        ];
    }
    
    /**
     * Retrain the anomaly detection model
     */
    public function retrain(): array
    {
        $startTime = microtime(true);
        
        // Remove old model
        if (file_exists($this->modelPath)) {
            unlink($this->modelPath);
        }
        
        $estimator = $this->train();
        
        $endTime = microtime(true);
        
        return [
            'status' => 'success',
            'training_time' => round($endTime - $startTime, 2),
            'model_path' => $this->modelPath,
            'message' => 'Anomaly detection model retrained successfully'
        ];
    }
}