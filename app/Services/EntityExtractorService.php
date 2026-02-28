<?php

namespace App\Services;

use App\Models\Movie;
use Rubix\ML\Tokenizers\Word;

/**
 * Service responsible for extracting entities from user questions
 */
class EntityExtractorService
{
    protected $tokenizer;
    
    public function __construct()
    {
        $this->tokenizer = new Word();
    }
    
    /**
     * Extract entities for a specific intent
     *
     * @param string $text Text to extract from
     * @param string $intent Intent type
     * @param string $language Language code
     * @return array Extracted entities
     */
    public function extractEntitiesForIntent(string $text, string $intent, string $language): array
    {
        switch ($intent) {
            case 'actor':
                return [$this->extractPersonName($text)];
                
            case 'director':
                return [$this->extractPersonName($text)];
                
            case 'genre':
                return $this->extractGenreKeywords($text);
                
            case 'year':
                $yearInfo = $this->extractYearInformation($text);
                return [$yearInfo['specific_year'] ?? ($yearInfo['year_range'][0] ?? null)];
                
            case 'rating':
                return ['high']; // For now, assume high rating
                
            default:
                return [];
        }
    }
    
    /**
     * Extract person names from questions
     *
     * @param string $question User's question
     * @return string Extracted person name
     */
    public function extractPersonName(string $question): string
    {
        $tokens = $this->tokenizer->tokenize(strtolower($question));
        $originalTokens = $this->tokenizer->tokenize($question);
        
        $stopWords = ['que', 'filmes', 'existem', 'realizados', 'por', 'com', 'o', 'a', 'os', 'as', 'do', 'da', 'de', 'em', 'para', 'com', 'sem', 'quem', 'dirigiu', 'têm', 'o', 'a', 'what', 'movies', 'with', 'the', 'who', 'directed', 'which', 'films', 'show', 'me', 'featuring'];
        
        $potentialNames = [];
        $currentName = '';
        $foundPor = false;
        $foundWith = false;
        $foundBy = false;
        $foundHave = false;
        
        // Try to find names after "por", "with" or "by"
        foreach ($tokens as $index => $token) {
            if (in_array($token, $stopWords)) {
                if ($currentName && ($foundPor || $foundWith || $foundBy)) {
                    $potentialNames[] = $currentName;
                    $currentName = '';
                }
                if ($token === 'por') $foundPor = true;
                if ($token === 'with') $foundWith = true;
                if ($token === 'by') $foundBy = true;
                if ($token === 'have' || $token === 'has') $foundHave = true;
                $currentName = '';
                continue;
            }
            
            if ($foundPor || $foundWith || $foundBy) {
                $currentName = $currentName ? $currentName . ' ' . $token : $token;
            }
        }
        
        // Add complete name if exists
        if ($foundPor || $foundWith || $foundBy || $foundHave) {
            $startCapture = false;
            $completeName = '';
            foreach ($originalTokens as $token) {
                $lowerToken = strtolower($token);
                if (in_array($lowerToken, ['por', 'with', 'by', 'have', 'has'])) {
                    $startCapture = true;
                    continue;
                }
                if ($startCapture) {
                    $completeName = $completeName ? $completeName . ' ' . $token : $token;
                }
            }
            if ($completeName) $potentialNames[] = $completeName;
        }
        
        // Try to extract proper names from original question
        if (empty($potentialNames)) {
            $currentName = '';
            foreach ($originalTokens as $token) {
                if (!in_array(strtolower($token), $stopWords) && ctype_upper($token[0]) && strlen($token) > 1) {
                    if (preg_match('/^[A-Za-zÁáÀàÃãÂâÉéÈèÊêÍíÌìÎîÓóÒòÕõÔôÚúÙùÛûÇç\s\-]+$/', $token)) {
                        if ($currentName) {
                            $currentName .= ' ' . $token;
                            $potentialNames[] = $currentName;
                            break;
                        } else {
                            $currentName = $token;
                        }
                    }
                } else {
                    if ($currentName) {
                        $potentialNames[] = $currentName;
                        break;
                    }
                }
            }
            if ($currentName && empty($potentialNames)) {
                $potentialNames[] = $currentName;
            }
        }
        
        return $potentialNames[0] ?? '';
    }
    
    /**
     * Extract genre keywords from questions
     *
     * @param string $question User's question
     * @return array Extracted genre keywords
     */
    public function extractGenreKeywords(string $question): array
    {
        $lowerQuestion = strtolower($question);
        
        $genreKeywords = [
            'pt' => ['ação', 'comédia', 'terror', 'romance', 'ficção', 'drama', 'aventura', 'suspense', 'animação'],
            'en' => ['action', 'comedy', 'horror', 'romance', 'science', 'drama', 'adventure', 'thriller', 'animation'],
            'es' => ['acción', 'comedia', 'terror', 'romance', 'ciencia', 'drama', 'aventura', 'suspense', 'animación']
        ];
        
        $detectedLanguage = $this->detectLanguage($question);
        $foundKeywords = [];
        
        foreach ($genreKeywords[$detectedLanguage] as $keyword) {
            if (str_contains($lowerQuestion, $keyword)) {
                $foundKeywords[] = $keyword;
            }
        }
        
        return $foundKeywords;
    }
    
    /**
     * Extract year information from questions
     *
     * @param string $question User's question
     * @return array Extracted year information
     */
    public function extractYearInformation(string $question): array
    {
        $lowerQuestion = strtolower($question);
        $result = [];
        
        if (preg_match('/(\d{4})/', $lowerQuestion, $matches)) {
            $result['specific_year'] = (int)$matches[1];
            return $result;
        }
        
        if (preg_match('/(\d{4})\s*(a|até|to)\s*(\d{4})/', $lowerQuestion, $matches)) {
            $result['year_range'] = [(int)$matches[1], (int)$matches[3]];
            return $result;
        }
        
        if (preg_match('/anos?\s*(\d{2})/', $lowerQuestion, $matches)) {
            $decade = (int)$matches[1];
            $startYear = $decade * 10;
            $result['year_range'] = [$startYear, $startYear + 9];
            return $result;
        }
        
        if (str_contains($lowerQuestion, 'recent') || str_contains($lowerQuestion, 'recente')) {
            $result['recent'] = true;
            return $result;
        }
        
        if (str_contains($lowerQuestion, 'old') || str_contains($lowerQuestion, 'antig')) {
            $result['old'] = true;
            return $result;
        }
        
        return [];
    }
    
    /**
     * Extract title keywords from questions
     *
     * @param string $question User's question
     * @return array Extracted title keywords
     */
    public function extractTitleKeywords(string $question): array
    {
        $lowerQuestion = strtolower($question);
        
        // Remove common question words
        $keywordsToRemove = ['what', 'which', 'movies', 'film', 'films', 'with', 'the', 'have', 'has', 'in', 'title'];
        $cleanedQuestion = str_replace($keywordsToRemove, '', $lowerQuestion);
        
        // Extract potential title keywords (words that are likely to be part of a title)
        $potentialKeywords = [];
        $words = explode(' ', trim($cleanedQuestion));
        
        foreach ($words as $word) {
            if (strlen($word) > 2 && !is_numeric($word)) {
                $potentialKeywords[] = $word;
            }
        }
        
        return array_filter($potentialKeywords);
    }
    
    /**
     * Extract year from questions (simplified version)
     *
     * @param string $question User's question
     * @return string|null Extracted year or null
     */
    public function extractYear(string $question): ?string
    {
        if (preg_match('/(\d{4})/', $question, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Detect the language of the question
     *
     * @param string $question User's question
     * @return string Language code (pt, en, es)
     */
    public function detectLanguage(string $question): string
    {
        $tokens = $this->tokenizer->tokenize(strtolower($question));
        
        $portugueseWords = ['que', 'quais', 'filmes', 'o', 'a', 'do', 'da', 'com', 'por', 'ator', 'diretor', 'título'];
        $englishWords = ['what', 'which', 'who', 'the', 'movies', 'with', 'by', 'actor', 'director', 'title', 'in'];
        $spanishWords = ['qué', 'cuáles', 'películas', 'el', 'la', 'de', 'con', 'por', 'actor', 'director', 'título'];
        
        $ptCount = 0;
        $enCount = 0;
        $esCount = 0;
        
        foreach ($tokens as $token) {
            if (in_array($token, $portugueseWords)) $ptCount++;
            if (in_array($token, $englishWords)) $enCount++;
            if (in_array($token, $spanishWords)) $esCount++;
        }
        
        if ($esCount > $ptCount && $esCount > $enCount) return 'es';
        if ($enCount > $ptCount && $enCount > $esCount) return 'en';
        if ($ptCount > $enCount && $ptCount > $esCount) return 'pt';
        
        return 'pt';
    }
}
