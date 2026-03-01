<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Rubix\ML\Tokenizers\Word;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;
use Rubix\ML\Classifiers\KNearestNeighbors;

/**
 * Service responsible for classifying user intents from questions
 */
class IntentClassifierService
{
    protected $tokenizer;
    protected $intentionClassifier;
    protected $supportedCompoundIntents;
    protected $supportedLanguages;
    protected $featureKeywordsCache;
    protected $trainingSamplesCache;
    protected $modelPath;
    
    public function __construct()
    {
        $this->tokenizer = new Word();
        $this->modelPath = storage_path(config('ml.nlp.model_path') ?? 'app/nlp_intention_classifier.model');
        $this->supportedLanguages = config('ml.nlp.supported_languages', ['pt', 'en', 'es']);
        $this->featureKeywordsCache = [];
        $this->trainingSamplesCache = [];
        
        $this->supportedCompoundIntents = config('ml.nlp.compound_intents', [
            ['actor', 'director'],
            ['actor', 'genre'],
            ['actor', 'year'],
            ['director', 'genre'],
            ['director', 'year'],
            ['genre', 'year'],
            ['genre', 'rating']
        ]);
        
        // $this->loadOrTrainIntentionClassifier();
    }
    
    /**
     * Get the NLP model file path
     *
     * @return string
     */
    public function getModelPath(): string
    {
        return $this->modelPath;
    }
    
    /**
     * Classify multiple intents for compound questions
     *
     * @param string $question User's question
     * @return array Detected intents with condition type and snippets
     */
    public function classifyMultipleIntents(string $question): array
    {
        try {
            $language = $this->detectLanguage($question);
            
            // First check if this is a compound question
            $lowerQuestion = strtolower($question);
            
            $hasCompoundIndicators = 
                (str_contains($lowerQuestion, ' and ') && $this->hasMultipleCriteria($question, $language)) ||
                (str_contains($lowerQuestion, ' e ') && $this->hasMultipleCriteria($question, $language)) ||
                (str_contains($lowerQuestion, ' y ') && $this->hasMultipleCriteria($question, $language)) ||
                (str_contains($lowerQuestion, ' or ') && $this->hasMultipleCriteria($question, $language)) ||
                (str_contains($lowerQuestion, ' ou ') && $this->hasMultipleCriteria($question, $language)) ||
                // Also check for implicit compound questions without connecting words
                $this->hasMultipleCriteria($question, $language);
            
            if ($hasCompoundIndicators) {
                return $this->detectCompoundIntents($question, $language);
            }
            
            // If not a compound question, try single intent classification
            try {
                $intention = $this->classifyIntention($question);
                
                if ($intention !== 'unknown') {
                    return ['intents' => [$intention], 'snippets' => [], 'condition' => 'AND'];
                }
            } catch (\Exception $e) {
                $tokens = $this->tokenizer->tokenize(strtolower($question));
                $intention = $this->classifyIntentionFallback($tokens, $question);
                
                if ($intention !== 'unknown') {
                    return ['intents' => [$intention], 'snippets' => [], 'condition' => 'AND'];
                }
            }
            
            return ['intents' => ['unknown'], 'snippets' => [], 'condition' => 'AND'];
        } catch (\Exception $e) {
            return ['intents' => ['unknown'], 'snippets' => [], 'condition' => 'AND'];
        }
    }
    
    /**
     * Classify single intent using ML classifier
     *
     * @param string $question User's question
     * @return string Detected intention
     */
    public function classifyIntention(string $question): string
    {
        try {
            $language = $this->detectLanguage($question);
            $featureVector = $this->generateFeatureVector($question, $language);
            $dataset = new Labeled([$featureVector], ['temp']);
            
            $intention = $this->intentionClassifier->predict($dataset);
            return $intention[0] ?? 'unknown';
        } catch (\Exception $e) {
            $tokens = $this->tokenizer->tokenize(strtolower($question));
            return $this->classifyIntentionFallback($tokens, $question);
        }
    }
    
    // ... (other methods from the original service related to intent classification)
    
    protected function detectCompoundIntents(string $question, string $language): array
    {
        $lowerQuestion = strtolower($question);
        $tokens = $this->tokenizer->tokenize($lowerQuestion);
        $keywords = $this->getFeatureKeywords($language);
        
        $detectedIntents = [];
        
        $hasOrCondition = str_contains($lowerQuestion, ' ou ') || str_contains($lowerQuestion, ' or ');
        $hasAndCondition = str_contains($lowerQuestion, ' e ') || str_contains($lowerQuestion, ' and ') ||
                          str_contains($lowerQuestion, ' y ');
        
        // Check for actor intent
        $hasActorKeywords = 
            in_array($keywords['actor'][0] ?? 'actor', $tokens) ||
            in_array($keywords['actor'][1] ?? 'starred', $tokens) ||
            in_array($keywords['actor'][2] ?? 'cast', $tokens) ||
            str_contains($lowerQuestion, 'have ') ||
            str_contains($lowerQuestion, 'with ') ||
            str_contains($lowerQuestion, 'têm ') ||
            str_contains($lowerQuestion, 'com ');
        
        if ($hasActorKeywords) {
            $detectedIntents['actor'] = $hasActorKeywords;
        }
        
        // Check for director intent
        $hasDirectorKeywords = 
            in_array($keywords['director'][0] ?? 'director', $tokens) ||
            in_array($keywords['director'][1] ?? 'directed', $tokens) ||
            str_contains($lowerQuestion, 'directed by') ||
            str_contains($lowerQuestion, 'dirigidos por') ||
            str_contains($lowerQuestion, 'dirigido por');
        
        // If we have multiple person names and one of them is a known director,
        // or if the question structure suggests actor+director
        $personNameCount = preg_match_all('/\b[A-Z][a-z]+\s[A-Z][a-z]+\b/', $question);
        if (!$hasDirectorKeywords && $personNameCount >= 2 && $hasActorKeywords) {
            // Assume second person might be a director in compound questions
            $hasDirectorKeywords = true;
        }
        
        if ($hasDirectorKeywords) {
            $detectedIntents['director'] = $hasDirectorKeywords;
        }
        
        // Check for genre intent
        $hasGenreKeywords = false;
        $genreKeywords = $keywords['genre'] ?? ['action', 'comedy', 'horror'];
        foreach ($genreKeywords as $keyword) {
            if (in_array($keyword, $tokens) || str_contains($lowerQuestion, $keyword)) {
                $hasGenreKeywords = true;
                break;
            }
        }
        
        // Additional check for common genre patterns
        if (!$hasGenreKeywords && str_contains($lowerQuestion, ' are ')) {
            // Check if there's a genre word after "are"
            $parts = explode(' are ', $lowerQuestion);
            if (isset($parts[1])) {
                $afterAre = $parts[1];
                foreach ($genreKeywords as $keyword) {
                    if (str_contains($afterAre, $keyword)) {
                        $hasGenreKeywords = true;
                        break;
                    }
                }
            }
        }
        
        if ($hasGenreKeywords) {
            $detectedIntents['genre'] = $hasGenreKeywords;
        }
        
        // Check for year intent
        $hasYearKeywords = 
            preg_match('/\d{4}/', $question) ||
            in_array($keywords['year'][0] ?? 'released', $tokens) ||
            str_contains($lowerQuestion, ' from ') ||
            str_contains($lowerQuestion, ' de ');
        
        if ($hasYearKeywords) {
            $detectedIntents['year'] = $hasYearKeywords;
        }
        
        // Check for rating intent
        $hasRatingKeywords = 
            in_array($keywords['rating'][0] ?? 'rated', $tokens) ||
            in_array($keywords['rating'][1] ?? 'good', $tokens) ||
            str_contains($lowerQuestion, 'highly rated') ||
            str_contains($lowerQuestion, 'bem avaliados') ||
            str_contains($lowerQuestion, 'bien valoradas');
        
        if ($hasRatingKeywords) {
            $detectedIntents['rating'] = $hasRatingKeywords;
        }
        
        $conditionType = 'AND';
        if ($hasOrCondition) {
            $conditionType = 'OR';
        } elseif ($hasAndCondition) {
            $conditionType = 'AND';
        }
        
        $finalIntents = array_keys(array_filter($detectedIntents));
        
        // Extract text snippets for each detected intent
        $intentSnippets = [];
        if (in_array('actor', $finalIntents)) {
            $intentSnippets['actor'] = $this->extractIntentSnippet($question, 'actor', $language);
        }
        if (in_array('director', $finalIntents)) {
            $intentSnippets['director'] = $this->extractIntentSnippet($question, 'director', $language);
        }
        if (in_array('genre', $finalIntents)) {
            $intentSnippets['genre'] = $this->extractIntentSnippet($question, 'genre', $language);
        }
        if (in_array('year', $finalIntents)) {
            $intentSnippets['year'] = $this->extractIntentSnippet($question, 'year', $language);
        }
        
        if (count($finalIntents) > 1) {
            foreach ($this->supportedCompoundIntents as $supportedCombo) {
                if (count(array_intersect($finalIntents, $supportedCombo)) === count($supportedCombo)) {
                    return [
                        'intents' => $supportedCombo,
                        'snippets' => $intentSnippets,
                        'condition' => $conditionType
                    ];
                }
            }
        }
        
        return [
            'intents' => [$finalIntents[0] ?? 'unknown'],
            'snippets' => isset($intentSnippets[$finalIntents[0]]) ? [$intentSnippets[$finalIntents[0]]] : [],
            'condition' => 'AND'
        ];
    }
    
    // ... (rest of the methods would be moved here)
    
    protected function extractIntentSnippet(string $question, string $intent, string $language): string
    {
        $lowerQuestion = strtolower($question);
        $originalTokens = $this->tokenizer->tokenize($question);
        $tokens = $this->tokenizer->tokenize($lowerQuestion);
        
        // Get keywords for the specific intent
        $keywords = $this->getFeatureKeywords($language);
        $intentKeywords = $keywords[$intent] ?? [];
        
        // Special handling for actor/director extraction in Portuguese
        if ($language === 'pt' && $intent === 'actor' && str_contains($lowerQuestion, 'ator')) {
            if (preg_match('/e o ator (\w+\s+\w+)/', $question, $matches)) {
                return $matches[1]; // Extract name after "e o ator"
            }
        }
        
        if ($language === 'pt' && $intent === 'director' && str_contains($lowerQuestion, 'realizador')) {
            if (preg_match('/com o realizador (\w+\s+\w+)/', $question, $matches)) {
                return $matches[1]; // Extract name after "com o realizador"
            }
        }
        
        // Find the position of intent keywords
        $keywordPositions = [];
        foreach ($tokens as $index => $token) {
            if (in_array($token, $intentKeywords)) {
                $keywordPositions[] = $index;
            }
        }
        
        if (empty($keywordPositions)) {
            return $question; // Return full question if no keywords found
        }
        
        // Extract snippet around the keywords
        $startPos = max(0, min($keywordPositions) - 2);
        $endPos = min(count($tokens) - 1, max($keywordPositions) + 3);
        
        $snippetTokens = array_slice($tokens, $startPos, $endPos - $startPos + 1);
        return implode(' ', $snippetTokens);
    }
    
    protected function getFeatureKeywords(string $language): array
    {
        if (isset($this->featureKeywordsCache[$language])) {
            return $this->featureKeywordsCache[$language];
        }
        
        $keywords = trans("chatbot/feature_keywords", [], $language);
        $this->featureKeywordsCache[$language] = $keywords;
        return $keywords;
    }
    
    protected function hasMultipleCriteria(string $question, string $language): bool
    {
        $lowerQuestion = strtolower($question);
        
        // Check for multiple person names (potential actor/director)
        $personNameCount = preg_match_all('/\b[A-Z][a-z]+\s[A-Z][a-z]+\b/', $question);
        
        // Check for genre keywords
        $genreKeywords = $this->getFeatureKeywords($language)['genre'] ?? [];
        $hasGenre = false;
        foreach ($genreKeywords as $keyword) {
            if (str_contains($lowerQuestion, $keyword)) {
                $hasGenre = true;
                break;
            }
        }
        
        // Check for year patterns
        $hasYear = preg_match('/\d{4}/', $lowerQuestion);
        
        // Check for director-related keywords
        $directorKeywords = $this->getFeatureKeywords($language)['director'] ?? [];
        $hasDirectorKeywords = false;
        foreach ($directorKeywords as $keyword) {
            if (str_contains($lowerQuestion, $keyword)) {
                $hasDirectorKeywords = true;
                break;
            }
        }
        
        // More flexible criteria:
        // 1. Multiple person names (likely actor + director)
        // 2. Person name + genre/year/director keywords
        // 3. Explicit mention of both actor and director
        return ($personNameCount >= 2) ||
               ($personNameCount >= 1 && ($hasGenre || $hasYear || $hasDirectorKeywords)) ||
               (str_contains($lowerQuestion, 'actor') && str_contains($lowerQuestion, 'director')) ||
               (str_contains($lowerQuestion, 'ator') && str_contains($lowerQuestion, 'diretor')) ||
               (str_contains($lowerQuestion, 'actor') && str_contains($lowerQuestion, 'género'));
    }
    
    public function detectLanguage(string $question): string
    {
        $tokens = $this->tokenizer->tokenize(strtolower($question));
        
        $portugueseWords = ['que', 'quais', 'filmes', 'o', 'a', 'do', 'da', 'com', 'por', 'ator', 'diretor', 'título', 'gênero', 'ano'];
        $englishWords = ['what', 'which', 'who', 'the', 'movies', 'with', 'by', 'actor', 'director', 'title', 'genre', 'year'];
        $spanishWords = ['qué', 'cuáles', 'películas', 'el', 'la', 'de', 'con', 'por', 'actor', 'director', 'título', 'género', 'año'];
        
        $ptCount = count(array_intersect($tokens, $portugueseWords));
        $enCount = count(array_intersect($tokens, $englishWords));
        $esCount = count(array_intersect($tokens, $spanishWords));
        
        if ($ptCount > $enCount && $ptCount > $esCount) return 'pt';
        if ($esCount > $ptCount && $esCount > $enCount) return 'es';
        return 'en'; // Default to English
    }
    
    protected function classifyIntentionFallback(array $tokens, string $question): string
    {
        $lowerQuestion = strtolower($question);
        
        // Check for actor-related keywords
        if (str_contains($lowerQuestion, 'star') || str_contains($lowerQuestion, 'actor') || 
            str_contains($lowerQuestion, 'actress') || str_contains($lowerQuestion, 'cast') ||
            str_contains($lowerQuestion, 'have ') || str_contains($lowerQuestion, 'with ')) {
            return 'actor';
        }
        
        // Check for director-related keywords
        if (str_contains($lowerQuestion, 'direct') || str_contains($lowerQuestion, 'directed by')) {
            return 'director';
        }
        
        // Check for genre-related keywords
        if (str_contains($lowerQuestion, 'genre') || str_contains($lowerQuestion, 'action') ||
            str_contains($lowerQuestion, 'comedy') || str_contains($lowerQuestion, 'horror') ||
            str_contains($lowerQuestion, 'drama') || str_contains($lowerQuestion, 'romance')) {
            return 'genre';
        }
        
        // Check for year-related keywords
        if (preg_match('/\d{4}/', $lowerQuestion) || str_contains($lowerQuestion, 'year') ||
            str_contains($lowerQuestion, 'from ') || str_contains($lowerQuestion, 'released')) {
            return 'year';
        }
        
        // Check for rating-related keywords
        if (str_contains($lowerQuestion, 'rate') || str_contains($lowerQuestion, 'good') ||
            str_contains($lowerQuestion, 'high') || str_contains($lowerQuestion, 'best')) {
            return 'rating';
        }
        
        // Check for title-related keywords
        if (str_contains($lowerQuestion, 'title') || str_contains($lowerQuestion, 'name')) {
            return 'title';
        }
        
        // Check for recommendation-related keywords
        if (str_contains($lowerQuestion, 'recommend') || str_contains($lowerQuestion, 'suggest') ||
            str_contains($lowerQuestion, 'popular') || str_contains($lowerQuestion, 'good movies')) {
            return 'recommendation';
        }
        
        return 'unknown';
    }
    
    protected function generateFeatureVector(string $question, string $language): array
    {
        $featureVector = [];
        $lowerQuestion = strtolower($question);
        $tokens = $this->tokenizer->tokenize($lowerQuestion);
        
        // Get all keywords for the language
        $keywords = $this->getFeatureKeywords($language);
        
        // Create feature vector based on keyword presence
        foreach ($keywords as $intent => $intentKeywords) {
            $count = 0;
            foreach ($intentKeywords as $keyword) {
                if (in_array($keyword, $tokens)) {
                    $count++;
                }
            }
            $featureVector[$intent] = $count;
        }
        
        // Add some additional features
        $featureVector['has_year'] = preg_match('/\d{4}/', $lowerQuestion) ? 1 : 0;
        $featureVector['has_question_word'] = (str_contains($lowerQuestion, 'what') || 
                                                  str_contains($lowerQuestion, 'which') || 
                                                  str_contains($lowerQuestion, 'who')) ? 1 : 0;
        $featureVector['question_length'] = count($tokens);
        
        return $featureVector;
    }
    
    public function loadOrTrainIntentionClassifier(): PersistentModel
    {
        try {
            if (file_exists($this->modelPath)) {
                Log::info('Loading existing NLP model from: ' . $this->modelPath);
                $estimator = PersistentModel::load(new Filesystem($this->modelPath));
            } else {
                $estimator = $this->train();
            }
            $this->intentionClassifier = $estimator;
            return $estimator;
        } catch (\Exception $e) {
            // Fallback to training if loading fails
            $estimator = $this->train();
            $this->intentionClassifier = $estimator;
            return $estimator;
        }
    }

    /**
     * Retrain the NLP model
     *
     * @return array Training result information
     */
    public function retrain(): array
    {
        $startTime = microtime(true);
        
        try {
            // Force retraining by training a new model
            $estimator = $this->train();
            
            $trainingTime = microtime(true) - $startTime;
            
            return [
                'success' => true,
                'message' => 'NLP model retrained successfully',
                'training_time' => round($trainingTime, 2),
                'model_path' => $this->modelPath
            ];
        } catch (\Exception $e) {
            Log::error('NLP model retraining failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to retrain NLP model',
                'error' => $e->getMessage(),
                'training_time' => round(microtime(true) - $startTime, 2)
            ];
        }
    }
    
    protected function train(): PersistentModel
    {
        $samples = [];
        $labels = [];
        
        foreach ($this->supportedLanguages as $language) {
            $languageSamples = $this->getTrainingSamples($language);
            
            foreach ($languageSamples as $intention => $intentionSamples) {
                foreach ($intentionSamples as $sample) {
                    $samples[] = $sample;
                    $labels[] = $intention;
                }
            }
        }
        
        $features = [];
        foreach ($samples as $sample) {
            $language = $this->detectLanguage($sample);
            $featureVector = $this->generateFeatureVector($sample, $language);
            $features[] = $featureVector;
        }
        
        $dataset = new Labeled($features, $labels);
        
        $estimator = new PersistentModel(
            new KNearestNeighbors(3, false),
            new Filesystem($this->modelPath)
        );
        
        $estimator->train($dataset);
        
        try {
            // Ensure directory exists before saving
            $directory = dirname($this->modelPath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory: {$directory}");
                }
            }
            
            // Check if directory is writable
            if (!is_writable($directory)) {
                throw new \RuntimeException("Directory is not writable: {$directory}");
            }
            Log::info('Saving NLP model to: ' . $this->modelPath);
            Log::info('Model directory: ' . $directory);
            $estimator->save();
        } catch (\Exception $e) {
            Log::error('Failed to save NLP model: ' . $e->getMessage());
            // Ignore save errors in test environment
        }
        
        return $estimator;
    }
    
    protected function getTrainingSamples(string $language): array
    {
        // Load from language files with fallback to Portuguese
        $samples = trans("chatbot/training_samples", [], $language);
        
        if (empty($samples) || !is_array($samples)) {
            $samples = trans("chatbot/training_samples", [], 'pt');
        }
        
        return $samples;
    }
}
