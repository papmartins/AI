# Chatbot Language-Agnostic System Documentation

## Overview

This document describes the language-agnostic architecture of the AI Movie Chatbot system, which enables seamless support for multiple languages without hardcoded strings in the business logic.

## Architecture

### Language Files Structure

```
resources/lang/
├── en/chatbot/
│   ├── feature_keywords.php      # Intent-related keywords
│   ├── trigger_words.php         # Entity extraction triggers
│   ├── stop_words.php            # Stop words for filtering
│   ├── compound_indicators.php   # AND/OR indicators
│   └── title_patterns.php        # Title extraction patterns
├── pt/chatbot/
│   ├── feature_keywords.php      # Portuguese equivalents
│   ├── trigger_words.php         # Portuguese triggers
│   ├── stop_words.php            # Portuguese stop words
│   ├── compound_indicators.php   # Portuguese AND/OR
│   └── title_patterns.php        # Portuguese title patterns
└── es/chatbot/
    ├── feature_keywords.php      # Spanish equivalents
    ├── trigger_words.php         # Spanish triggers
    ├── stop_words.php            # Spanish stop words
    ├── compound_indicators.php   # Spanish AND/OR
    └── title_patterns.php        # Spanish title patterns
```

### Core Principles

1. **No Hardcoded Strings**: All language-specific content resides in language files
2. **Dynamic Loading**: Services load appropriate language files based on detected language
3. **Fallback Support**: Graceful fallback when language files are missing
4. **Consistent Structure**: Same file structure across all languages
5. **Easy Maintenance**: Translators update files without touching PHP code

## Language File Specifications

### feature_keywords.php

Defines keywords for intent classification:

```php
return [
    'actor' => ['actor', 'starred', 'starring', 'cast'],
    'director' => ['director', 'directed', 'directed by'],
    'genre' => ['action', 'comedy', 'horror', 'drama'],
    'year' => ['released', 'from', 'year'],
    'rating' => ['rated', 'good', 'rating']
];
```

### trigger_words.php

Defines trigger words for entity extraction:

```php
return [
    'prepositions' => ['by', 'with', 'por', 'com'],
    'verbs' => ['have', 'has', 'tem', 'têm']
];
```

### stop_words.php

Defines stop words for filtering:

```php
return [
    'articles' => ['the', 'a', 'an', 'o', 'a', 'os', 'as'],
    'pronouns' => ['who', 'which', 'que', 'quem'],
    'verbs' => ['have', 'with', 'têm', 'com'],
    'nouns' => ['movies', 'filmes'],
    'prepositions' => ['with', 'in', 'for', 'com', 'em', 'para']
];
```

### compound_indicators.php

Defines AND/OR indicators for compound questions:

```php
return [
    'or' => ['or', 'ou'],
    'and' => ['and', 'e', 'y']
];
```

### title_patterns.php

Defines title extraction patterns:

```php
return [
    'indicators' => ['in the title', 'with title', 'no título', 'com título'],
];
```

## Service Methods

### IntentClassifierService

#### `getDirectorPatterns(string $language): array`
Loads director-related patterns from language files.

#### `getCompoundIndicators(string $language): array`
Loads AND/OR indicators from language files.

### EntityExtractorService

#### `getTriggerWords(string $language): array`
Loads trigger words (prepositions/verbs) from language files.

#### `getStopWords(string $language): array`
Loads stop words from language files.

#### `getTitlePatterns(string $language): array`
Loads title patterns from language files.

#### `cleanTitlePhrase(string $phrase, string $language): string`
Centralized method for cleaning title phrases.

### NLPChatbotService

#### `getCompoundIndicators(string $language): array`
Loads AND/OR indicators from language files.

## Language Detection

The system automatically detects the language of user questions using:

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
    
    return 'pt'; // default
}
```

## Intent Classification Flow

1. **Detect Language**: Analyze question to determine language
2. **Load Patterns**: Load appropriate language files
3. **Pattern Matching**: Check against loaded patterns
4. **Fallback to ML**: Use machine learning if no patterns match
5. **Fallback to Defaults**: Use fallback patterns if language files missing

## Entity Extraction Flow

1. **Detect Language**: Determine question language
2. **Load Language Patterns**: Load trigger words, stop words, etc.
3. **Extract Entities**: Apply language-specific extraction logic
4. **Clean Results**: Remove stop words and normalize
5. **Return Results**: Provide extracted entities

## Adding New Languages

To add support for a new language (e.g., French):

1. **Create Language Files**:
   ```bash
   mkdir -p resources/lang/fr/chatbot
   touch resources/lang/fr/chatbot/{feature_keywords,trigger_words,stop_words,compound_indicators,title_patterns}.php
   ```

2. **Translate Content**: Fill files with appropriate translations

3. **Update Detection**: Add French words to `detectLanguage()` method

4. **Test**: Verify the new language works correctly

## Maintenance Guidelines

### Adding New Keywords

To add new keywords for an existing language:

1. **Edit Language File**: Update the appropriate file
2. **No Code Changes**: Business logic remains unchanged
3. **Test**: Verify the new keywords work

### Updating Patterns

To modify extraction patterns:

1. **Edit Language File**: Update patterns in the file
2. **Clear Cache**: If using cached language files
3. **Test**: Verify patterns work as expected

## Testing Strategy

### Unit Tests

Test each language file independently:

```php
// Test Portuguese patterns
$patterns = include 'resources/lang/pt/chatbot/trigger_words.php';
$this->assertContains('por', $patterns['prepositions']);
$this->assertContains('com', $patterns['prepositions']);
```

### Integration Tests

Test complete intent classification:

```php
$classifier = new IntentClassifierService();
$result = $classifier->classifyIntention('Filmes dirigidos por Christopher Nolan');
$this->assertEquals('director', $result);
```

### End-to-End Tests

Test complete user interactions:

```php
$chatbot = new NLPChatbotService();
$response = $chatbot->processQuestion('Filmes com Die Hard no título', 'user123');
$this->assertStringContainsString('Die Hard', $response);
```

## Performance Considerations

1. **File Caching**: Consider caching language files in production
2. **Lazy Loading**: Load language files only when needed
3. **Memory Usage**: Language files are small and can be kept in memory
4. **Fallback Efficiency**: Fallback patterns minimize file I/O

## Error Handling

The system gracefully handles:

1. **Missing Language Files**: Falls back to English patterns
2. **Unknown Languages**: Defaults to Portuguese (primary language)
3. **Malformed Input**: Returns empty results rather than crashing
4. **Ambiguous Questions**: Uses ML fallback when patterns don't match

## Best Practices

1. **Consistent Structure**: Maintain same structure across all language files
2. **Comprehensive Coverage**: Include common variations and synonyms
3. **Regular Updates**: Keep language files current with new terms
4. **Documentation**: Document new patterns and keywords
5. **Testing**: Test new patterns thoroughly before deployment

## Future Enhancements

1. **Automatic Translation**: Integrate with translation APIs
2. **User Preferences**: Allow users to set preferred language
3. **Language Switching**: Support mid-conversation language changes
4. **Regional Variations**: Handle regional dialects and slang
5. **Community Contributions**: Crowdsource language improvements

## Conclusion

This language-agnostic architecture ensures the chatbot system is:

- **Maintainable**: Easy to update and extend
- **Scalable**: Simple to add new languages
- **Reliable**: Graceful fallback mechanisms
- **Performant**: Efficient pattern matching
- **Global-Ready**: Built for international audiences

The system follows software engineering best practices and provides a solid foundation for multi-language support.
