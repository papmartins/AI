<?php

namespace App\Services;

use Rubix\ML\Transformers\Transformer;
use Rubix\ML\Datasets\Dataset;

class TfIdfVectorizer implements Transformer, \Stringable
{
    protected $vocabulary = [];
    protected $idf = [];
    protected $fitCalled = false;
    
    public function fit($dataset) : bool
    {
        // Accept both Dataset and array
        $samples = is_array($dataset) ? $dataset : $dataset->samples();
        $documentCount = count($samples);
        
        // Build vocabulary
        $vocabulary = [];
        foreach ($samples as $sample) {
            if (is_string($sample)) {
                $tokens = $this->tokenize($sample);
                foreach ($tokens as $token) {
                    if (!isset($vocabulary[$token])) {
                        $vocabulary[$token] = 0;
                    }
                    $vocabulary[$token]++;
                }
            }
        }
        
        $this->vocabulary = array_keys($vocabulary);
        
        // Calculate IDF
        foreach ($this->vocabulary as $term) {
            $documentsWithTerm = 0;
            foreach ($samples as $sample) {
                if (is_string($sample) && str_contains(strtolower($sample), strtolower($term))) {
                    $documentsWithTerm++;
                }
            }
            $this->idf[$term] = log(($documentCount + 1) / ($documentsWithTerm + 1)) + 1;
        }
        
        $this->fitCalled = true;
        return true;
    }
    
    public function transform(array &$samples): void
    {
        if (!$this->fitCalled) {
            // Fit on the samples if not already fitted
            $this->fit($samples);
        }
        
        if (empty($this->vocabulary)) {
            return;
        }
        
        $transformedSamples = [];
        foreach ($samples as $sample) {
            if (is_string($sample)) {
                $vector = $this->textToVector($sample);
                // Ensure we have at least one feature
                if (empty($vector)) {
                    $vector = array_fill(0, count($this->vocabulary), 0);
                }
                $transformedSamples[] = $vector;
            } else {
                $transformedSamples[] = $sample;
            }
        }
        
        $samples = $transformedSamples;
    }
    
    protected function tokenize(string $text) : array
    {
        // Simple tokenizer
        $text = strtolower($text);
        $text = preg_replace('/[^\pL\s]/u', '', $text);
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($tokens, function($token) {
            return strlen($token) > 2; // Remove very short tokens
        });
    }
    
    protected function textToVector(string $text) : array
    {
        $tokens = $this->tokenize($text);
        $tokenCounts = array_count_values($tokens);
        
        $vector = [];
        foreach ($this->vocabulary as $index => $term) {
            $tf = isset($tokenCounts[$term]) ? $tokenCounts[$term] : 0;
            $tfidf = $tf * ($this->idf[$term] ?? 1);
            $vector[$index] = $tfidf; // Use index as key for numerical array
        }
        
        return $vector;
    }
    
    public function getVocabulary(): array
    {
        return $this->vocabulary;
    }
    
    public function compatibility(): array
    {
        return ['tfidf_vectorizer' => '1.0'];
    }
    
    public function __toString(): string
    {
        return 'TF-IDF Vectorizer';
    }
    
    public function __serialize() : array
    {
        return [
            'vocabulary' => $this->vocabulary,
            'idf' => $this->idf,
            'fitCalled' => $this->fitCalled
        ];
    }
    
    public function __unserialize(array $data) : void
    {
        $this->vocabulary = $data['vocabulary'];
        $this->idf = $data['idf'];
        $this->fitCalled = $data['fitCalled'];
    }
}