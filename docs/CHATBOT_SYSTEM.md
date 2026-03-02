# AI Movie Chatbot System Architecture

## Overview

This document provides a comprehensive overview of the AI Movie Chatbot system architecture, focusing on the language-agnostic design, component interactions, and key features.

## System Components

### 1. Core Services

#### IntentClassifierService
**Responsibility**: Classifies user intents (actor, director, genre, etc.)

**Key Features**:
- Language-agnostic intent classification
- ML model with rule-based fallback
- Compound question detection
- Dynamic pattern loading from language files

**Methods**:
- `classifyIntention()`: Main classification method
- `classifyMultipleIntents()`: Handles compound questions
- `getDirectorPatterns()`: Loads director patterns
- `getCompoundIndicators()`: Loads AND/OR indicators

#### EntityExtractorService
**Responsibility**: Extracts entities (titles, names, genres) from questions

**Key Features**:
- Language-agnostic entity extraction
- Trigger word-based extraction
- Stop word filtering
- Title pattern matching

**Methods**:
- `extractPersonName()`: Extracts actor/director names
- `extractTitleKeywords()`: Extracts movie titles
- `extractGenreKeywords()`: Extracts genre keywords
- `getTriggerWords()`: Loads trigger words
- `getStopWords()`: Loads stop words
- `getTitlePatterns()`: Loads title patterns
- `getGenreKeywords()`: Loads genre keywords

#### NLPChatbotService
**Responsibility**: Main chatbot orchestration and response generation

**Key Features**:
- User question processing
- Intent-based response routing
- Conversation context management
- Multi-language support

**Methods**:
- `processQuestion()`: Main entry point
- `handleSingleIntentQuestion()`: Single intent handling
- `handleCompoundQuestion()`: Compound intent handling
- `getCompoundIndicators()`: Loads AND/OR indicators

### 2. Language System

#### Language Files Structure
```
resources/lang/
├── en/chatbot/
│   ├── feature_keywords.php      # Intent keywords
│   ├── trigger_words.php         # Entity triggers
│   ├── stop_words.php            # Stop words
│   ├── compound_indicators.php   # AND/OR indicators
│   ├── title_patterns.php        # Title patterns
│   └── genre_keywords.php        # Genre keywords
├── pt/chatbot/
│   ├── feature_keywords.php      # Portuguese equivalents
│   ├── trigger_words.php         # Portuguese triggers
│   ├── stop_words.php            # Portuguese stop words
│   ├── compound_indicators.php   # Portuguese AND/OR
│   ├── title_patterns.php        # Portuguese title patterns
│   └── genre_keywords.php        # Portuguese genres
└── es/chatbot/
    ├── feature_keywords.php      # Spanish equivalents
    ├── trigger_words.php         # Spanish triggers
    ├── stop_words.php            # Spanish stop words
    ├── compound_indicators.php   # Spanish AND/OR
    ├── title_patterns.php        # Spanish title patterns
    └── genre_keywords.php        # Spanish genres
```

#### Language Detection
```php
protected function detectLanguage(string $question): string
{
    $tokens = $this->tokenizer->tokenize(strtolower($question));
    
    // Count language-specific words
    $portugueseWords = ['que', 'filmes', 'com', 'o', 'a', 'os', 'as'];
    $englishWords = ['what', 'movies', 'with', 'the', 'a', 'an'];
    $spanishWords = ['qué', 'películas', 'con', 'el', 'la', 'los', 'las'];
    
    // Count matches and return language with most matches
    // ... counting logic ...
    
    return 'pt'; // default to Portuguese
}
```

### 3. Data Models

#### Movie Model
- Represents movie data from database
- Relationships: Genre, Ratings, Rentals

#### User Model
- Represents user accounts
- Relationships: Ratings, Rentals, Wishlist

#### Rating Model
- Stores user movie ratings
- Used for recommendations

#### Rental Model
- Tracks movie rentals
- Relationships: User, Movie

## Key Features

### 1. Language-Agnostic Design

**Principle**: All language-specific content is stored in language files, not in code.

**Benefits**:
- Easy to add new languages
- No code changes for translations
- Consistent structure across languages
- Graceful fallback mechanisms

**Implementation**:
```php
// Load patterns from language file
$patterns = $this->getDirectorPatterns($language);

// Use patterns for classification
foreach ($patterns as $pattern) {
    if (str_contains($question, $pattern)) {
        return 'director';
    }
}
```

### 2. Intent Classification

**Process**:
1. Detect language
2. Load language-specific patterns
3. Pattern matching
4. ML fallback if needed
5. Return intent

**Example**:
```php
// "Filmes dirigidos por Christopher Nolan"
// 1. Detects language: 'pt'
// 2. Loads Portuguese patterns: ['dirigidos por', 'dirigido por']
// 3. Matches 'dirigidos por'
// 4. Returns 'director'
```

### 3. Entity Extraction

**Process**:
1. Detect language
2. Load trigger words and stop words
3. Tokenize question
4. Extract entities based on triggers
5. Clean and normalize results

**Example**:
```php
// "Filmes com Die Hard no título"
// 1. Detects language: 'pt'
// 2. Loads Portuguese triggers: ['no título']
// 3. Finds 'no título' at end
// 4. Extracts 'Die Hard'
// 5. Returns ['Die Hard']
```

### 4. Recommendation System

**Process**:
1. Analyze user ratings
2. Find similar users (collaborative filtering)
3. Generate personalized recommendations
4. Cache results for frequent users

**Features**:
- Popularity-based fallback
- Hybrid recommendations
- Performance optimization

## Workflow Examples

### Single Intent Question
**Question**: "Filmes dirigidos por Christopher Nolan"

**Flow**:
1. `NLPChatbotService->processQuestion()`
2. `IntentClassifierService->classifyIntention()` → 'director'
3. `EntityExtractorService->extractPersonName()` → 'Christopher Nolan'
4. `Movie::where('director', 'LIKE', '%Nolan%')->get()`
5. Return movie list

### Compound Question
**Question**: "Quais filmes têm Bruce Willis e quem os dirigiu?"

**Flow**:
1. `NLPChatbotService->processQuestion()`
2. `IntentClassifierService->classifyMultipleIntents()` → ['actor', 'director']
3. `EntityExtractorService->extractPersonName()` ×2 → ['Bruce Willis', '?']
4. Handle each intent separately
5. Combine results with AND condition

### Title Extraction
**Question**: "Filmes com Die Hard no título"

**Flow**:
1. `EntityExtractorService->extractTitleKeywords()`
2. Detects 'no título' pattern
3. Extracts content before pattern
4. Cleans result → 'Die Hard'
5. `Movie::where('title', 'LIKE', '%Die Hard%')->get()`

## Language Support

### Supported Languages
- **Portuguese (pt)**: Primary language
- **English (en)**: Full support
- **Spanish (es)**: Full support
- **Extensible**: Add new languages by creating files

### Adding New Languages

To add French support:

1. Create language files:
```bash
mkdir -p resources/lang/fr/chatbot
touch resources/lang/fr/chatbot/{feature_keywords,trigger_words,stop_words,compound_indicators,title_patterns,genre_keywords}.php
```

2. Translate content into each file

3. Add French words to `detectLanguage()` method

4. Test thoroughly

## Performance Optimization

### Caching Strategies
- **Language Files**: Consider caching in production
- **ML Models**: Load once, reuse across requests
- **Recommendations**: Cache for frequent users
- **Database Queries**: Optimize with indexes

### Lazy Loading
- Load language files only when needed
- Load ML models on first use
- Minimize file I/O operations

## Error Handling

### Graceful Degradation
1. **Missing Language Files**: Fall back to English
2. **Unknown Languages**: Default to Portuguese
3. **ML Failures**: Use rule-based fallback
4. **Database Errors**: Return user-friendly messages

### Robustness Features
- Try-catch blocks around critical operations
- Input validation and sanitization
- Fallback mechanisms at every level
- Comprehensive logging for debugging

## Testing Strategy

### Unit Tests
```php
// Test language file loading
$patterns = include 'resources/lang/pt/chatbot/trigger_words.php';
$this->assertContains('por', $patterns['prepositions']);
```

### Integration Tests
```php
// Test intent classification
$classifier = new IntentClassifierService();
$result = $classifier->classifyIntention('Filmes dirigidos por X');
$this->assertEquals('director', $result);
```

### End-to-End Tests
```php
// Test complete flow
$chatbot = new NLPChatbotService();
$response = $chatbot->processQuestion('Filmes com Die Hard');
$this->assertStringContainsString('Die Hard', $response);
```

## Deployment

### Requirements
- PHP 8.0+
- Laravel 9.x
- Rubix ML (for intent classification)
- MySQL 5.7+

### Environment Configuration
```env
APP_LOCALE=pt
FALLBACK_LOCALE=en
ML_MODEL_PATH=storage/app/nlp_model.rbx
CACHE_DRIVER=file
```

### Scaling
- **Horizontal Scaling**: Stateless design supports multiple instances
- **Database**: Read replicas for query load
- **Cache**: Redis for distributed caching
- **Queue**: Horizon for background jobs

## Maintenance

### Updating Language Files
1. Edit the appropriate language file
2. No code changes required
3. Clear cache if using file caching
4. Test the changes

### Adding Features
1. Add new intent type to language files
2. Update service methods if needed
3. Add test coverage
4. Document the feature

### Monitoring
- **Logs**: Comprehensive logging for debugging
- **Metrics**: Track response times, error rates
- **Alerts**: Set up for critical failures
- **Analytics**: User interaction patterns

## Future Enhancements

### Short-Term
1. **More Languages**: Italian, German, French
2. **User Preferences**: Language selection
3. **Performance**: Query optimization
4. **UI/UX**: Better error messages

### Long-Term
1. **Voice Interface**: Voice command support
2. **Multi-Modal**: Image/video understanding
3. **Personalization**: User-specific models
4. **Offline Mode**: Local processing

## Conclusion

This architecture provides a robust, maintainable, and scalable foundation for the AI Movie Chatbot system. The language-agnostic design ensures global readiness while maintaining performance and reliability.
