<?php

namespace App\Services\Recommendation;

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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MLRecommendationService
{
    protected string $modelPath;
    protected string $datasetPath;
    protected string $metadataPath;

    public function __construct()
    {
        $this->modelPath = storage_path(config('ml.movie_recommender.model_path', 'app/movie_recommender.model'));
        $this->datasetPath = storage_path(config('ml.movie_recommender.dataset_path', 'app/movie_recommendations.csv'));
        $this->metadataPath = storage_path(config('ml.movie_recommender.metadata_path', 'app/movie_metadata.json'));
    }

    public function getMLBasedRecommendations(User $user, int $limit = 6): array
    {
        $model = $this->loadOrTrain();
        $userRatings = $this->getUserRatingsWithFeatures($user);
        
        if ($userRatings->isEmpty()) {
            return [];
        }

        $dataset = $this->createPredictionDataset($userRatings);
        $predictions = $model->predict($dataset);

        $recommendations = [];
        $movieIds = $userRatings->pluck('movie_id')->toArray();
        
        foreach ($predictions as $index => $prediction) {
            $movieId = $movieIds[$index];
            $movie = Movie::find($movieId);
            
            if ($movie) {
                $confidence = $this->calculatePredictionConfidence($movie, $prediction);
                $recommendations[] = [
                    'movie' => $movie,
                    'predicted_rating' => $prediction,
                    'confidence' => $confidence
                ];
            }
        }

        usort($recommendations, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($recommendations, 0, $limit);
    }

    protected function loadOrTrain(): PersistentModel
    {
        if (file_exists($this->modelPath)) {
            Log::info('Loading existing ML model from ' . $this->modelPath);
            return PersistentModel::load(new Filesystem($this->modelPath));
        }

        Log::info('Training new ML model');
        return $this->train();
    }

    protected function train(): PersistentModel
    {
        $this->prepareDataset();
        
        $samples = [];
        $labels = [];

        $handle = fopen($this->datasetPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open dataset file: " . $this->datasetPath);
        }

        // Skip header
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $samples[] = [
                (int)$row[0],  // user_id
                (int)$row[1],  // movie_id
                (int)$row[2]   // genre_id
            ];
            $labels[] = (float)$row[3];  // rating
        }
        fclose($handle);

        $dataset = new Labeled($samples, $labels, false);

        $classifier = new KNNRegressor(10, true, new Cosine());
        $model = new PersistentModel($classifier, new Filesystem($this->modelPath));
        $model->train($dataset);

        return $model;
    }

    protected function prepareDataset(): void
    {
        // Implementation from original file
        $ratings = Rating::with(['user', 'movie.genre'])->get();
        $data = [];

        foreach ($ratings as $rating) {
            $data[] = [
                'user_id' => $rating->user_id,
                'movie_id' => $rating->movie_id,
                'genre_id' => $rating->movie->genre_id,
                'rating' => $rating->rating
            ];
        }

        $this->writeCSV($data);
    }

    protected function writeCSV(array $data): void
    {
        $handle = fopen($this->datasetPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Failed to create dataset file: " . $this->datasetPath);
        }

        fputcsv($handle, ['user_id', 'movie_id', 'genre_id', 'rating']);

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    protected function getUserRatingsWithFeatures(User $user): \Illuminate\Support\Collection
    {
        return Rating::with(['movie.genre', 'movie.ratings', 'movie.rentals'])
            ->where('user_id', $user->id)
            ->get();
    }

    protected function createPredictionDataset(\Illuminate\Support\Collection $ratings): Unlabeled
    {
        $samples = [];

        foreach ($ratings as $rating) {
            $samples[] = [
                $rating->user_id,
                $rating->movie_id,
                $rating->movie->genre_id
            ];
        }

        return new Unlabeled($samples, false);
    }

    protected function calculatePredictionConfidence(Movie $movie, float $prediction): float
    {
        $ratingCount = $movie->ratings->count();
        $rentalCount = $movie->rentals->count();
        
        $confidence = 0.5;
        
        if ($ratingCount > 0) {
            $confidence += min($ratingCount / 20, 0.3);
        }
        
        if ($rentalCount > 0) {
            $confidence += min($rentalCount / 50, 0.2);
        }
        
        return min(max($confidence, 0.1), 1.0);
    }

    public function retrain(): array
    {
        Log::info('Retraining ML model');
        $model = $this->train();
        
        return [
            'status' => 'success',
            'message' => 'Model retrained successfully',
            'model_path' => $this->modelPath
        ];
    }

    public function getModelInfo(): array
    {
        if (!file_exists($this->modelPath)) {
            return [
                'exists' => false,
                'message' => 'Model not trained yet'
            ];
        }

        $model = PersistentModel::load($this->modelPath);
        
        return [
            'exists' => true,
            'class' => get_class($model->getEstimator()),
            'path' => $this->modelPath,
            'size' => filesize($this->modelPath)
        ];
    }
}