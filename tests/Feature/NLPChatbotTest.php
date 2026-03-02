<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\NLPChatbotService;
use App\Services\IntentClassifierService;
use App\Services\EntityExtractorService;

class NLPChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected $chatbotService;
    protected $intentClassifier;
    protected $entityExtractor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->intentClassifier = new IntentClassifierService();
        $this->entityExtractor = new EntityExtractorService();
        
        // Create NLPChatbotService with its dependency
        $movieRecommender = $this->app->make(\App\Services\MovieRecommender::class);
        $this->chatbotService = new NLPChatbotService($movieRecommender);
    }

    /** @test */
    public function it_detects_portuguese_language_correctly()
    {
        $portugueseQuestions = [
            'Filmes protagonizados por Bruce Willis',
            'Quais filmes tem o ator Tom Hanks?',
            'Mostre filmes do diretor Christopher Nolan',
            'Filmes de ação populares',
            'Filmes lançados em 2015',
            'Filmes bem avaliados'
        ];

        foreach ($portugueseQuestions as $question) {
            $language = $this->intentClassifier->detectLanguage($question);
            $this->assertEquals('pt', $language, "Failed to detect Portuguese for: {$question}");
        }
    }

    /** @test */
    public function it_detects_english_language_correctly()
    {
        $englishQuestions = [
            'Movies starring Bruce Willis',
            'Which movies have actor Tom Hanks?',
            'Show movies by director Christopher Nolan',
            'Popular action movies',
            'Movies released in 2015',
            'Highly rated movies'
        ];

        foreach ($englishQuestions as $question) {
            $language = $this->intentClassifier->detectLanguage($question);
            $this->assertEquals('en', $language, "Failed to detect English for: {$question}");
        }
    }

    /** @test */
    public function it_detects_spanish_language_correctly()
    {
        $spanishQuestions = [
            'Películas protagonizadas por Bruce Willis',
            '¿Qué películas tienen al actor Tom Hanks?',
            'Muestra películas del director Christopher Nolan',
            'Películas de acción populares',
            'Películas estrenadas en 2015',
            'Películas bien valoradas'
        ];

        foreach ($spanishQuestions as $question) {
            $language = $this->intentClassifier->detectLanguage($question);
            $this->assertEquals('es', $language, "Failed to detect Spanish for: {$question}");
        }
    }

    /** @test */
    public function it_classifies_actor_intent_correctly()
    {
        $actorQuestions = [
            ['question' => 'Filmes protagonizados por Bruce Willis', 'language' => 'pt'],
            ['question' => 'Movies starring Bruce Willis', 'language' => 'en'],
            ['question' => 'Películas con Brad Pitt', 'language' => 'es'],
        ];

        foreach ($actorQuestions as $testCase) {
            $result = $this->intentClassifier->classifyMultipleIntents($testCase['question']);
            $this->assertContains('actor', $result['intents'], 
                "Failed to classify actor intent for: {$testCase['question']}");
        }
    }

    /** @test */
    public function it_classifies_director_intent_correctly()
    {
        $directorQuestions = [
            ['question' => 'Filmes dirigidos por Christopher Nolan', 'language' => 'pt'],
            ['question' => 'Movies directed by Steven Spielberg', 'language' => 'en'],
            ['question' => 'Películas dirigidas por Alfonso Cuarón', 'language' => 'es'],
        ];

        foreach ($directorQuestions as $testCase) {
            $result = $this->intentClassifier->classifyMultipleIntents($testCase['question']);
            $this->assertContains('director', $result['intents'],
                "Failed to classify director intent for: {$testCase['question']}");
        }
    }

    /** @test */
    public function it_classifies_genre_intent_correctly()
    {
        $genreQuestions = [
            ['question' => 'Filmes de ação', 'language' => 'pt'],
            ['question' => 'Action movies', 'language' => 'en'],
            ['question' => 'Películas de terror', 'language' => 'es'],
        ];

        foreach ($genreQuestions as $testCase) {
            $result = $this->intentClassifier->classifyMultipleIntents($testCase['question']);
            $this->assertContains('genre', $result['intents'],
                "Failed to classify genre intent for: {$testCase['question']}");
        }
    }

    /** @test */
    public function it_extracts_actor_names_correctly()
    {
        $actorTests = [
            ['question' => 'Filmes protagonizados por Bruce Willis', 'expected' => 'Bruce Willis', 'language' => 'pt'],
            ['question' => 'Movies starring Tom Hanks', 'expected' => 'Tom Hanks', 'language' => 'en'],
            ['question' => 'Películas con Brad Pitt', 'expected' => 'Brad Pitt', 'language' => 'es'],
        ];

        foreach ($actorTests as $testCase) {
            $actorName = $this->entityExtractor->extractPersonName($testCase['question'], $testCase['language']);
            $this->assertEquals($testCase['expected'], $actorName,
                "Failed to extract actor name from: {$testCase['question']}. Got: '{$actorName}'");
        }
    }

    /** @test */
    public function it_extracts_director_names_correctly()
    {
        $directorTests = [
            ['question' => 'Filmes dirigidos por Christopher Nolan', 'expected' => 'Christopher Nolan', 'language' => 'pt'],
            ['question' => 'Movies directed by Steven Spielberg', 'expected' => 'Steven Spielberg', 'language' => 'en'],
            ['question' => 'Películas dirigidas por Alfonso Cuarón', 'expected' => 'Alfonso Cuarón', 'language' => 'es'],
        ];

        foreach ($directorTests as $testCase) {
            $directorName = $this->entityExtractor->extractPersonName($testCase['question'], $testCase['language']);
            $this->assertEquals($testCase['expected'], $directorName,
                "Failed to extract director name from: {$testCase['question']}. Got: '{$directorName}'");
        }
    }

    /** @test */
    public function it_handles_compound_questions_correctly()
    {
        $compoundTests = [
            [
                'question' => 'Filmes de ação com Bruce Willis',
                'expectedIntents' => ['genre', 'actor'],
                'language' => 'pt'
            ],
            [
                'question' => 'Action movies with Tom Hanks',
                'expectedIntents' => ['genre', 'actor'],
                'language' => 'en'
            ],
        ];

        foreach ($compoundTests as $testCase) {
            $result = $this->intentClassifier->classifyMultipleIntents($testCase['question']);
            
            foreach ($testCase['expectedIntents'] as $expectedIntent) {
                $this->assertContains($expectedIntent, $result['intents'],
                    "Failed to classify compound intent '{$expectedIntent}' from: {$testCase['question']}");
            }
        }
    }



    /** @test */
    public function it_filters_out_common_words_from_entity_extraction()
    {
        $commonWordTests = [
            ['question' => 'Filmes com atores famosos', 'language' => 'pt', 'shouldBeEmpty' => true],
            ['question' => 'Movies with popular actors', 'language' => 'en', 'shouldBeEmpty' => true],
        ];

        foreach ($commonWordTests as $testCase) {
            $entity = $this->entityExtractor->extractPersonName($testCase['question'], $testCase['language']);
            if ($testCase['shouldBeEmpty']) {
                $this->assertEmpty($entity, "Should not extract entity from: {$testCase['question']}");
            }
        }
    }
}