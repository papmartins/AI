<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\IntentClassifierService;
use App\Services\NLPChatbotService;

class TrainChatbotModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:train
        {--force : Force retraining even if model exists}
        {--test : Run in test mode without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Train or retrain the NLP chatbot model';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $force = $this->option('force');
        $test = $this->option('test');

        $intentClassifier = new IntentClassifierService();
        $modelPath = $intentClassifier->getModelPath();

        if (File::exists($modelPath) && !$force) {
            $this->info('Model already exists at: ' . $modelPath);
            if (!$this->confirm('Do you want to retrain and overwrite the existing model?')) {
                return 0;
            }
        }

        $this->info('Training NLP chatbot model...');
        
        try {
            // Force retraining by deleting existing model
            if (File::exists($modelPath)) {
                File::delete($modelPath);
                $this->info('Deleted existing model');
            }

            // Train new model
            $estimator = $intentClassifier->loadOrTrainIntentionClassifier();
            
            if ($test) {
                $this->info('Test mode: Model trained but not saved');
                return 0;
            }

            $this->info('Model training completed successfully!');
            $this->info('Model saved to: ' . $modelPath);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Training failed: ' . $e->getMessage());
            return 1;
        }
    }
}