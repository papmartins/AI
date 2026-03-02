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
     * Get trigger words for a specific language
     * 
     * @param string $language Language code (en, pt, es)
     * @return array Array of trigger words
     */
    protected function getTriggerWords(string $language): array
    {
        $filePath = resource_path("lang/{$language}/chatbot/trigger_words.php");
        
        if (file_exists($filePath)) {
            $triggerWords = include $filePath;
            return [
                'all' => array_merge($triggerWords['prepositions'] ?? [], $triggerWords['verbs'] ?? []),
                'prepositions' => $triggerWords['prepositions'] ?? [],
                'verbs' => $triggerWords['verbs'] ?? []
            ];
        }
        
        // Fallback to English if language file doesn't exist
        return [
            'all' => ['by', 'with', 'have', 'has'],
            'prepositions' => ['by', 'with'],
            'verbs' => ['have', 'has']
        ];
    }
    
    /**
     * Get stop words for a specific language
     * 
     * @param string $language Language code (en, pt, es)
     * @return array Array of stop words
     */
    protected function getStopWords(string $language): array
    {
        $filePath = resource_path("lang/{$language}/chatbot/stop_words.php");
        
        if (file_exists($filePath)) {
            $stopWords = include $filePath;
            // Flatten all categories into a single array
            $result = [];
            foreach ($stopWords as $category) {
                if (is_array($category)) {
                    $result = array_merge($result, $category);
                }
            }
            return $result;
        }
        
        // Fallback to English if language file doesn't exist
        return ['the', 'who', 'which', 'me', 'show', 'featuring', 'directed', 'movies', 'films', 'with', 'in', 'for', 'without'];
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
                return [$this->extractPersonName($text, $language)];
                
            case 'director':
                return [$this->extractPersonName($text, $language)];
                
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
    public function extractPersonName(string $question, string $language = 'en'): string
    {
        $tokens = $this->tokenizer->tokenize(strtolower($question));
        $originalTokens = $this->tokenizer->tokenize($question);
        
        $stopWords = $this->getStopWords($language);
        $triggerWords = $this->getTriggerWords($language);
        
        // Common descriptive words that should not be extracted as names
        $descriptiveWords = [
            // Portuguese
            'atores', 'famosos', 'famosas', 'conhecidos', 'conhecidas', 'populares',
            'principais', 'melhores', 'piores', 'novos', 'velhos', 'jovens',
            // English
            'actors', 'famous', 'popular', 'main', 'best', 'worst', 'new', 'old', 'young',
            // Spanish
            'actores', 'famosos', 'populares', 'principales', 'mejores', 'peores', 'nuevos', 'viejos', 'jóvenes'
        ];

        $allStopWords = array_merge($stopWords, $descriptiveWords);
        
        // Convert trigger words to lowercase for case-insensitive comparison
        $allTriggerWordsLower = array_map('strtolower', array_merge(
            $triggerWords['prepositions'] ?? [],
            $triggerWords['verbs'] ?? []
        ));
        
        // Debug: log tokens and trigger words for Portuguese
        // if ($language === 'pt') {
        //     \Log::info("PT Tokens: " . implode(', ', $tokens));
        //     \Log::info("PT Trigger words: " . implode(', ', $allTriggerWordsLower));
        //     \Log::info("PT Stop words: " . implode(', ', $allStopWords));
        // }
        
        $capturedName = '';
        $foundTrigger = false;
        
        foreach ($tokens as $index => $token) {
            // If we find a trigger word, start capturing the NEXT tokens
            if (in_array($token, $allTriggerWordsLower)) {
                $foundTrigger = true;
                $capturedName = ''; // Reset to capture what comes after
                continue; // Skip the trigger word itself
            }
            
            // If we've found a trigger, capture non-stop words
            if ($foundTrigger) {
                if (!in_array($token, $allStopWords)) {
                    if ($capturedName) {
                        $capturedName .= ' ' . $originalTokens[$index];
                    } else {
                        $capturedName = $originalTokens[$index];
                    }
                } else {
                    // Hit a stop word after trigger - stop capturing
                    break;
                }
            }
        }
        
        // If we captured something with a trigger, return it
        if ($capturedName) {
            // Check if this is a known movie title
            $movieTitles = [
                'mad max', 'die hard', 'the matrix', 'inception', 'titanic',
                'avatar', 'jurassic park', 'star wars', 'the godfather', 'pulp fiction'
            ];
            foreach ($movieTitles as $title) {
                // Check if the captured name is part of a movie title
                if (stripos($title, $capturedName) !== false) {
                    return ''; // Return empty to indicate this is a title, not a person name
                }
            }
            // Also check if we captured common words that should be filtered
            $commonNonNames = ['filmes', 'movies', 'peliculas', 'the'];
            if (in_array(strtolower($capturedName), $commonNonNames)) {
                // If we only captured a common word, try fallback extraction
                $fallbackName = $this->extractPersonNameFallback($originalTokens, $stopWords);
                return $fallbackName !== '' ? $fallbackName : '';
            }
            return $capturedName;
        }
        
        // Fallback: try to find proper names (capitalized words)
        return $this->extractPersonNameFallback($originalTokens, $stopWords);
    }
    
    protected function extractPersonNameFallback(array $originalTokens, array $stopWords): string
    {
        // Common descriptive words that should not be extracted as names
        $descriptiveWords = [
            // Portuguese
            'atores', 'famosos', 'famosas', 'conhecidos', 'conhecidas', 'populares',
            'principais', 'melhores', 'piores', 'novos', 'velhos', 'jovens',
            // English
            'actors', 'famous', 'popular', 'main', 'best', 'worst', 'new', 'old', 'young',
            // Spanish
            'actores', 'famosos', 'populares', 'principales', 'mejores', 'peores', 'nuevos', 'viejos', 'jóvenes'
        ];

        $allStopWords = array_merge($stopWords, $descriptiveWords);

        foreach ($originalTokens as $index => $token) {
            $lowerToken = strtolower($token);
            
            // Skip if it's a stop word or descriptive word
            if (in_array($lowerToken, $allStopWords)) {
                continue;
            }
            
            if (ctype_upper($token[0]) && strlen($token) > 1) {
                // Check if next token is also capitalized (for multi-word names)
                $name = $token;
                if ($index + 1 < count($originalTokens)) {
                    $nextToken = $originalTokens[$index + 1];
                    $lowerNextToken = strtolower($nextToken);
                    
                    // Skip if next token is a descriptive word
                    if (!in_array($lowerNextToken, $allStopWords) && ctype_upper($nextToken[0])) {
                        $name .= ' ' . $nextToken;
                        // Check if this is a known movie title
                        $movieTitles = [
                            'mad max', 'die hard', 'the matrix', 'inception', 'titanic',
                            'avatar', 'jurassic park', 'star wars', 'the godfather', 'pulp fiction'
                        ];
                        foreach ($movieTitles as $title) {
                            if (stripos($title, $name) !== false) {
                                return ''; // Return empty to indicate this is a title, not a person name
                            }
                        }
                    }
                }
                
                // Additional check: if we only have descriptive words in the name, return empty
                $nameTokens = explode(' ', $name);
                $hasValidName = false;
                foreach ($nameTokens as $nameToken) {
                    if (!in_array(strtolower($nameToken), $allStopWords)) {
                        $hasValidName = true;
                        break;
                    }
                }
                
                if (!$hasValidName) {
                    return '';
                }
                
                return $name;
            }
        }
        return '';
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
        $detectedLanguage = $this->detectLanguage($question);
        
        // Load genre keywords from language file
        $genreKeywords = $this->getGenreKeywords($detectedLanguage);
        
        $foundKeywords = [];
        
        foreach ($genreKeywords as $keyword) {
            if (str_contains($lowerQuestion, $keyword)) {
                $foundKeywords[] = $keyword;
            }
        }
        
        return $foundKeywords;
    }
    
    /**
     * Get genre keywords from language files
     * 
     * @param string $language Language code
     * @return array Array of genre keywords
     */
    protected function getGenreKeywords(string $language): array
    {
        $filePath = resource_path("lang/{$language}/chatbot/genre_keywords.php");
        
        if (file_exists($filePath)) {
            return include $filePath;
        }
        
        // Fallback keywords if language file doesn't exist
        return ['action', 'comedy', 'horror', 'romance', 'drama', 'adventure', 'thriller', 'animation'];
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
        $originalQuestion = $question;
        $detectedLanguage = $this->detectLanguage($question);
        
        // Get language-specific indicators (like "movie", "film", etc.)
        $titleData = $this->getTitlePatterns($detectedLanguage);
        $indicators = $titleData['indicators'] ?? [];
        
        // Remove common question words and indicators to isolate potential titles
        $stopWords = array_merge(
            $this->getStopWords($detectedLanguage),
            $indicators,
            ['who', 'what', 'when', 'where', 'why', 'how', 'is', 'are', 'was', 'were', 'the', 'a', 'an']
        );
        
        // Tokenize the question
        $tokens = preg_split('/\s+/', $lowerQuestion);
        $potentialKeywords = [];
        
        foreach ($tokens as $token) {
            // Skip stop words and very short words
            if (!in_array($token, $stopWords) && strlen($token) > 2) {
                $potentialKeywords[] = $token;
            }
        }
        
        // If we have potential keywords, return them
        return !empty($potentialKeywords) ? $potentialKeywords : [];
    }
    
    /**
     * Get title patterns from language files
     * 
     * @param string $language Language code
     * @return array Array with indicators and common titles
     */
    protected function getTitlePatterns(string $language): array
    {
        $filePath = resource_path("lang/{$language}/chatbot/title_patterns.php");
        
        if (file_exists($filePath)) {
            return include $filePath;
        }
        
        // Fallback patterns if language file doesn't exist
        return [
            'indicators' => ['title', 'in the title', 'with title', 'titled'],
            'common_titles' => ['die hard', 'matrix', 'inception', 'avatar', 'titanic']
        ];
    }
    
    /**
     * Clean title phrase by removing common prepositions and question words
     * 
     * @param string $phrase Phrase to clean
     * @param string $language Language code
     * @return string Cleaned phrase
     */
    protected function cleanTitlePhrase(string $phrase, string $language): string
    {
        // Remove common prepositions from beginning and end
        $prepositions = ['in', 'the', 'a', 'an', 'o', 'a', 'os', 'as', 'no', 'com', 'with'];
        
        // Remove from beginning
        foreach ($prepositions as $prep) {
            $pattern = '/^' . $prep . '\s+/i';
            $phrase = preg_replace($pattern, '', $phrase);
        }
        
        // Remove from end
        foreach ($prepositions as $prep) {
            $pattern = '/\s+' . $prep . '$/i';
            $phrase = preg_replace($pattern, '', $phrase);
        }
        
        // Remove common question words from beginning
        $questionWords = ['Filmes', 'Movies', 'Que filmes', 'What movies', 'who directed', 'who starred', 'who acted'];
        foreach ($questionWords as $word) {
            $pattern = '/^' . $word . '\s+/i';
            $phrase = preg_replace($pattern, '', $phrase);
        }
        
        // Remove prepositions from beginning again (in case question words were removed)
        foreach ($prepositions as $prep) {
            $pattern = '/^' . $prep . '\s+/i';
            $phrase = preg_replace($pattern, '', $phrase);
        }
        
        // Remove connection words
        $connectionWords = ['com', 'with', 'e', 'and', 'ou', 'or'];
        foreach ($connectionWords as $word) {
            $pattern = '/\s+' . $word . '\s+/i';
            $phrase = preg_replace($pattern, ' ', $phrase);
        }
        
        return trim($phrase);
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
