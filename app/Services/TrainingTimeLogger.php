<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class TrainingTimeLogger
{
    protected string $logFilePath;

    public function __construct()
    {
        $this->logFilePath = storage_path('app/training_times.json');
    }

    /**
     * Log training time for a specific model
     *
     * @param string $modelName
     * @param float $trainingTime
     * @return void
     */
    public function logTrainingTime(string $modelName, float $trainingTime): void
    {
        $times = $this->getTrainingTimes();
        $times[$modelName] = $trainingTime;
        $this->saveTrainingTimes($times);
    }

    /**
     * Get all training times
     *
     * @return array
     */
    public function getTrainingTimes(): array
    {
        if (!File::exists($this->logFilePath)) {
            return [];
        }

        try {
            $content = File::get($this->logFilePath);
            return json_decode($content, true) ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to read training times: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get training time for a specific model
     *
     * @param string $modelName
     * @return float|null
     */
    public function getTrainingTime(string $modelName): ?float
    {
        $times = $this->getTrainingTimes();
        return $times[$modelName] ?? null;
    }

    /**
     * Save training times to file
     *
     * @param array $times
     * @return void
     */
    protected function saveTrainingTimes(array $times): void
    {
        try {
            // Ensure directory exists
            $directory = dirname($this->logFilePath);
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Save JSON file
            File::put($this->logFilePath, json_encode($times, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            Log::error('Failed to save training times: ' . $e->getMessage());
        }
    }

    /**
     * Clear all training times
     *
     * @return void
     */
    public function clearTrainingTimes(): void
    {
        $this->saveTrainingTimes([]);
    }
}