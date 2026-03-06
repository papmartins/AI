<?php

namespace App\Console\Commands;

use App\Services\Recommendation\MovieRecommendationService;
use Illuminate\Console\Command;

class TrainRecommendationModel extends Command
{
    protected $signature = 'recommendations:train {--force : Force retraining even if model exists}';
    protected $description = 'Train the movie recommendation model using Rubix ML';

    public function handle(MovieRecommendationService $recommender)
    {
        $this->info('Starting movie recommendation model training...');
        
        $startTime = microtime(true);
        
        if ($this->option('force')) {
            $result = $recommender->retrainMLModel();
            $this->info('Forced retraining completed.');
        } else {
            $result = $recommender->retrainMLModel();
        }
        
        $endTime = microtime(true);
        $trainingTime = round($endTime - $startTime, 2);
        
        $this->info("✅ Training completed in {$trainingTime} seconds");
        $this->info("📊 Model saved to: {$result['model_path']}");
        $this->info('🎉 Movie recommendation system is ready!');
        
        return 0;
    }
}