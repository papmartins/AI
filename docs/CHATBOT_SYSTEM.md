# Chatbot System Documentation

## Overview

The AI Movie Chatbot is a natural language processing system that allows users to ask questions about movies in multiple languages (Portuguese, English, Spanish). The system can handle various types of questions including simple queries about actors, directors, genres, years, and ratings, as well as complex compound questions combining multiple criteria.

## Architecture

### Core Components

1. **NLPChatbotService** - Main service that orchestrates the chatbot functionality
2. **IntentClassifierService** - Classifies user intents and detects compound questions
3. **EntityExtractorService** - Extracts entities (names, years, genres) from questions
4. **MovieRecommender** - Provides movie recommendations

### Data Flow

```
User Question → Intent Classification → Entity Extraction → Database Query → Response Formatting → User
```

## Features

### Supported Question Types

#### Single Intent Questions
- **Actor**: "What movies have Tom Hanks?"
- **Director**: "Who directed Inception?"
- **Genre**: "Show me action movies"
- **Year**: "Movies from 2023"
- **Rating**: "Highly rated films"
- **Title**: "Movies with Matrix in the title"
- **Recommendation**: "Recommend some movies"

#### Compound Questions
- **Actor + Director**: "What movies have Tom Hanks and Steven Spielberg?"
- **Actor + Genre**: "Show me action movies with Brad Pitt"
- **Director + Year**: "Movies from 2023 directed by Christopher Nolan"
- **Actor + Director (Portuguese)**: "Que filmes existem com o realizador George Miller e o ator Charlize Theron"

### Language Support

- **Portuguese (PT)** - Full support
- **English (EN)** - Full support  
- **Spanish (ES)** - Full support

## Implementation Details

### Intent Classification

The system uses a combination of machine learning and rule-based approaches:

1. **ML Classifier**: Trained on labeled training samples for each language
2. **Fallback Rules**: Keyword-based classification when ML confidence is low
3. **Compound Detection**: Identifies questions with multiple intents using connecting words ("and", "e", "y", "or", "ou")

### Entity Extraction

The entity extractor identifies:
- **Person Names**: Uses proper noun detection and context analysis
- **Years**: Regex patterns for 4-digit years and decade references
- **Genres**: Keyword matching against genre lists
- **Titles**: Extracts potential title keywords from questions

### Response Formatting

Responses use Laravel's translation system with:
- **Language-specific templates** in `resources/lang/*/chatbot/responses.php`
- **Parameter replacement** using `:param` syntax
- **Proper formatting** with newlines and bullet points

## Technical Implementation

### Training Data

Training samples are defined in:
- `resources/lang/pt/chatbot/training_samples.php`
- `resources/lang/en/chatbot/training_samples.php`
- `resources/lang/es/chatbot/training_samples.php`

Each file contains examples for all supported intents.

### Feature Keywords

Intent-specific keywords are defined in:
- `resources/lang/pt/chatbot/feature_keywords.php`
- `resources/lang/en/chatbot/feature_keywords.php`
- `resources/lang/es/chatbot/feature_keywords.php`

### Response Templates

Response templates are defined in:
- `resources/lang/pt/chatbot/responses.php`
- `resources/lang/en/chatbot/responses.php`
- `resources/lang/es/chatbot/responses.php`

## Compound Question Handling

### Detection Logic

1. **Explicit Connectors**: Questions containing "and", "e", "y", "or", "ou"
2. **Multiple Criteria**: Questions with multiple person names + genre/year keywords
3. **Implicit Compounds**: Questions like "action movies with Brad Pitt" (no explicit connector)

### Supported Combinations

- `actor + director`
- `actor + genre`
- `actor + year`
- `director + genre`
- `director + year`
- `genre + year`
- `genre + rating`

### Processing Flow

```php
// Example: "What movies have Tom Hanks and Steven Spielberg?"
1. Detect "and" connector and multiple criteria
2. Classify as compound question with intents: ['actor', 'director']
3. Extract entities: 
   - Actor: "Tom Hanks"
   - Director: "Steven Spielberg"
4. Query database for movies matching both criteria
5. Format response using appropriate template
```

## Response Examples

### Simple Question (English)
**Input**: "What movies have Bruce Willis?"
**Output**: "I found movies with Bruce Willis:
• Die Hard (1988) - Action - 4.50/5
• The Sixth Sense (1999) - Thriller - 4.20/5"

### Compound Question (Portuguese)
**Input**: "Que filmes existem com o realizador George Miller e o ator Charlize Theron"
**Output**: "Encontrei filmes com Charlize Theron dirigidos por George Miller:
• Mad Max: Fury Road (2015) - Action - 4.50/5"

### No Results
**Input**: "Movies with non-existent actor"
**Output**: "I didn't find any movies with 'non-existent actor' in our catalog."

### Unknown Question
**Input**: "What's the weather today?"
**Output**: "I didn't understand your question. Please try asking about movie actors, directors, titles, genres, years, or ratings."

## Error Handling

### Common Error Cases

1. **Unknown Intent**: Falls back to keyword analysis
2. **Missing Entities**: Returns appropriate "not found" message
3. **Database Errors**: Graceful error handling with user-friendly messages
4. **Translation Missing**: Falls back to English translations

### Error Responses

- `actor_not_found`: When actor name cannot be extracted
- `no_actor_movies`: When no movies found for actor
- `compound_entities_not_found`: When compound question criteria cannot be extracted
- `no_compound_movies_found`: When no movies match compound criteria
- `unknown_question`: When question intent cannot be determined

## Performance Considerations

### Caching

- **Intent Classifier**: ML model is persisted to disk
- **Movie Recommendations**: Cached for better performance
- **Language Translations**: Loaded once and cached

### Optimization Techniques

1. **Eager Loading**: Database queries use `with()` for relationships
2. **Query Optimization**: Proper indexing on frequently searched fields
3. **Batch Processing**: Multiple entity extractions in single pass

## Integration

### API Endpoints

The chatbot is accessible via:
- **POST /api/chatbot**: Process user questions
- **GET /chatbot**: Web interface

### Request Format

```json
{
    "question": "What movies have Tom Hanks?",
    "language": "en",
    "user_id": "optional_user_identifier"
}
```

### Response Format

```json
{
    "response": "I found movies with Tom Hanks:\n• Forrest Gump (1994) - Drama - 4.70/5\n• Cast Away (2000) - Adventure - 4.30/5",
    "intents": ["actor"],
    "entities": {
        "actor": "Tom Hanks"
    },
    "language": "en"
}
```

## Training Command

The application includes an Artisan command for training the NLP chatbot model:

```bash
php artisan chatbot:train
```

### Command Options:

- `--force` - Force retraining even if model exists
- `--test` - Run in test mode without saving the model

### Usage Examples:

```bash
# Train new model (prompts if model exists)
php artisan chatbot:train

# Force retraining
php artisan chatbot:train --force

# Test training without saving
php artisan chatbot:train --test
```

### When to Use:

- Initial setup of the application
- After updating training data
- When adding new languages or intents
- Periodic retraining to improve accuracy

## Testing

### Test Coverage

- **Unit Tests**: Individual component testing
- **Integration Tests**: End-to-end question processing
- **Language Tests**: Multi-language support verification

### Test Cases

```php
// Example test cases
$testCases = [
    ['What movies have Bruce Willis?', 'actor', 'Bruce Willis'],
    ['Who directed Inception?', 'director', 'Christopher Nolan'],
    ['Action movies with Tom Cruise', ['actor', 'genre'], ['Tom Cruise', 'action']],
    ['Movies from 2023 directed by James Cameron', ['director', 'year'], ['James Cameron', '2023']]
];
```

## Deployment

### Requirements

- PHP 8.1+
- Laravel 10+
- MySQL 5.7+
- Rubix ML (for intent classification)

### Setup

1. Install dependencies: `composer install`
2. Set up database: `php artisan migrate`
3. Train ML models: `php artisan recommendations:train`
4. Seed sample data: `php artisan db:seed`

### Configuration

Environment variables in `.env`:
```env
CHATBOT_DEFAULT_LANGUAGE=en
CHATBOT_MAX_RESULTS=10
CHATBOT_ENABLE_CACHING=true
```

## Future Enhancements

### Planned Features

1. **Context Awareness**: Remember previous questions in conversation
2. **Personalization**: User-specific recommendations based on history
3. **Expanded Languages**: Support for additional languages
4. **Voice Interface**: Voice-based question processing
5. **Image Recognition**: Upload movie posters for identification

### Potential Improvements

- **Better NER**: Enhanced named entity recognition
- **Synonym Handling**: Support for movie synonyms and aliases
- **Fuzzy Matching**: Tolerant matching for typos and variations
- **Performance Optimization**: Faster response times for complex queries

## Troubleshooting

### Common Issues

1. **Intent Misclassification**: Add more training samples
2. **Entity Extraction Failures**: Improve regex patterns and keyword lists
3. **Translation Missing**: Add missing keys to language files
4. **Performance Bottlenecks**: Optimize database queries and caching

### Debugging Tools

```bash
# Test intent classification
php artisan tinker --execute="\App\Services\IntentClassifierService::classifyMultipleIntents('test question')"

# Test entity extraction  
php artisan tinker --execute="\App\Services\EntityExtractorService::extractPersonName('test question')"

# Clear caches
php artisan cache:clear
php artisan view:clear
```

## Contributing

### Guidelines

1. Follow existing code style and patterns
2. Add tests for new features
3. Update documentation for changes
4. Maintain backward compatibility

### Adding New Intents

1. Add training samples to language files
2. Add feature keywords
3. Add response templates
4. Implement handler method in NLPChatbotService
5. Update intent classification logic

### Adding New Languages

1. Create language directory in `resources/lang/`
2. Add translation files (training_samples.php, feature_keywords.php, responses.php)
3. Update language detection logic
4. Add language to supported languages list

## License

This chatbot system is open-source software licensed under the MIT license.

## Support

For issues, questions, or contributions, please contact the development team or open an issue in the project repository.