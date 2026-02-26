<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MovieRecommender;
use App\Services\AnomalyDetector;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ModelTrainingController extends Controller
{
    /**
     * Show the model training page
     */
    public function index()
    {
        return Inertia::render('ModelTraining/Index', [
            'status' => session('status'),
            'training_times' => [
                'recommendation' => session('recommendation_training_time'),
                'anomaly' => session('anomaly_training_time'),
                'movie' => session('movie_training_time'),
                'total' => session('total_training_time')
            ]
        ]);
    }

    /**
     * Train the movie recommendation model
     */
    public function trainRecommendationModel(MovieRecommender $recommender)
    {
        try {
            $startTime = microtime(true);
            $result = $recommender->retrain();
            $trainingTime = microtime(true) - $startTime;

            return response()->json([
                'success' => true,
                'message' => 'Movie recommendation model trained successfully',
                'training_time' => round($trainingTime, 2),
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Recommendation model training failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to train recommendation model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Train the anomaly detection model
     */
    public function trainAnomalyModel(AnomalyDetector $detector)
    {
        try {
            $startTime = microtime(true);
            $result = $detector->retrain();
            $trainingTime = microtime(true) - $startTime;

            return response()->json([
                'success' => true,
                'message' => 'Anomaly detection model trained successfully',
                'training_time' => round($trainingTime, 2),
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Anomaly detection model training failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to train anomaly detection model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Train all models
     */
    public function trainAllModels(MovieRecommender $movieRecommender, AnomalyDetector $anomalyDetector)
    {
        try {
            $startTime = microtime(true);
            $movieStart = microtime(true);

            // Train recommendation model
            $movieResult = $movieRecommender->retrain();
            $movieTrainingTime = microtime(true) - $movieStart;

            $anomalyStart = microtime(true);
            // Train anomaly detection model
            $anomalyResult = $anomalyDetector->retrain();
            $anomalyTrainingTime = microtime(true) - $anomalyStart;

            $totalTrainingTime = microtime(true) - $startTime;

            return response()->json([
                'success' => true,
                'message' => 'All models trained successfully',
                'training_times' => [
                    'movie' => round($movieTrainingTime, 2),
                    'anomaly' => round($anomalyTrainingTime, 2),
                    'total' => round($totalTrainingTime, 2)
                ],
                'results' => [
                    'movie' => $movieResult,
                    'anomaly' => $anomalyResult
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Model training failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to train models',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get model training status
     */
    public function getTrainingStatus(Request $request)
    {
        $movieModelPath = storage_path('app/movie_recommender.model');
        $anomalyModelPath = storage_path('app/anomaly_detector.model');

        $movieModelExists = file_exists($movieModelPath);
        $anomalyModelExists = file_exists($anomalyModelPath);

        $movieModelSize = $movieModelExists ? filesize($movieModelPath) : 0;
        $anomalyModelSize = $anomalyModelExists ? filesize($anomalyModelPath) : 0;

        return response()->json([
            'movie_model' => [
                'exists' => $movieModelExists,
                'size' => $movieModelSize,
                'last_modified' => $movieModelExists ? filemtime($movieModelPath) : null
            ],
            'anomaly_model' => [
                'exists' => $anomalyModelExists,
                'size' => $anomalyModelSize,
                'last_modified' => $anomalyModelExists ? filemtime($anomalyModelPath) : null
            ]
        ]);
    }
}