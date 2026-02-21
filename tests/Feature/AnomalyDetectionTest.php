<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Rental;
use App\Models\Rating;
use App\Models\AnomalyDetection;
use App\Services\AnomalyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnomalyDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_anomaly_detection_service_works()
    {
        // Create test users with different behaviors
        $normalUser = User::factory()->create();
        $anomalousUser = User::factory()->create();
        
        // Normal user: moderate rental activity
        Rental::factory()->create([
            'user_id' => $normalUser->id,
            'created_at' => now()->subDays(30),
            'returned_at' => now()->subDays(25)
        ]);
        
        Rating::factory()->create([
            'user_id' => $normalUser->id,
            'rating' => 4
        ]);
        
        // Anomalous user: high rental frequency
        for ($i = 0; $i < 15; $i++) {
            Rental::factory()->create([
                'user_id' => $anomalousUser->id,
                'created_at' => now()->subDays($i),
                'returned_at' => now()->subDays($i - 2)
            ]);
        }
        
        // Test anomaly detector
        $detector = new AnomalyDetector();
        
        // Check normal user
        $normalResult = $detector->checkUserAnomaly($normalUser);
        $this->assertFalse($normalResult['is_anomaly'], 'Normal user should not be flagged as anomaly');
        
        // Check anomalous user
        $anomalousResult = $detector->checkUserAnomaly($anomalousUser);
        $this->assertTrue($anomalousResult['is_anomaly'], 'High frequency user should be flagged as anomaly');
        $this->assertEquals('high_rental_frequency', $anomalousResult['type']);
    }

    public function test_anomaly_detection_api_endpoints()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Test stats endpoint (public)
        $response = $this->get('/api/anomaly/stats');
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
        
        // Test check-me endpoint (authenticated)
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/anomaly/check-me');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status', 'is_anomaly', 'score', 'severity', 'type'
        ]);
    }

    public function test_console_command_works()
    {
        $this->artisan('anomaly:detect')
            ->assertExitCode(0);
    }
}