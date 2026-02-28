# Algorithm Guide

## Machine Learning Algorithms Overview

This guide explains the algorithms used in our ML systems and NLP chatbot.

## Algorithm Comparison

| System | Algorithm | Supervised | Key Strengths |
|---------|-----------|------------|---------------|
| Anomaly Detection | Isolation Forest | ❌ No | Explains anomalies, unsupervised |
| Iris Classification | Naive Bayes | ✅ Yes | Simple, probabilistic |
| Recommendations | KNN | ✅ Yes | Intuitive, instance-based |
| Chatbot NLP | KNN + Rules | ✅ Yes | Multilingual, context-aware |

## 1. Anomaly Detection System

### Isolation Forest

**Type**: Unsupervised Anomaly Detection

**How it works**:
1. Randomly selects a feature and splits the data
2. Recursively isolates observations by randomly selecting features and splits
3. Anomalies require fewer splits to isolate (shorter path length)
4. Anomaly score = path length normalized by tree depth

**Key Properties**:
- Unsupervised: Doesn't require labeled anomaly data
- Efficient: O(n) training, O(1) prediction
- Interpretable: Path length explains why an observation is anomalous
- Scalable: Works well with high-dimensional data

**Use Cases**:
- Fraud detection
- Outlier identification
- Novelty detection
- Anomaly explanation

**Threshold**: 0.3 (configurable)

### Detection Command

```bash
php artisan anomaly:detect
```

**Command Options**:
- `--retrain` - Force model retraining
- `--check-user=ID` - Check specific user ID

**Usage Examples**:

```bash
# Run anomaly detection
php artisan anomaly:detect

# Force retraining and detect
php artisan anomaly:detect --retrain

# Check specific user
php artisan anomaly:detect --check-user=1
```

**Model Location**: `storage/app/anomaly_detector.model`

**Dataset**: Uses user behavior data from the database

**When to Use**:
- Regular monitoring for unusual activity
- Fraud detection
- User behavior analysis
- System health monitoring

### Rule-Based Detection

**Complementary Approach**:
```
IF rentals_per_day > 0.5 THEN high_frequency
IF rating_stddev > 1.5 THEN inconsistent_ratings
IF late_returns > 30% THEN frequent_late_returns
```

**Use Cases**:
- Simple, explainable thresholds
- Complements ML model scores
- Catches obvious anomalies
- Easy to audit and adjust

### Hybrid Approach

**Best of both worlds**:
- ML model catches complex patterns
- Rules catch obvious anomalies
- Combined score provides comprehensive detection
- Adjustable sensitivity via thresholds

## 2. Iris Flower Classification

### Naive Bayes

**Type**: Supervised Classification

**How it works**:
1. Calculates probability of each class given features
2. Uses Bayes' theorem to combine probabilities
3. Predicts class with highest posterior probability

**Key Properties**:
- Simple and fast
- Works well with categorical data
- Probabilistic output
- Assumes feature independence

**Use Cases**:
- Multi-class classification
- Text classification
- When feature independence assumption holds

### Training Command

The Iris classification model is trained automatically when predictions are made:

```bash
# No separate training command - model trains on first prediction
# The model is automatically persisted to storage/app/iris.model
```

**Model Location**: `storage/app/iris.model`

**Dataset**: Uses the built-in Iris dataset from `storage/app/iris.csv`

**When Training Occurs**:
- First prediction request
- When model file doesn't exist
- Automatic persistence after training

## 3. Movie Recommendation System

### K-Nearest Neighbors (KNN) Regressor

**Type**: Supervised Regression

**How it works**:
1. Finds k most similar users (neighbors)
2. Averages their ratings for prediction
3. Uses cosine similarity for distance

**Key Properties**:
- Instance-based learning
- Simple and intuitive
- Works well with small datasets
- Sensitive to feature scaling

**Use Cases**:
- Collaborative filtering
- Content-based recommendations
- When neighbors are meaningful

### Training Command

```bash
php artisan recommendations:train
```

**Command Options**:
- `--force` - Force retraining even if model exists

**Usage Examples**:

```bash
# Train recommendation model
php artisan recommendations:train

# Force retraining
php artisan recommendations:train --force
```

**Model Location**: `storage/app/movie_recommender.model`

**Dataset**: Uses movie ratings from the database

**When to Use**:
- Initial setup
- After adding new movies or ratings
- Periodic retraining for improved accuracy

## 4. AI Chatbot System

### Natural Language Processing Pipeline

**Architecture**:
```
User Input → Language Detection → Intent Classification → Entity Extraction → Response Generation
```

### Intent Classification Algorithm

**Hybrid Approach**: Combines ML and rule-based classification

**ML Component**:
- **Algorithm**: K-Nearest Neighbors (KNN)
- **Features**: Keyword presence, question structure, language patterns
- **Training Data**: Labeled samples for each intent type
- **Output**: Intent probability scores

**Rule-Based Component**:
- **Fallback**: Keyword matching when ML confidence is low
- **Patterns**: Regex patterns for common question structures
- **Thresholds**: Configurable confidence thresholds

**Supported Intents**:
1. `actor` - Questions about actors/actresses
2. `director` - Questions about directors
3. `genre` - Questions about movie genres
4. `year` - Questions about release years
5. `rating` - Questions about movie ratings
6. `title` - Questions about movie titles
7. `recommendation` - Requests for movie recommendations

### Compound Question Detection

**Algorithm**: Rule-based pattern matching with context analysis

**Detection Patterns**:
- **Explicit Connectors**: "and", "e", "y", "or", "ou"
- **Multiple Criteria**: Multiple person names + genre/year keywords
- **Implicit Compounds**: "action movies with Brad Pitt"

**Processing Steps**:
1. Check for connecting words
2. Analyze question structure
3. Count potential entities
4. Validate intent combinations
5. Extract snippets for each intent

**Supported Combinations**:
- `actor + director`
- `actor + genre`
- `actor + year`
- `director + genre`
- `director + year`
- `genre + year`
- `genre + rating`

### Entity Extraction Algorithm

**Named Entity Recognition**:

1. **Person Names**:
   - Proper noun detection (capitalized words)
   - Context analysis (after "with", "have", "por", etc.)
   - Language-specific patterns

2. **Years**:
   - 4-digit year patterns (`\d{4}`)
   - Decade references ("movies from the 80s")
   - Relative years ("recent movies", "old movies")

3. **Genres**:
   - Keyword matching against genre lists
   - Context-aware extraction
   - Multi-word genre detection

**Example**:
```
Input: "What movies have Tom Hanks and Steven Spielberg?"
Output: 
- Actor: "Tom Hanks"
- Director: "Steven Spielberg"
```

### Response Generation

**Template-Based System**:
- Language-specific response templates
- Parameter replacement using Laravel's translation system
- Dynamic content insertion
- Proper formatting with newlines and bullet points

**Template Structure**:
```php
// Portuguese template
'compound_actor_director_found' => 'Encontrei filmes com :actor dirigidos por :director:\n:list'

// English template  
'compound_actor_director_found' => 'I found movies with :actor directed by :director:\n:list'
```

### Language Detection Algorithm

**Approach**: Token-based language identification

**Language-Specific Words**:
- Portuguese: "que", "quais", "filmes", "ator", "diretor"
- English: "what", "which", "movies", "actor", "director"
- Spanish: "qué", "cuáles", "películas", "actor", "director"

**Decision Logic**:
1. Tokenize input question
2. Count matches for each language
3. Select language with most matches
4. Default to English if ambiguous

### Performance Characteristics

**Complexity Analysis**:
- Intent Classification: O(n) where n = number of features
- Entity Extraction: O(m) where m = question length
- Response Generation: O(1) template lookup
- Overall: O(n + m) per question

**Optimizations**:
- Feature vector caching
- Translation caching
- Database query optimization
- Eager loading of relationships

**Response Times**:
- Simple questions: < 100ms
- Compound questions: < 200ms
- Complex queries: < 500ms

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

## When to Use Which

- **Anomaly Detection**: Unusual behavior, fraud, outliers
- **Iris Classification**: Multi-class prediction, categorical data
- **Recommendations**: Personalization, similarity matching
- **Chatbot NLP**: Natural language understanding, intent classification

All algorithms are production-ready and well-documented in their respective files.

**Last Updated**: 2024-02-27
**Version**: 2.0.0
---

**Chatbot Documentation**: See [CHATBOT_SYSTEM.md](CHATBOT_SYSTEM.md) for detailed chatbot implementation guide.
