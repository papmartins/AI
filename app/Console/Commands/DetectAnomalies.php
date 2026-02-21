<?php

namespace App\Console\Commands;

use App\Services\AnomalyDetector;
use Illuminate\Console\Command;

class DetectAnomalies extends Command
{
    protected $signature = 'anomaly:detect {--retrain : Force model retraining} {--check-user= : Check specific user ID}';
    protected $description = 'Detect anomalous user behavior using Rubix ML Isolation Forest';

    public function handle(AnomalyDetector $detector)
    {
        $startTime = microtime(true);
        
        if ($this->option('retrain')) {
            $this->info('🔄 Retraining anomaly detection model...');
            $result = $detector->retrain();
            $this->info("✅ Model retrained in {$result['training_time']} seconds");
        }
        
        if ($this->option('check-user')) {
            $userId = $this->option('check-user');
            $user = \App\Models\User::find($userId);
            
            if (!$user) {
                $this->error("User with ID {$userId} not found");
                return 1;
            }
            
            $this->info("🔍 Checking user: {$user->name} ({$user->email})");
            $result = $detector->checkUserAnomaly($user);
            
            $this->line("Anomaly Score: {$result['score']}");
            $this->line("Is Anomaly: " . ($result['is_anomaly'] ? '❌ YES' : '✅ NO'));
            
            if ($result['is_anomaly']) {
                $this->line("Type: {$result['type']}");
                $this->line("Severity: {$result['severity']}");
            }
            
            return 0;
        }
        
        // Run full anomaly detection
        $this->info('🕵️‍♂️ Running anomaly detection on all users...');
        
        $anomalies = $detector->detectAnomalies();
        
        $stats = $detector->getStatistics();
        
        $this->info("📊 Detection Results:");
        $this->line("Total anomalies found: " . count($anomalies));
        $this->line("Model trained: " . ($stats['model_trained'] ? '✅ Yes' : '❌ No'));
        
        if (!empty($anomalies)) {
            $this->table(
                ['User ID', 'Name', 'Email', 'Score', 'Type', 'Severity'],
                array_map(function($anomaly) {
                    return [
                        $anomaly['user_id'],
                        $anomaly['user_name'],
                        $anomaly['user_email'],
                        $anomaly['anomaly_score'],
                        $anomaly['anomaly_type'],
                        ucfirst($anomaly['severity'])
                    ];
                }, $anomalies)
            );
        } else {
            $this->info("✅ No anomalies detected!");
        }
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        $this->info("⏱️  Completed in {$executionTime} seconds");
        
        return 0;
    }
}