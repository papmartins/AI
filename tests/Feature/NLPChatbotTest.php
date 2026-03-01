<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Services\IntentClassifierService;
use App\Services\NLPChatbotService;
use App\Models\User;

class NLPChatbotTest extends TestCase
{
    use RefreshDatabase;
    
    #[Test]
    public function test_model_path_uses_config()
    {
        $service = new IntentClassifierService();
        
        // Test that the service uses the configured model path
        $modelPath = $service->getModelPath();
        
        $this->assertStringContainsString('nlp_intention_classifier.model', $modelPath);
        $this->assertStringContainsString('storage', $modelPath);
    }
    
    #[Test]
    public function test_model_path_can_be_customized_via_env()
    {
        // Override the environment variable for testing
        config(['ml.nlp.model_path' => 'custom/custom_model.model']);
        
        $service = new IntentClassifierService();
        $modelPath = $service->getModelPath();
        
        $this->assertStringContainsString('custom', $modelPath);
        $this->assertStringContainsString('custom_model.model', $modelPath);
    }
    
    #[Test]
    public function test_supported_languages_from_config()
    {
        $service = new IntentClassifierService();
        
        // Access the protected property using reflection
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('supportedLanguages');
        $property->setAccessible(true);
        $languages = $property->getValue($service);
        
        $this->assertIsArray($languages);
        $this->assertContains('pt', $languages);
        $this->assertContains('en', $languages);
        $this->assertContains('es', $languages);
    }
    
    #[Test]
    public function test_compound_intents_from_config()
    {
        $service = new IntentClassifierService();
        
        // Access the protected property using reflection
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('supportedCompoundIntents');
        $compoundIntents = $property->getValue($service);
        
        $this->assertIsArray($compoundIntents);
        $this->assertContains(['actor', 'director'], $compoundIntents);
        $this->assertContains(['actor', 'genre'], $compoundIntents);
        $this->assertContains(['genre', 'rating'], $compoundIntents);
    }
    
    #[Test]
    public function test_controller_uses_same_config()
    {
        $controller = new \App\Http\Controllers\NLPChatbotController(
            app()->make(\App\Services\NLPChatbotService::class),
            app()->make(\App\Services\IntentClassifierService::class)
        );
        
        // Access the protected method using reflection
        $method = new \ReflectionMethod($controller, 'getModelPath');
        $method->setAccessible(true);
        $controllerPath = $method->invoke($controller);
        
        $service = new IntentClassifierService();
        $servicePath = $service->getModelPath();
        
        // Both should use the same configuration
        $this->assertEquals($servicePath, $controllerPath);
    }
    
    #[Test]
    public function test_config_fallback_values()
    {
        // Test that fallback values work when config is missing
        // Save original config
        $originalConfig = config('ml.nlp.model_path');
        
        // Set config to null to test fallback
        config(['ml.nlp.model_path' => null]);
        
        $service = new IntentClassifierService();
        $modelPath = $service->getModelPath();
        
        // Debug: output the actual path
        \Log::info('Model path in fallback test: ' . $modelPath);
        
        // Should fall back to default value
        $this->assertStringContainsString('nlp_intention_classifier.model', $modelPath);
        
        // Restore original config
        config(['ml.nlp.model_path' => $originalConfig]);
    }
}