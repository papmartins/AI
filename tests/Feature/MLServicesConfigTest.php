<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Services\IntentClassifierService;
use App\Services\IrisClassifier;
use App\Services\MovieRecommender;
use App\Services\AnomalyDetector;

class MLServicesConfigTest extends TestCase
{
    use RefreshDatabase;
    
    #[Test]
    public function test_all_services_use_config()
    {
        // Test NLP Chatbot Service
        $nlpService = new IntentClassifierService();
        $nlpPath = $nlpService->getModelPath();
        $this->assertStringContainsString('nlp_intention_classifier.model', $nlpPath);
        
        // Test Iris Classifier Service
        $irisService = new IrisClassifier();
        $irisModelPath = $this->getPrivateProperty($irisService, 'modelPath');
        $irisDatasetPath = $this->getPrivateProperty($irisService, 'datasetPath');
        $this->assertStringContainsString('iris.model', $irisModelPath);
        $this->assertStringContainsString('iris.csv', $irisDatasetPath);
        
        // Test Movie Recommender Service
        $movieService = new MovieRecommender();
        $movieModelPath = $this->getPrivateProperty($movieService, 'modelPath');
        $movieDatasetPath = $this->getPrivateProperty($movieService, 'datasetPath');
        $this->assertStringContainsString('movie_recommender.model', $movieModelPath);
        $this->assertStringContainsString('movie_recommendations.csv', $movieDatasetPath);
        
        // Test Anomaly Detector Service
        $anomalyService = new AnomalyDetector();
        $anomalyModelPath = $this->getPrivateProperty($anomalyService, 'modelPath');
        $anomalyDatasetPath = $this->getPrivateProperty($anomalyService, 'datasetPath');
        $this->assertStringContainsString('anomaly_detector.model', $anomalyModelPath);
        $this->assertStringContainsString('anomaly_dataset.csv', $anomalyDatasetPath);
    }
    
    #[Test]
    public function test_custom_config_values()
    {
        // Override config values
        config([
            'nlp.iris.model_path' => 'custom/iris_custom.model',
            'nlp.movie_recommender.model_path' => 'custom/recommender_custom.model',
            'nlp.anomaly_detector.model_path' => 'custom/anomaly_custom.model',
        ]);
        
        // Test that services use custom config values
        $irisService = new IrisClassifier();
        $irisModelPath = $this->getPrivateProperty($irisService, 'modelPath');
        $this->assertStringContainsString('custom/iris_custom.model', $irisModelPath);
        
        $movieService = new MovieRecommender();
        $movieModelPath = $this->getPrivateProperty($movieService, 'modelPath');
        $this->assertStringContainsString('custom/recommender_custom.model', $movieModelPath);
        
        $anomalyService = new AnomalyDetector();
        $anomalyModelPath = $this->getPrivateProperty($anomalyService, 'modelPath');
        $this->assertStringContainsString('custom/anomaly_custom.model', $anomalyModelPath);
    }
    
    #[Test]
    public function test_fallback_to_default_values()
    {
        // Set config to null to test fallback
        config(['nlp.iris.model_path' => null]);
        
        $irisService = new IrisClassifier();
        $irisModelPath = $this->getPrivateProperty($irisService, 'modelPath');
        
        // Should fall back to default value
        $this->assertStringContainsString('iris.model', $irisModelPath);
    }
    
    #[Test]
    public function test_config_consistency_across_services()
    {
        // All services should use the same config structure
        $mlConfig = config('ml');
        
        $this->assertArrayHasKey('model_path', $mlConfig);
        $this->assertArrayHasKey('iris', $mlConfig);
        $this->assertArrayHasKey('movie_recommender', $mlConfig);
        $this->assertArrayHasKey('anomaly_detector', $mlConfig);
        
        // Check that each service has the expected config keys
        $this->assertArrayHasKey('model_path', $mlConfig['iris']);
        $this->assertArrayHasKey('dataset_path', $mlConfig['iris']);
        
        $this->assertArrayHasKey('model_path', $mlConfig['movie_recommender']);
        $this->assertArrayHasKey('dataset_path', $mlConfig['movie_recommender']);
        $this->assertArrayHasKey('metadata_path', $mlConfig['movie_recommender']);
        $this->assertArrayHasKey('confidence_cache_path', $mlConfig['movie_recommender']);
        
        $this->assertArrayHasKey('model_path', $mlConfig['anomaly_detector']);
        $this->assertArrayHasKey('dataset_path', $mlConfig['anomaly_detector']);
    }
    
    #[Test]
    public function test_environment_variables_load_correctly()
    {
        // Test that environment variables are properly loaded
        $this->assertNotEmpty(env('ML_MODEL_PATH'));
        $this->assertNotEmpty(env('IRIS_MODEL_PATH'));
        $this->assertNotEmpty(env('MOVIE_RECOMMENDER_MODEL_PATH'));
        $this->assertNotEmpty(env('ANOMALY_DETECTOR_MODEL_PATH'));
    }
    
    #[Test]
    public function test_directory_creation_for_model_saving()
    {
        // Test that the service can handle directory creation
        $service = new IntentClassifierService();
        $modelPath = $service->getModelPath();
        $directory = dirname($modelPath);
        
        // Directory should either exist or be creatable
        if (!is_dir($directory)) {
            $this->assertTrue(is_writable(dirname($directory)), 'Parent directory should be writable');
        } else {
            $this->assertTrue(is_writable($directory), 'Model directory should be writable');
        }
    }
    
    /**
     * Helper method to access private properties for testing
     */
    private function getPrivateProperty($object, $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}