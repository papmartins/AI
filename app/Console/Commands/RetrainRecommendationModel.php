<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Recommendation\MovieRecommendationService;
use Illuminate\Support\Facades\Storage;

class RetrainRecommendationModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recommendations:retrain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrain the movie recommendation model with current data';

    /**
     * Execute the console command.
     */
    public function handle(MovieRecommendationService $recommender)
    {
        $this->info('Starting model retraining...');
        
        // Remove existing model files
        $modelPath = storage_path('app/movie_recommender.model');
        $datasetPath = storage_path('app/movie_recommendations.csv');
        $metadataPath = storage_path('app/movie_metadata.json');
        
        if (file_exists($modelPath)) {
            unlink($modelPath);
            $this->info('Removed existing model file');
        }
        
        if (file_exists($datasetPath)) {
            unlink($datasetPath);
            $this->info('Removed existing dataset file');
        }
        
        if (file_exists($metadataPath)) {
            unlink($metadataPath);
            $this->info('Removed existing metadata file');
        }
        
        // Pre-calculate movie features first
        $this->info('Pre-calculating movie features...');
        $featuresStart = microtime(true);
        $features = $recommender->precalculateAndCacheMovieFeatures();
        $featuresTime = round(microtime(true) - $featuresStart, 2);
        $this->info("✓ Pre-calculated features for " . count($features) . " movies in {$featuresTime} seconds");
        
        // Train new model
        $this->info('Training new model...');
        $startTime = microtime(true);
        
        try {
            $estimator = $recommender->retrainMLModel();
            $endTime = microtime(true);
            
            $trainingTime = round($endTime - $startTime, 2);
            $this->info("Model training completed in {$trainingTime} seconds");
            $this->info('New model saved to: ' . $modelPath);
            
            // Check if files were created
            if (file_exists($modelPath)) {
                $this->info('✓ Model file created successfully');
            } else {
                $this->error('✗ Model file was not created');
            }
            
            if (file_exists($datasetPath)) {
                $this->info('✓ Dataset file created successfully');
            } else {
                $this->error('✗ Dataset file was not created');
            }
            
        } catch (\Exception $e) {
            $this->error('Error during training: ' . $e->getMessage());
            return 1;
        }
        
        $this->info('Model retraining completed successfully!');
        return 0;
    }
}