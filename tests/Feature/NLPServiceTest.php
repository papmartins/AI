<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\NLPEntityExtractorService;
use App\Services\NLPSemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NLPServiceTest extends TestCase
{
    /**
     * Test entity extraction service
     */
    public function test_entity_extraction_service()
    {
        $service = new NLPEntityExtractorService();
        
        // Test with English text
        $result = $service->extractEntities('The Godfather movie directed by Francis Ford Coppola in 1972');
        
        $this->assertArrayHasKey('entities', $result);
        $this->assertArrayHasKey('language', $result);
        $this->assertIsArray($result['entities']);
        
        // Test language detection
        $language = $service->detectLanguage('que filme com o ator');
        $this->assertContains($language, ['en', 'pt', 'es']);
    }
    
    /**
     * Test semantic search service
     */
    public function test_semantic_search_service()
    {
        $service = new NLPSemanticSearchService();
        
        $items = [
            ['title' => 'The Godfather', 'description' => 'Crime drama about a mafia family'],
            ['title' => 'Goodfellas', 'description' => 'Mafia movie based on true story'],
            ['title' => 'Pulp Fiction', 'description' => 'Non-linear crime film by Tarantino']
        ];
        
        $result = $service->semanticSearch('mafia movies', $items, 'en', 2);
        
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('similarities', $result);
        $this->assertIsArray($result['results']);
        $this->assertIsArray($result['similarities']);
        $this->assertCount(2, $result['results']);
        
        // Test text embedding
        $embedding = $service->embedText('test movie');
        $this->assertArrayHasKey('embedding', $embedding);
        $this->assertArrayHasKey('dimension', $embedding);
    }
}